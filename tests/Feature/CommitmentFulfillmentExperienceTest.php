<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Livewire\Commitments\Create as CommitmentCreate;
use App\Livewire\Commitments\Show as CommitmentShow;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\Fulfillment;
use App\Support\ContextTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommitmentFulfillmentExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_creator_can_create_exact_version_commitment_from_contract_ui(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $contract = $this->activeContract($alice, $bob);
        $version = $contract->activeVersionRecord();

        $this->actingAs($alice->user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(route('commitments.create', $contract), false);

        Livewire::actingAs($alice->user)
            ->test(CommitmentCreate::class, ['contract' => $contract])
            ->assertSet('obligorUsername', $bob->user->username)
            ->assertSet('beneficiaryUsername', $alice->user->username)
            ->set('title', 'Riverside construction workday')
            ->set('description', 'Bob performs one agreed construction workday.')
            ->set('kind', 'work')
            ->set('quantity', '1')
            ->set('unit', 'day')
            ->call('save')
            ->assertHasNoErrors();

        $commitment = Commitment::query()->sole();

        $this->assertSame($version?->id, $commitment->contract_version_id);
        $this->assertSame($bob->id, $commitment->obligor_actor_id);
        $this->assertSame($alice->id, $commitment->beneficiary_actor_id);

        $this->actingAs($alice->user)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('Riverside construction workday')
            ->assertSee(route('commitments.show', $commitment), false);
    }

    public function test_riverside_workday_flows_from_planner_to_fulfillment_and_explicit_review_in_ui(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 07:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();
            $alice->user->forceFill(['timezone' => 'UTC'])->save();
            $bob->user->forceFill(['timezone' => 'UTC'])->save();

            $contract = $this->activeContract($alice, $bob);

            Livewire::actingAs($alice->user)
                ->test(CommitmentCreate::class, ['contract' => $contract])
                ->set('title', 'Riverside construction workday')
                ->set('kind', 'work')
                ->set('obligorUsername', $bob->user->username)
                ->set('beneficiaryUsername', $alice->user->username)
                ->set('quantity', '1')
                ->set('unit', 'day')
                ->call('save')
                ->assertHasNoErrors();

            $commitment = Commitment::query()->sole();

            Livewire::actingAs($alice->user)
                ->test(CommitmentShow::class, ['commitment' => $commitment])
                ->set('planFrequency', 'once')
                ->set('planStartsOn', '2026-09-25')
                ->set('planStartTime', '08:00')
                ->set('planDurationMinutes', 540)
                ->call('createPlan')
                ->assertHasNoErrors()
                ->assertSee('08:00');

            $occurrence = $commitment->planBinding()
                ->with('plan.occurrences')
                ->sole()
                ->plan
                ->occurrences
                ->sole();

            CarbonImmutable::setTestNow('2026-09-25 08:00:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);

            CarbonImmutable::setTestNow('2026-09-25 17:00:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user);

            Livewire::actingAs($bob->user)
                ->test(CommitmentShow::class, ['commitment' => $commitment])
                ->set('fulfillmentOccurrenceUuid', $occurrence->uuid)
                ->set('fulfillmentQuantity', '1')
                ->set('fulfillmentNotes', 'Completed the Riverside workday.')
                ->call('submitFulfillment')
                ->assertHasNoErrors()
                ->assertSee(__('commitments.fulfillment_status.submitted'));

            $fulfillment = Fulfillment::query()->sole();

            Livewire::actingAs($alice->user)
                ->test(CommitmentShow::class, ['commitment' => $commitment])
                ->set('reviewNotes.'.$fulfillment->id, 'Workday accepted after review.')
                ->call('review', $fulfillment->id, FulfillmentReviewDecision::Accepted->value)
                ->assertHasNoErrors()
                ->assertSee(__('commitments.fulfillment_status.accepted'))
                ->assertSee('0.0000');

            $this->assertSame(FulfillmentStatus::Accepted, $fulfillment->fresh()->status);
            $this->assertSame(540, $fulfillment->fresh()->duration_minutes);
            $this->assertDatabaseCount('journal_entries', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_commitment_history_composes_into_contract_timeline_and_outsider_is_denied(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $contract = $this->activeContract($alice, $bob);

        Livewire::actingAs($alice->user)
            ->test(CommitmentCreate::class, ['contract' => $contract])
            ->set('title', 'Private Riverside deliverable')
            ->set('kind', 'deliverable')
            ->set('obligorUsername', $bob->user->username)
            ->set('beneficiaryUsername', $alice->user->username)
            ->set('quantity', '1')
            ->set('unit', 'report')
            ->call('save');

        $commitment = Commitment::query()->sole();
        $context = $contract->contextBinding()->with('context')->sole()->context;

        $entries = app(ContextTimeline::class)->entries($context, $alice->user);

        $this->assertTrue($entries->contains(fn ($entry): bool => $entry->kind === 'commitment'));
        $this->assertTrue($entries->contains(
            fn ($entry): bool => $entry->url === route('commitments.show', $commitment),
        ));

        $this->actingAs($outsider->user)
            ->get(route('commitments.show', $commitment))
            ->assertForbidden();
    }

    private function activeContract(Actor $alice, Actor $bob): Contract
    {
        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Riverside paid work',
            [['actor' => $bob, 'role' => 'worker']],
            'Bob performs agreed work. Alice reviews actual Fulfillment explicitly.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
        );

        $version = $contract->versions()->sole();
        app(AcceptContractVersion::class)->execute($version, $bob->user);

        return $contract->fresh([
            'contextBinding.context',
            'versions',
        ]);
    }
}
