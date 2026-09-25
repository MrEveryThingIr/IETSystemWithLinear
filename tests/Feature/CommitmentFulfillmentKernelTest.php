<?php

namespace Tests\Feature;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Commitments\CreateCommitmentPlan;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Contracts\ProposeContractAmendment;
use App\Actions\Conversations\PostContextMessage;
use App\Actions\Fulfillments\OpenFulfillmentDispute;
use App\Actions\Fulfillments\ResolveFulfillmentDispute;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\CommitmentKind;
use App\ContractVersionStatus;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\PlanScheduleFrequency;
use App\Support\CommitmentProgress;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommitmentFulfillmentKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_commitments_bind_the_exact_active_contract_version_across_future_amendment(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 07:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();
            $contract = $this->activeContract($alice, $bob);

            $versionOne = $contract->activeVersionRecord();
            $this->assertInstanceOf(ContractVersion::class, $versionOne);

            $first = app(CreateCommitment::class)->execute(
                $contract,
                $alice->user,
                $bob,
                $alice,
                CommitmentKind::Work,
                'Riverside workday one',
                '1',
                'day',
            );

            $effective = CarbonImmutable::parse('2026-09-26 06:00:00 UTC');

            $versionTwo = app(ProposeContractAmendment::class)->execute(
                $contract,
                $alice->user,
                'Riverside paid work — amended',
                'Version 2 terms govern future work only.',
                $effective,
                'UTC',
                versionNote: 'Future terms',
            );

            app(AcceptContractVersion::class)->execute($versionTwo, $bob->user);

            $this->assertSame(ContractVersionStatus::Active, $versionOne->fresh()->status);
            $this->assertSame(ContractVersionStatus::Accepted, $versionTwo->fresh()->status);
            $this->assertSame($versionOne->id, $first->contract_version_id);

            CarbonImmutable::setTestNow('2026-09-26 06:01:00 UTC');
            $this->assertSame(1, app(ActivateDueContractVersions::class)->execute());

            $second = app(CreateCommitment::class)->execute(
                $contract->fresh(),
                $alice->user,
                $bob,
                $alice,
                CommitmentKind::Work,
                'Riverside workday two',
                '1',
                'day',
            );

            $this->assertSame(ContractVersionStatus::Superseded, $versionOne->fresh()->status);
            $this->assertSame(ContractVersionStatus::Active, $versionTwo->fresh()->status);
            $this->assertSame($versionOne->id, $first->fresh()->contract_version_id);
            $this->assertSame($versionTwo->id, $second->contract_version_id);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_completed_planner_occurrence_becomes_reviewed_fulfillment_with_exact_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 07:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();
            $alice->user->update(['timezone' => 'UTC']);
            $bob->user->update(['timezone' => 'UTC']);

            $contract = $this->activeContract($alice, $bob);
            $commitment = app(CreateCommitment::class)->execute(
                $contract,
                $alice->user,
                $bob,
                $alice,
                CommitmentKind::Work,
                'Riverside construction workday',
                '1',
                'day',
                'Bob performs one agreed construction workday.',
            );

            $plan = app(CreateCommitmentPlan::class)->execute(
                $commitment,
                $alice->user,
                PlanScheduleFrequency::Once,
                '2026-09-25',
                '08:00',
                540,
            );

            $occurrence = $plan->occurrences()->sole();

            $this->assertSame('commitment', $plan->origin_type);
            $this->assertSame($commitment->uuid, $plan->origin_uuid);
            $this->assertSame('commitment', $occurrence->origin_type);
            $this->assertSame($commitment->uuid, $occurrence->origin_uuid);

            CarbonImmutable::setTestNow('2026-09-25 08:00:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);

            CarbonImmutable::setTestNow('2026-09-25 17:00:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user);

            $context = $contract->contextBinding()->with('context')->sole()->context;
            $asset = Asset::factory()->create([
                'context_id' => $context->id,
                'group_space_id' => null,
                'uploaded_by_actor_id' => $bob->id,
            ]);

            $version = $commitment->contractVersion()->with('termsRevision')->sole();
            $revision = $version->termsRevision;

            $reference = ContentEvidenceReference::factory()->create([
                'context_id' => $context->id,
                'space_content_id' => $revision->space_content_id,
                'space_content_revision_id' => $revision->id,
                'created_by_actor_id' => $bob->id,
            ]);

            app(AttachPlanOccurrenceEvidence::class)->execute(
                $occurrence,
                $bob->user,
                assetIds: [$asset->id],
                evidenceReferenceIds: [$reference->id],
            );

            app(PostContextMessage::class)->execute(
                $context,
                $bob->user,
                'I completed the work and Alice accepts it.',
            );

            $this->assertDatabaseCount('fulfillments', 0);

            $fulfillment = app(SubmitFulfillment::class)->execute(
                $commitment,
                $bob->user,
                '1',
                occurrence: $occurrence,
                notes: 'Completed Riverside workday.',
            );

            $this->assertSame(540, $fulfillment->duration_minutes);
            $this->assertSame('2026-09-25 08:00:00', $fulfillment->actual_start_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-25 17:00:00', $fulfillment->actual_end_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertSame([$asset->id], $fulfillment->assets->pluck('id')->all());
            $this->assertSame([$reference->id], $fulfillment->evidenceReferences->pluck('id')->all());
            $this->assertTrue($reference->revision->hasVerifiableManifest());

            app(ReviewFulfillment::class)->execute(
                $fulfillment,
                $alice->user,
                FulfillmentReviewDecision::Accepted,
                'Workday accepted.',
            );

            $fulfillment = $fulfillment->fresh();
            $progress = app(CommitmentProgress::class);

            $this->assertSame(FulfillmentStatus::Accepted, $fulfillment->status);
            $this->assertSame('1.0000', $progress->acceptedQuantity($commitment));
            $this->assertSame('0.0000', $progress->remainingQuantity($commitment));
            $this->assertTrue($progress->isSatisfied($commitment));
            $this->assertDatabaseCount('journal_entries', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_clarification_requires_a_new_correction_and_preserves_original_facts(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $contract = $this->activeContract($alice, $bob);

        $commitment = app(CreateCommitment::class)->execute(
            $contract,
            $alice->user,
            $bob,
            $alice,
            CommitmentKind::Deliverable,
            'Submit fabrication report',
            '1',
            'report',
        );

        $original = app(SubmitFulfillment::class)->execute(
            $commitment,
            $bob->user,
            '1',
            notes: 'Initial report.',
        );

        $review = app(ReviewFulfillment::class)->execute(
            $original,
            $alice->user,
            FulfillmentReviewDecision::ClarificationRequested,
            'Add the dimensional-control evidence.',
        );

        $this->assertSame(FulfillmentStatus::ClarificationRequested, $original->fresh()->status);

        $replacement = app(SubmitFulfillment::class)->execute(
            $commitment,
            $bob->user,
            '1',
            notes: 'Corrected report with dimensional evidence.',
            corrects: $original,
        );

        $this->assertSame(FulfillmentStatus::Corrected, $original->fresh()->status);
        $this->assertSame(FulfillmentStatus::Submitted, $replacement->status);
        $this->assertSame($original->id, $replacement->corrects_fulfillment_id);
        $this->assertSame('Initial report.', $original->fresh()->notes);
        $this->assertSame(
            'Add the dimensional-control evidence.',
            $review->fresh()->note,
        );

        app(ReviewFulfillment::class)->execute(
            $replacement,
            $alice->user,
            FulfillmentReviewDecision::Accepted,
        );

        $this->assertSame('1.0000', app(CommitmentProgress::class)->acceptedQuantity($commitment));

        try {
            $original->update(['quantity' => '0.5000']);
            $this->fail('Submitted Fulfillment facts were mutated directly.');
        } catch (LogicException) {
            $this->assertSame('1.0000', $original->fresh()->quantity);
        }
    }

    public function test_dispute_temporarily_removes_accepted_quantity_until_explicit_resolution(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $contract = $this->activeContract($alice, $bob);

        $commitment = app(CreateCommitment::class)->execute(
            $contract,
            $alice->user,
            $bob,
            $alice,
            CommitmentKind::Service,
            'One service unit',
            '1',
            'service',
        );

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $bob->user,
            '1',
            notes: 'Service delivered.',
        );

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $alice->user,
            FulfillmentReviewDecision::Accepted,
        );

        $progress = app(CommitmentProgress::class);
        $this->assertSame('1.0000', $progress->acceptedQuantity($commitment));

        $dispute = app(OpenFulfillmentDispute::class)->execute(
            $fulfillment->fresh(),
            $bob->user,
            'The acceptance note omitted agreed evidence context.',
        );

        $this->assertSame(FulfillmentStatus::Disputed, $fulfillment->fresh()->status);
        $this->assertSame('0.0000', $progress->acceptedQuantity($commitment));

        app(ResolveFulfillmentDispute::class)->execute(
            $dispute,
            $alice->user,
            FulfillmentStatus::Accepted,
            'Acceptance confirmed after reviewing the context.',
        );

        $this->assertSame(FulfillmentStatus::Accepted, $fulfillment->fresh()->status);
        $this->assertSame('1.0000', $progress->acceptedQuantity($commitment));
        $this->assertNotNull($dispute->fresh()->resolved_at);
    }

    public function test_fulfillment_rejects_cross_context_evidence_and_outsider_access(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $contract = $this->activeContract($alice, $bob);

        $commitment = app(CreateCommitment::class)->execute(
            $contract,
            $alice->user,
            $bob,
            $alice,
            CommitmentKind::Other,
            'Private deliverable',
            '1',
            'item',
        );

        $foreignAsset = Asset::factory()->create([
            'uploaded_by_actor_id' => $outsider->id,
        ]);

        try {
            app(SubmitFulfillment::class)->execute(
                $commitment,
                $bob->user,
                '1',
                assetIds: [$foreignAsset->id],
            );

            $this->fail('Cross-Context Fulfillment evidence was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $bob->user,
            '1',
        );

        $this->assertTrue($alice->user->can('view', $commitment));
        $this->assertTrue($bob->user->can('view', $commitment));
        $this->assertFalse($outsider->user->can('view', $commitment));
        $this->assertFalse($outsider->user->can('view', $fulfillment));
        $this->assertFalse($outsider->user->can('review', $fulfillment));
    }

    private function activeContract(Actor $alice, Actor $bob): Contract
    {
        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Riverside paid work',
            [['actor' => $bob, 'role' => 'worker']],
            'Bob performs agreed work. Alice reviews accepted work.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
        );

        $version = $contract->versions()->sole();
        app(AcceptContractVersion::class)->execute($version, $bob->user);

        return $contract->fresh([
            'versions.termsRevision',
            'contextBinding.context',
        ]);
    }
}
