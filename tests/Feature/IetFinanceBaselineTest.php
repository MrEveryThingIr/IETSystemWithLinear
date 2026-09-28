<?php

namespace Tests\Feature;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Exchange\PublishIetValuation;
use App\Actions\Exchange\ReviewIetExchangeRequest;
use App\Actions\Exchange\SubmitIetExchangeRequest;
use App\Actions\Financial\ProposeIetSettlement;
use App\Actions\Financial\RecognizeFulfillmentFinancialObligation;
use App\Actions\Financial\RespondToSettlement;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\CommitmentKind;
use App\FulfillmentReviewDecision;
use App\IetExchangeDirection;
use App\IetExchangeStatus;
use App\Livewire\Finance\Index as FinanceIndex;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\FinancialObligation;
use App\Models\IetExchangeRequest;
use App\Models\IetValuationSnapshot;
use App\Models\Ledger;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\SettlementStatus;
use App\Support\IetValueMath;
use App\Support\IetValuation;
use App\Support\IetWallet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IetFinanceBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['release.profile' => 'planning_baseline']);
    }

    public function test_initial_iet_definition_is_unambiguous_and_exact(): void
    {
        $snapshot = app(IetValuation::class)->current();

        $this->assertSame(10000, $snapshot->usd_pico_per_iet);
        $this->assertSame('0.000001', IetValueMath::xPercent($snapshot->usd_pico_per_iet));
        $this->assertSame('0.00000001', IetValueMath::usdPerIet($snapshot->usd_pico_per_iet));

        $required = IetValueMath::ietMinorForUsdMinor(350, $snapshot->usd_pico_per_iet);

        $this->assertSame(350_000_000_000_000, $required);
        $this->assertSame('350000000', IetValueMath::formatIetMinor($required));
    }

    public function test_confirmed_deposit_credits_wallet_and_cashout_is_reserved_until_review(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            $owner = Actor::factory()->create();
            $admin = $this->exchangeAdmin();

            $deposit = app(SubmitIetExchangeRequest::class)->execute(
                $owner->user,
                IetExchangeDirection::Deposit,
                350,
                'OFFLINE-DEPOSIT-1',
            );

            $this->assertSame(IetExchangeStatus::Pending, $deposit->status);
            $this->assertSame(0, app(IetWallet::class)->availableMinor($owner->user));

            app(ReviewIetExchangeRequest::class)->confirm($deposit, $admin->user);

            $this->assertSame(
                350_000_000_000_000,
                app(IetWallet::class)->availableMinor($owner->user),
            );

            $cashout = app(SubmitIetExchangeRequest::class)->execute(
                $owner->user,
                IetExchangeDirection::Cashout,
                100,
                'OFFLINE-CASHOUT-1',
            );

            $this->assertSame(
                250_000_000_000_000,
                app(IetWallet::class)->availableMinor($owner->user),
            );

            app(ReviewIetExchangeRequest::class)->reject(
                $cashout,
                $admin->user,
                'External payout was not completed.',
            );

            $this->assertSame(
                350_000_000_000_000,
                app(IetWallet::class)->availableMinor($owner->user),
            );
            $this->assertSame(IetExchangeStatus::Rejected, $cashout->fresh()->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_new_valuation_affects_only_new_quotes(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            $owner = Actor::factory()->create();
            $admin = $this->exchangeAdmin();

            $before = app(SubmitIetExchangeRequest::class)->execute(
                $owner->user,
                IetExchangeDirection::Deposit,
                350,
            );

            $newSnapshot = app(PublishIetValuation::class)->execute(
                $admin->user,
                '0.0000010001',
                'First controlled valuation step.',
                ['successful_flows' => 100],
            );

            $after = app(SubmitIetExchangeRequest::class)->execute(
                $owner->user,
                IetExchangeDirection::Deposit,
                350,
            );

            $this->assertSame('0.0000010001', IetValueMath::xPercent($newSnapshot->usd_pico_per_iet));
            $this->assertNotSame($before->iet_valuation_snapshot_id, $after->iet_valuation_snapshot_id);
            $this->assertSame(350_000_000_000_000, $before->iet_amount_minor);
            $this->assertLessThan($before->iet_amount_minor, $after->iet_amount_minor);
            $this->assertSame(
                10000,
                IetValuationSnapshot::query()->findOrFail($before->iet_valuation_snapshot_id)->usd_pico_per_iet,
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_usd_obligation_reserves_then_transfers_locked_iet_on_confirmation(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            $debtor = Actor::factory()->create();
            $creditor = Actor::factory()->create();
            $admin = $this->exchangeAdmin();

            $this->fund($debtor, $admin, 1000);

            $obligation = $this->acceptedUsdObligation($debtor, $creditor, 350);

            $settlement = app(ProposeIetSettlement::class)->execute(
                $obligation,
                $debtor->user,
                350,
                'Settle accepted work internally.',
            );

            $this->assertSame(SettlementStatus::PendingConfirmation, $settlement->status);
            $this->assertSame(350_000_000_000_000, $settlement->iet_amount_minor);
            $this->assertSame(
                650_000_000_000_000,
                app(IetWallet::class)->availableMinor($debtor->user),
            );
            $this->assertSame(
                350_000_000_000_000,
                app(IetWallet::class)->reservedMinor($debtor->user),
            );
            $this->assertSame(0, app(IetWallet::class)->availableMinor($creditor->user));

            app(RespondToSettlement::class)->confirm($settlement, $creditor->user);

            $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);
            $this->assertSame(0, $obligation->fresh()->outstandingMinor());
            $this->assertSame(0, app(IetWallet::class)->reservedMinor($debtor->user));
            $this->assertSame(
                350_000_000_000_000,
                app(IetWallet::class)->availableMinor($creditor->user),
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_rejected_iet_settlement_returns_the_reserved_balance(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            $debtor = Actor::factory()->create();
            $creditor = Actor::factory()->create();
            $admin = $this->exchangeAdmin();

            $this->fund($debtor, $admin, 500);
            $obligation = $this->acceptedUsdObligation($debtor, $creditor, 200);

            $settlement = app(ProposeIetSettlement::class)->execute(
                $obligation,
                $debtor->user,
                200,
            );

            $this->assertSame(
                300_000_000_000_000,
                app(IetWallet::class)->availableMinor($debtor->user),
            );

            app(RespondToSettlement::class)->reject(
                $settlement,
                $creditor->user,
                'The claimed settlement should not be accepted.',
            );

            $this->assertSame(SettlementStatus::Rejected, $settlement->fresh()->status);
            $this->assertSame(
                500_000_000_000_000,
                app(IetWallet::class)->availableMinor($debtor->user),
            );
            $this->assertSame(0, app(IetWallet::class)->reservedMinor($debtor->user));
            $this->assertSame(200, $obligation->fresh()->outstandingMinor());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_non_usd_obligation_is_not_given_an_invented_fx_quote(): void
    {
        $debtor = Actor::factory()->create();
        $creditor = Actor::factory()->create();
        $admin = $this->exchangeAdmin();

        $this->fund($debtor, $admin, 1000);
        $obligation = $this->acceptedObligation($debtor, $creditor, 'EUR', 350);

        try {
            app(ProposeIetSettlement::class)->execute($obligation, $debtor->user, 350);
            $this->fail('Non-USD obligation received an invented IET FX quote.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('settlements', 0);
    }

    public function test_opening_finance_is_read_only_until_a_real_iet_action_occurs(): void
    {
        $actor = Actor::factory()->create();

        $this->assertDatabaseCount('ledgers', 0);

        Livewire::actingAs($actor->user)
            ->test(FinanceIndex::class)
            ->assertSee(__('finance_baseline.title'))
            ->assertSee('0')
            ->assertSee('0.000001');

        $this->assertDatabaseCount('ledgers', 0);

        $this->actingAs($actor->user)
            ->get(route('finance.index'))
            ->assertOk();
    }

    private function exchangeAdmin(): Actor
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->for($admin->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        return $admin;
    }

    private function fund(Actor $owner, Actor $admin, int $usdMinor): IetExchangeRequest
    {
        $request = app(SubmitIetExchangeRequest::class)->execute(
            $owner->user,
            IetExchangeDirection::Deposit,
            $usdMinor,
        );

        return app(ReviewIetExchangeRequest::class)->confirm($request, $admin->user);
    }

    private function acceptedUsdObligation(
        Actor $debtor,
        Actor $creditor,
        int $amountMinor,
    ): FinancialObligation {
        return $this->acceptedObligation($debtor, $creditor, 'USD', $amountMinor);
    }

    private function acceptedObligation(
        Actor $debtor,
        Actor $creditor,
        string $unit,
        int $amountMinor,
    ): FinancialObligation {
        $contract = app(CreateDirectContract::class)->execute(
            $debtor->user,
            'Internal settlement work',
            [['actor' => $creditor, 'role' => 'provider']],
            'Provider performs accepted work and the client recognizes the resulting obligation.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
        );

        $version = $contract->versions()->sole();
        app(AcceptContractVersion::class)->execute($version, $creditor->user);

        $commitment = app(CreateCommitment::class)->execute(
            $contract->fresh(),
            $debtor->user,
            $creditor,
            $debtor,
            CommitmentKind::Work,
            'Accepted service unit',
            1,
            'unit',
        );

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $creditor->user,
            1,
        );

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $debtor->user,
            FulfillmentReviewDecision::Accepted,
        );

        return app(RecognizeFulfillmentFinancialObligation::class)->execute(
            $fulfillment->fresh(),
            $debtor->user,
            $unit,
            $amountMinor,
        );
    }
}
