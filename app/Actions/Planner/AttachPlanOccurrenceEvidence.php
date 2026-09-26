<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanOccurrenceEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AttachPlanOccurrenceEvidence
{
    /**
     * @param  list<int>  $assetIds
     * @param  list<int>  $evidenceReferenceIds
     */
    public function execute(
        PlanOccurrence $occurrence,
        User $user,
        array $assetIds = [],
        array $evidenceReferenceIds = [],
    ): PlanOccurrence {
        abort_if(count($assetIds) > 20 || count($evidenceReferenceIds) > 20, 422, 'An Occurrence may attach at most twenty Assets and twenty evidence references at once.');
        abort_if($assetIds === [] && $evidenceReferenceIds === [], 422, 'Occurrence evidence requires at least one Asset or evidence reference.');
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($occurrence, $current, $assetIds, $evidenceReferenceIds): PlanOccurrence {
            $locked = PlanOccurrence::query()
                ->with(['plan.context'])
                ->lockForUpdate()
                ->findOrFail($occurrence->id);

            Gate::forUser($current)->authorize('participate', $locked->plan);
            $contextId = $locked->plan->context_id;
            $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
            $evidenceReferenceIds = array_values(array_unique(array_map('intval', $evidenceReferenceIds)));

            $assets = Asset::query()
                ->where('context_id', $contextId)
                ->whereIn('id', $assetIds)
                ->get();
            abort_unless($assets->count() === count($assetIds), 422, 'Every Occurrence Asset must belong to the Plan Context.');

            $references = ContentEvidenceReference::query()
                ->where('context_id', $contextId)
                ->whereIn('id', $evidenceReferenceIds)
                ->get();
            abort_unless($references->count() === count($evidenceReferenceIds), 422, 'Every Occurrence evidence reference must belong to the Plan Context.');

            $actor = Actor::query()->findOrFail($current->actor->id);

            foreach ($assets as $asset) {
                DB::table('plan_occurrence_assets')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'plan_occurrence_id' => $locked->id,
                    'asset_id' => $asset->id,
                    'added_by_actor_id' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($references as $reference) {
                DB::table('plan_occurrence_evidence_references')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'plan_occurrence_id' => $locked->id,
                    'content_evidence_reference_id' => $reference->id,
                    'added_by_actor_id' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($assets->isNotEmpty() || $references->isNotEmpty()) {
                PlanOccurrenceEvent::query()->create([
                    'plan_occurrence_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'event_type' => PlanOccurrenceEventType::EvidenceAttached,
                    'payload' => [
                        'asset_ids' => $assets->pluck('id')->values()->all(),
                        'content_evidence_reference_ids' => $references->pluck('id')->values()->all(),
                    ],
                ]);
            }

            return $locked->fresh([
                'assets',
                'evidenceReferences',
                'events',
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
