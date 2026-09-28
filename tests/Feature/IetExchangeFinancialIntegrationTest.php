<?php

namespace Tests\Feature;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Exchange\ChargeIetForUsdFlow;
use App\Actions\Exchange\CreateIetExchangeRequest;
use App\Actions\Exchange\EnsureIetWallet;
use App\Actions\Exchange\PublishIetValuationQuote;
use App\Actions\Exchange\ReviewIetExchangeRequest;
use App\Actions\Financial\ProposeSettlement;
use App\Actions\Financial\RecognizeUsdPricedFulfillmentInIet;
use App\Actions\Financial\RespondToSettlement;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\CommitmentKind;
use App\FulfillmentReviewDecision;
use App\IetExchangeDirection;
use App\IetExchangeStatus;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\IetExchangeRequest;
use App\Models\IetInternalCharge;
use App\Models\IetPricedFinancialObligation;
use App\Models\IetValuationQuote;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\SettlementStatus;
use App\Support\AccountingSummary;
use App\Support\IetPricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IetExchangeFinancialIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_quote_prices_three_fifty_as_three_point_five_million_iet_without_float_math(): void
    {
        $pricing = app(IetPricing::class);
        $quote = $pricing->currentQuote();

        $this->assertSame('0.0000010000', (string) $quote->usd_per_iet);
        $this->assertSame(3_500_000, $pricing->ietForUsdMinor(350, $quote));
        $this->assertSame(1_000_000, $pricing->ietForUsdMinor(100, $quote));
    }

    public function test_manual_deposit_internal_charge_and_cashout_reconcile_to_one_iet_wallet(): void
    {
        $userActor = Actor::factory()->create();
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $deposit = app(CreateIetExchangeRequest::class)->execute(
            $userActor->user,
            IetExchangeDirection::Deposit,
            350,
            'DEPOSIT-350',
        );

        $this->assertSame(3_500_000, $deposit->iet_amount);
        $this->assertSame(IetExchangeStatus::Pending, $deposit->status);

        app(ReviewIetExchangeRequest::class)->confirm($deposit, $admin->user);

        $wallet = app(EnsureIetWallet::class)->execute($userActor->user);
        $summary = app(AccountingSummary::class);

        $this->assertSame(
            3_500_000,
            $summary->accountBalanceMinor($wallet['wallet']),
        );
        $this->assertSame(
            JournalEntryKind::ExchangeDeposit,
            $deposit->fresh()->journalEntry->kind,
        );

        $sourceUuid = (string) Str::uuid();

        $charge = app(ChargeIetForUsdFlow::class)->execute(
            $userActor->user,
            100,
            'feature_action',
            $sourceUuid,
            'One-dollar internal flow',
        );

        $this->assertSame(1_000_000, $charge->iet_amount);
        $this->assertSame(
            2_500_000,
            $summary->accountBalanceMinor($wallet['wallet']->fresh()),
        );

        $retry = app(ChargeIetForUsdFlow::class)->execute(
            $userActor->user,
            100,
            'feature_action',
            $sourceUuid,
            'Retry',
        );

        $this->assertSame($charge->id, $retry->id);
        $this->assertDatabaseCount('iet_internal_charges', 1);

        $cashout = app(CreateIetExchangeRequest::class)->execute(
            $userActor->user,
            IetExchangeDirection::Cashout,
            100,
            'CASHOUT-100',
        );

        app(ReviewIetExchangeRequest::class)->confirm($cashout, $admin->user);

        $this->assertSame(
            1_500_000,
            $summary->accountBalanceMinor($wallet['wallet']->fresh()),
        );
        $this->assertSame(IetExchangeStatus::Confirmed, $cashout->fresh()->status);
    }

    public function test_quote_history_is_immutable_and_old_exchange_keeps_its_snapshot(): void
    {
        $userActor = Actor::factory()->create();
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $old = app(CreateIetExchangeRequest::class)->execute(
            $userActor->user,
            IetExchangeDirection::Deposit,
            100,
        );

        $newQuote = app(PublishIetValuationQuote::class)->execute(
            $admin->user,
            '0.0000010001',
            'Small audited valuation update.',
            ['successful_flow_count' => 12],
        );

        $new = app(CreateIetExchangeRequest::class)->execute(
            $userActor->user,
            IetExchangeDirection::Deposit,
            100,
        );

        $this->assertNotSame($old->valuation_quote_id, $new->valuation_quote_id);
        $this->assertSame('0.0000010000', (string) $old->fresh()->valuationQuote->usd_per_iet);
        $this->assertSame('0.0000010001', (string) $newQuote->usd_per_iet);
        $this->assertLessThan($old->iet_amount, $new->iet_amount);
        $this->assertDatabaseCount('iet_valuation_quotes', 2);
    }

    public function test_direct_internal_charge_rejects_an_underfunded_wallet(): void
    {
        $actor = Actor::factory()->create();

        try {
            app(ChargeIetForUsdFlow::class)->execute(
                $actor->user,
                350,
                'premium_action',
                (string) Str::uuid(),
            );

            $this->fail('Underfunded internal IET flow was charged.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('iet_internal_charges', 0);
    }

    public function test_usd_priced_obligation_snapshots_quote_and_iet_confirmation_moves_both_wallets_atomically(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            [$alice, $bob, , $commitment] = $this->activePaidWorkCommitment();

            $admin = Actor::factory()->create();
            PlatformAccessGrant::factory()->create([
                'user_id' => $admin->user->id,
                'role' => PlatformRole::Superadmin,
            ]);

            $funding = app(CreateIetExchangeRequest::class)->execute(
                $alice->user,
                IetExchangeDirection::Deposit,
                500,
            );
            app(ReviewIetExchangeRequest::class)->confirm($funding, $admin->user);

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

            $priced = app(RecognizeUsdPricedFulfillmentInIet::class)->execute(
                $fulfillment->fresh(),
                $alice->user,
                350,
                description: 'USD-priced work settled internally in IET.',
            );

            $this->assertSame(350, $priced->reference_usd_amount_minor);
            $this->assertSame(3_500_000, $priced->iet_amount);
            $this->assertSame('IET', $priced->obligation->monetaryUnit->code);

            $settlement = app(ProposeSettlement::class)->execute(
                $priced->obligation,
                $alice->user,
                3_500_000,
                CarbonImmutable::now()->subMinute(),
                'IET internal settlement',
            );

            app(RespondToSettlement::class)->confirm($settlement, $bob->user);

            $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);

            $summary = app(AccountingSummary::class);
            $aliceWallet = app(EnsureIetWallet::class)->execute($alice->user);
            $bobWallet = app(EnsureIetWallet::class)->execute($bob->user);

            $this->assertSame(
                1_500_000,
                $summary->accountBalanceMinor($aliceWallet['wallet']),
            );
            $this->assertSame(
                3_500_000,
                $summary->accountBalanceMinor($bobWallet['wallet']),
            );

            $this->assertSame(
                2,
                $priced->obligation->fresh()->events()
                    ->where('event_type', 'settlement_accounting_posted')
                    ->count(),
            );

            $this->assertDatabaseCount('iet_priced_financial_obligations', 1);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array{0: Actor, 1: Actor, 2: Contract, 3: Commitment}
     */
    private function activePaidWorkCommitment(): array
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'IET-priced work',
            [['actor' => $bob, 'role' => 'worker']],
            'Bob performs accepted work. Alice settles accepted work using IET.',
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
            'One accepted IET-priced work unit',
            1,
            'unit',
        );

        return [$alice, $bob, $contract->fresh(), $commitment];
    }
}
