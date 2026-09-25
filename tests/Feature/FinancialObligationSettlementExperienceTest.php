<?php

namespace Tests\Feature;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\CommitmentKind;
use App\FulfillmentReviewDecision;
use App\Livewire\Commitments\Show as CommitmentShow;
use App\Livewire\Contracts\Show as ContractShow;
use App\Livewire\Financial\Show as FinancialShow;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\FinancialObligation;
use App\Models\JournalEntry;
use App\Models\Settlement;
use App\SettlementStatus;
use App\Support\ContextTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialObligationSettlementExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_work_flows_through_obligation_accounting_settlement_and_contract_summary(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 09:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();
            $outsider = Actor::factory()->create();

            $alice->user->forceFill(['timezone' => 'UTC'])->save();
            $bob->user->forceFill(['timezone' => 'UTC'])->save();

            [$contract, $commitment] = $this->activePaidWorkCommitment($alice, $bob);

            $fulfillment = app(SubmitFulfillment::class)->execute(
                $commitment,
                $bob->user,
                1,
                notes: 'Completed one Riverside workday.',
            );

            app(ReviewFulfillment::class)->execute(
                $fulfillment,
                $alice->user,
                FulfillmentReviewDecision::Accepted,
                'Accepted after site review.',
            );

            Livewire::actingAs($alice->user)
                ->test(CommitmentShow::class, ['commitment' => $commitment])
                ->set('financialAmounts.'.$fulfillment->id, '1500000')
                ->set('financialUnits.'.$fulfillment->id, 'IRR')
                ->call('recognizeFinancialObligation', $fulfillment->id)
                ->assertHasNoErrors()
                ->assertSee(__('financial.obligation.title'));

            $obligation = FinancialObligation::query()->with('monetaryUnit')->sole();

            $this->assertSame(1_500_000, $obligation->amount_minor);
            $this->assertSame('IRR', $obligation->monetaryUnit->code);

            $this->actingAs($outsider->user)
                ->get(route('financial-obligations.show', $obligation))
                ->assertForbidden();

            Livewire::actingAs($alice->user)
                ->test(FinancialShow::class, ['obligation' => $obligation])
                ->call('postObligationAccounting')
                ->assertHasNoErrors()
                ->set('settlementAmount', '1500000')
                ->set('settlementPaidAt', '2026-09-25T08:55')
                ->set('settlementMethod', 'bank transfer')
                ->set('settlementReference', 'RIVERSIDE-1')
                ->call('proposeSettlement')
                ->assertHasNoErrors()
                ->assertSee(__('financial.settlement.pending_confirmation'));

            $settlement = Settlement::query()->sole();

            Livewire::actingAs($bob->user)
                ->test(FinancialShow::class, ['obligation' => $obligation])
                ->call('postObligationAccounting')
                ->assertHasNoErrors()
                ->call('confirmSettlement', $settlement->id)
                ->assertHasNoErrors()
                ->assertSee(__('financial.settlement.confirmed'))
                ->call('postSettlementAccounting', $settlement->id)
                ->assertHasNoErrors();

            Livewire::actingAs($alice->user)
                ->test(FinancialShow::class, ['obligation' => $obligation])
                ->call('postSettlementAccounting', $settlement->id)
                ->assertHasNoErrors();

            $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);
            $this->assertSame(0, $obligation->fresh()->outstandingMinor());
            $this->assertDatabaseCount('journal_entries', 4);

            $entries = JournalEntry::query()
                ->whereIn('acting_user_id', [$alice->user->id, $bob->user->id])
                ->get();

            $this->assertSame(2, $entries->where('acting_user_id', $alice->user->id)->count());
            $this->assertSame(2, $entries->where('acting_user_id', $bob->user->id)->count());

            Livewire::actingAs($alice->user)
                ->test(ContractShow::class, ['contract' => $contract])
                ->assertSee(__('financial.summary.title'))
                ->assertSee('1500000')
                ->assertSee(route('financial-obligations.show', $obligation), false);

            $context = $contract->contextBinding()->with('context')->sole()->context;
            $timeline = app(ContextTimeline::class)->entries($context, $alice->user);

            $this->assertTrue($timeline->contains(fn ($entry): bool => $entry->kind === 'financial'));
            $this->assertTrue($timeline->contains(
                fn ($entry): bool => $entry->url === route('financial-obligations.show', $obligation),
            ));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_contract_party_outside_bilateral_obligation_cannot_see_financial_details(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Three-party Riverside contract',
            [
                ['actor' => $bob, 'role' => 'worker'],
                ['actor' => $carol, 'role' => 'site coordinator'],
            ],
            'Bob works. Alice reviews and owes accepted work. Carol coordinates the site.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
        );

        $version = $contract->versions()->sole();
        app(AcceptContractVersion::class)->execute($version, $bob->user);
        app(AcceptContractVersion::class)->execute($version, $carol->user);

        $commitment = app(CreateCommitment::class)->execute(
            $contract->fresh(),
            $alice->user,
            $bob,
            $alice,
            CommitmentKind::Work,
            'Riverside workday',
            1,
            'day',
        );

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $bob->user,
            1,
        );

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $alice->user,
            FulfillmentReviewDecision::Accepted,
        );

        Livewire::actingAs($alice->user)
            ->test(CommitmentShow::class, ['commitment' => $commitment])
            ->set('financialAmounts.'.$fulfillment->id, '1500000')
            ->set('financialUnits.'.$fulfillment->id, 'IRR')
            ->call('recognizeFinancialObligation', $fulfillment->id)
            ->assertHasNoErrors();

        $obligation = FinancialObligation::query()->sole();

        $this->assertTrue($carol->user->can('view', $contract));
        $this->assertFalse($carol->user->can('view', $obligation));

        Livewire::actingAs($carol->user)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertDontSee(route('financial-obligations.show', $obligation), false);

        $context = $contract->contextBinding()->with('context')->sole()->context;

        $carolTimeline = app(ContextTimeline::class)->entries($context, $carol->user);
        $aliceTimeline = app(ContextTimeline::class)->entries($context, $alice->user);

        $this->assertFalse($carolTimeline->contains(fn ($entry): bool => $entry->kind === 'financial'));
        $this->assertTrue($aliceTimeline->contains(fn ($entry): bool => $entry->kind === 'financial'));
    }

    /**
     * @return array{0: Contract, 1: Commitment}
     */
    private function activePaidWorkCommitment(Actor $alice, Actor $bob): array
    {
        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Riverside paid work',
            [['actor' => $bob, 'role' => 'worker']],
            'Bob performs accepted construction work. Alice owes 1,500,000 IRR per accepted workday.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
        );

        $version = $contract->versions()->sole();
        app(AcceptContractVersion::class)->execute($version, $bob->user);

        $commitment = app(CreateCommitment::class)->execute(
            $contract->fresh(),
            $alice->user,
            $bob,
            $alice,
            CommitmentKind::Work,
            'Riverside construction workday',
            1,
            'day',
        );

        return [$contract->fresh(['contextBinding.context']), $commitment];
    }
}
