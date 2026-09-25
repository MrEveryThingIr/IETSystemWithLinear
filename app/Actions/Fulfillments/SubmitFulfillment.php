<?php

namespace App\Actions\Fulfillments;

use App\CommitmentEventType;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\ContentEvidenceReference;
use App\Models\Fulfillment;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\Support\CommitmentProgress;
use App\Support\QuantityAmount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubmitFulfillment
{
    public function __construct(private readonly CommitmentProgress $progress) {}

    /**
     * @param  list<int>  $assetIds
     * @param  list<int>  $evidenceReferenceIds
     */
    public function execute(
        Commitment $commitment,
        User $user,
        string|int $quantity,
        ?PlanOccurrence $occurrence = null,
        ?CarbonInterface $actualStartAt = null,
        ?CarbonInterface $actualEndAt = null,
        ?string $notes = null,
        array $assetIds = [],
        array $evidenceReferenceIds = [],
        ?Fulfillment $corrects = null,
    ): Fulfillment {
        $current = $this->currentUser($user);

        if ($corrects instanceof Fulfillment) {
            Gate::forUser($current)->authorize('correct', $corrects);
        } else {
            Gate::forUser($current)->authorize('submit', $commitment);
        }

        try {
            $normalizedQuantity = QuantityAmount::positive($quantity);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        $notes = trim((string) $notes);
        abort_if(mb_strlen($notes) > 10000, 422, 'Fulfillment notes may not exceed 10000 characters.');
        abort_if(count($assetIds) > 20 || count($evidenceReferenceIds) > 20, 422, 'Fulfillment may attach at most twenty Assets and twenty evidence references.');

        return DB::transaction(function () use (
            $commitment,
            $current,
            $normalizedQuantity,
            $occurrence,
            $actualStartAt,
            $actualEndAt,
            $notes,
            $assetIds,
            $evidenceReferenceIds,
            $corrects,
        ): Fulfillment {
            $locked = Commitment::query()
                ->with([
                    'contractVersion.contract.contextBinding.context',
                    'planBinding.plan',
                ])
                ->lockForUpdate()
                ->findOrFail($commitment->id);

            if ($corrects instanceof Fulfillment) {
                $corrected = Fulfillment::query()
                    ->with(['commitment', 'correction'])
                    ->lockForUpdate()
                    ->findOrFail($corrects->id);

                abort_unless((int) $corrected->commitment_id === (int) $locked->id, 422, 'Correction must belong to the same Commitment.');
                Gate::forUser($current)->authorize('correct', $corrected);
                abort_if($corrected->correction instanceof Fulfillment, 422, 'This Fulfillment already has a correction.');
            } else {
                $corrected = null;
                Gate::forUser($current)->authorize('submit', $locked);
            }

            abort_unless(
                QuantityAmount::compare($normalizedQuantity, $this->progress->remainingQuantity($locked)) <= 0,
                422,
                'Fulfillment quantity exceeds the remaining Commitment quantity.',
            );

            $context = $locked->contractVersion->contract->contextBinding?->context;
            abort_unless($context !== null, 500, 'Contract Context is missing.');

            $resolvedOccurrence = null;
            $start = $actualStartAt;
            $end = $actualEndAt;
            $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
            $evidenceReferenceIds = array_values(array_unique(array_map('intval', $evidenceReferenceIds)));

            if ($occurrence instanceof PlanOccurrence) {
                $resolvedOccurrence = PlanOccurrence::query()
                    ->with(['assets', 'evidenceReferences'])
                    ->lockForUpdate()
                    ->findOrFail($occurrence->id);

                $binding = $locked->planBinding;
                abort_unless(
                    $binding !== null
                    && (int) $resolvedOccurrence->plan_id === (int) $binding->plan_id
                    && $resolvedOccurrence->origin_type === 'commitment'
                    && $resolvedOccurrence->origin_uuid === $locked->uuid,
                    422,
                    'Fulfillment Occurrence must be materialized from this Commitment.',
                );
                abort_unless(
                    $resolvedOccurrence->status === PlanOccurrenceStatus::Completed
                    && $resolvedOccurrence->actual_start_at !== null
                    && $resolvedOccurrence->actual_end_at !== null,
                    422,
                    'Time-based Fulfillment requires a completed Occurrence with actual start/end.',
                );

                $activeOccurrenceFulfillment = Fulfillment::query()
                    ->where('plan_occurrence_id', $resolvedOccurrence->id)
                    ->whereIn('status', [
                        FulfillmentStatus::Submitted->value,
                        FulfillmentStatus::Accepted->value,
                        FulfillmentStatus::ClarificationRequested->value,
                        FulfillmentStatus::Disputed->value,
                    ]);

                if ($corrected instanceof Fulfillment) {
                    $activeOccurrenceFulfillment->where('id', '!=', $corrected->id);
                }

                abort_if(
                    $activeOccurrenceFulfillment->exists(),
                    422,
                    'This Occurrence already has an active Fulfillment record.',
                );

                $start = $resolvedOccurrence->actual_start_at;
                $end = $resolvedOccurrence->actual_end_at;
                $assetIds = array_values(array_unique(array_merge(
                    $assetIds,
                    $resolvedOccurrence->assets->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                )));
                $evidenceReferenceIds = array_values(array_unique(array_merge(
                    $evidenceReferenceIds,
                    $resolvedOccurrence->evidenceReferences->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                )));
            }

            abort_if($start !== null && $end !== null && $end->lt($start), 422, 'Fulfillment actual end cannot precede actual start.');
            abort_if(($start === null) !== ($end === null), 422, 'Fulfillment actual start/end must be provided together.');

            $assets = Asset::query()
                ->where('context_id', $context->id)
                ->whereIn('id', $assetIds)
                ->get();
            abort_unless($assets->count() === count($assetIds), 422, 'Every Fulfillment Asset must belong to the Contract Context.');

            $references = ContentEvidenceReference::query()
                ->with('revision')
                ->where('context_id', $context->id)
                ->whereIn('id', $evidenceReferenceIds)
                ->get();
            abort_unless($references->count() === count($evidenceReferenceIds), 422, 'Every Fulfillment evidence reference must belong to the Contract Context.');
            abort_if(
                $references->contains(fn (ContentEvidenceReference $reference): bool => ! $reference->revision->hasVerifiableManifest()),
                422,
                'Fulfillment Content evidence must point to a sealed verifiable revision.',
            );

            $duration = $start !== null && $end !== null
                ? (int) $start->diffInMinutes($end)
                : null;

            $fulfillment = Fulfillment::query()->create([
                'commitment_id' => $locked->id,
                'plan_occurrence_id' => $resolvedOccurrence?->id,
                'corrects_fulfillment_id' => $corrected?->id,
                'submitted_by_actor_id' => $current->actor->id,
                'quantity' => $normalizedQuantity,
                'unit' => $locked->unit,
                'actual_start_at' => $start?->utc(),
                'actual_end_at' => $end?->utc(),
                'duration_minutes' => $duration,
                'notes' => $notes !== '' ? $notes : null,
                'status' => FulfillmentStatus::Submitted,
                'submitted_at' => now(),
            ]);

            foreach ($assets as $asset) {
                DB::table('fulfillment_assets')->insert([
                    'uuid' => (string) Str::uuid(),
                    'fulfillment_id' => $fulfillment->id,
                    'asset_id' => $asset->id,
                    'added_by_actor_id' => $current->actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($references as $reference) {
                DB::table('fulfillment_evidence_references')->insert([
                    'uuid' => (string) Str::uuid(),
                    'fulfillment_id' => $fulfillment->id,
                    'content_evidence_reference_id' => $reference->id,
                    'added_by_actor_id' => $current->actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($corrected instanceof Fulfillment) {
                $corrected->transition(FulfillmentStatus::Corrected);

                CommitmentEvent::query()->create([
                    'commitment_id' => $locked->id,
                    'actor_id' => $current->actor->id,
                    'event_type' => CommitmentEventType::FulfillmentCorrected,
                    'payload' => [
                        'original_fulfillment_uuid' => $corrected->uuid,
                        'replacement_fulfillment_uuid' => $fulfillment->uuid,
                    ],
                ]);
            }

            CommitmentEvent::query()->create([
                'commitment_id' => $locked->id,
                'actor_id' => $current->actor->id,
                'event_type' => CommitmentEventType::FulfillmentSubmitted,
                'payload' => [
                    'fulfillment_uuid' => $fulfillment->uuid,
                    'plan_occurrence_uuid' => $resolvedOccurrence?->uuid,
                    'quantity' => $normalizedQuantity,
                    'unit' => $locked->unit,
                    'asset_ids' => $assets->pluck('id')->values()->all(),
                    'content_evidence_reference_ids' => $references->pluck('id')->values()->all(),
                ],
            ]);

            return $fulfillment->fresh([
                'commitment',
                'occurrence',
                'corrects',
                'assets',
                'evidenceReferences.revision',
                'submitter.user',
            ]);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
