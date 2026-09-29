<?php

namespace Tests\Feature;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\PostJournalEntry;
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

    public function test_initial_quote_prices_literal_point_zero_zero_zero_zero_zero_one_percent_without_float_math(): void
    {
        $pricing = app(IetPricing::class);
        $quote = $pricing->currentQuote();

        $this->assertSame('0.000000010000000000', (string) $quote->usd_per_iet);
        $this->assertSame('0.000001', $pricing->percentOfUsd($quote));
        $this->assertSame(350_000_000, $pricing->ietForUsdMinor(350, $quote));
        $this->assertSame(100_000_000, $pricing->ietForUsdMinor(100, $quote));
    }

    public function test_quote_precision_supports_eighteen_decimal_places_without_float_math(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $quote = app(PublishIetValuationQuote::class)->execute(
            $admin->user,
            '0.000000010000000001',
            'Precision proof.',
            ['successful_flow_count' => 1],
        );

        $pricing = app(IetPricing::class);

        $this->assertSame('0.000000010000000001', (string) $quote->usd_per_iet);
        $this->assertSame('0.0000010000000001', $pricing->percentOfUsd($quote));
        $this->assertSame(100_000_000, $pricing->ietForUsdMinor(100, $quote));
    }

    public function test_quote_publication_requires_an_auditable_rationale(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        try {
            app(PublishIetValuationQuote::class)->execute(
                $admin->user,
                '0.000000010100000000',
                '',
                ['successful_flow_count' => 12],
            );

            $this->fail('A valuation quote was published without a rationale.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('iet_valuation_quotes', 1);
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

        $this->assertSame(350_000_000, $deposit->iet_amount);
        $this->assertSame(IetExchangeStatus::Pending, $deposit->status);

        app(ReviewIetExchangeRequest::class)->confirm($deposit, $admin->user);

        $wallet = app(EnsureIetWallet::class)->execute($userActor->user);
        $summary = app(AccountingSummary::class);

        $this->assertSame(
            350_000_000,
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

        $this->assertSame(100_000_000, $charge->iet_amount);
        $this->assertSame(
            250_000_000,
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
            150_000_000,
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
            '0.000000010100000000',
            'Small audited valuation update.',
            ['successful_flow_count' => 12],
        );

        $new = app(CreateIetExchangeRequest::class)->execute(
            $userActor->user,
            IetExchangeDirection::Deposit,
            100,
        );

        $this->assertNotSame($old->valuation_quote_id, $new->valuation_quote_id);
        $this->assertSame('0.000000010000000000', (string) $old->fresh()->valuationQuote->usd_per_iet);
        $this->assertSame('0.000000010100000000', (string) $newQuote->usd_per_iet);
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
            $this->assertSame(350_000_000, $priced->iet_amount);
            $this->assertSame('IET', $priced->obligation->monetaryUnit->code);

            $settlement = app(ProposeSettlement::class)->execute(
                $priced->obligation,
                $alice->user,
                350_000_000,
                CarbonImmutable::now()->subMinute(),
                'IET internal settlement',
            );

            app(RespondToSettlement::class)->confirm($settlement, $bob->user);

            $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);

            $summary = app(AccountingSummary::class);
            $aliceWallet = app(EnsureIetWallet::class)->execute($alice->user);
            $bobWallet = app(EnsureIetWallet::class)->execute($bob->user);

            $this->assertSame(
                150_000_000,
                $summary->accountBalanceMinor($aliceWallet['wallet']),
            );
            $this->assertSame(
                350_000_000,
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

    public function test_pending_cashout_reserves_iet_against_new_internal_charges(): void
    {
        $actor = Actor::factory()->create();
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $deposit = app(CreateIetExchangeRequest::class)->execute(
            $actor->user,
            IetExchangeDirection::Deposit,
            350,
        );
        app(ReviewIetExchangeRequest::class)->confirm($deposit, $admin->user);

        app(CreateIetExchangeRequest::class)->execute(
            $actor->user,
            IetExchangeDirection::Cashout,
            300,
        );

        try {
            app(ChargeIetForUsdFlow::class)->execute(
                $actor->user,
                100,
                'reserved-balance-check',
                (string) Str::uuid(),
            );

            $this->fail('Pending cash-out reservation was ignored by an internal charge.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $wallet = app(EnsureIetWallet::class)->execute($actor->user);

        $this->assertSame(
            350_000_000,
            app(AccountingSummary::class)->accountBalanceMinor($wallet['wallet']),
        );
    }

    public function test_generic_accounting_cannot_mint_or_mutate_iet_wallet(): void
    {
        $actor = Actor::factory()->create();
        $side = app(EnsureIetWallet::class)->execute($actor->user);

        $income = app(CreateLedgerAccount::class)->execute(
            $side['ledger'],
            $actor->user,
            'Attempted manual income',
            AccountType::Income,
        );

        try {
            app(PostJournalEntry::class)->execute(
                $side['ledger'],
                $actor->user,
                JournalEntryKind::Income,
                now()->toDateString(),
                'Attempted manual IET mint',
                [
                    ['account' => $side['wallet'], 'debit_minor' => 10],
                    ['account' => $income, 'credit_minor' => 10],
                ],
            );

            $this->fail('Generic accounting posted directly into the IET wallet.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('journal_entries', 0);
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
