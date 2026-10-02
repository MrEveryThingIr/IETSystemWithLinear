<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Exchange\EnsureIetWallet;
use App\Actions\Exchange\PublishIetValuationQuote;
use App\Actions\Exchange\PublishManualMarketQuote;
use App\Actions\Exchange\RegisterEconomicInstrument;
use App\Actions\Financial\ProposeReferencedContractSettlementBatch;
use App\Actions\Financial\RespondToContractSettlementBatch;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\EconomicInstrumentKind;
use App\FulfillmentReviewDecision;
use App\Models\Account;
use App\Models\Actor;
use App\Models\EconomicInstrument;
use App\Models\FinancialObligation;
use App\Models\JournalEntry;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\Support\AccountingSummary;
use App\Support\ContractFinancialSummary;
use App\Support\IetPosition;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWorkIetReferenceSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_reserved_workdays_toman_price_and_partial_cash_payment_keep_both_books_exact(): void
    {
        $contractedAt = CarbonImmutable::now('UTC')->addDay()->setTime(7, 0);
        $dayX = $contractedAt->addDay()->startOfDay();
        $dayY = $dayX->addDays(2);
        $dayZ = $dayX->addDays(4);

        CarbonImmutable::setTestNow($contractedAt);

        try {
            $father = Actor::factory()->create();
            $worker = Actor::factory()->create();

            $father->user->update(['timezone' => 'UTC']);
            $worker->user->update(['timezone' => 'UTC']);

            PlatformAccessGrant::factory()->for($father->user)->create([
                'role' => PlatformRole::Superadmin,
                'reason' => 'Release acceptance FX administrator.',
            ]);

            $ietQuote = app(PublishIetValuationQuote::class)->execute(
                $father->user,
                '0.010000000000000000',
                'Release acceptance quote: one IET equals one US cent.',
            );

            $irt = app(RegisterEconomicInstrument::class)->execute(
                $father->user,
                'IRT',
                'Iranian Toman',
                EconomicInstrumentKind::FiatCurrency,
            );
            $usd = EconomicInstrument::query()->where('code', 'USD')->sole();

            $irtQuote = app(PublishManualMarketQuote::class)->execute(
                $father->user,
                $irt->uuid,
                $usd->uuid,
                '0.00001',
                'Release acceptance quote: 100,000 toman equals one USD.',
                'DAILY-WORK-IRT-USD',
            );

            $contract = app(CreateDirectContract::class)->execute(
                $father->user,
                'Three future workdays',
                [['actor' => $worker, 'role' => 'worker']],
                'Worker is reserved for three selected days from 08:00 to 17:00. Reference wage is 1,500,000 toman per accepted day and internal obligations are IET.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'employer',
                serviceTerms: [
                    'employer_username' => $father->user->username,
                    'worker_username' => $worker->user->username,
                    'service_title' => 'Daily work with father',
                    'service_kind' => 'work',
                    'total_quantity' => '3',
                    'quantity_per_occurrence' => '1',
                    'unit' => 'day',
                    'unit_rate' => '',
                    'monetary_unit_code' => 'IET',
                    'reference_unit_rate' => '1500000',
                    'reference_monetary_unit_code' => 'IRT',
                    'settlement_cycle' => 'per_fulfillment',
                    'payment_due_days' => 0,
                    'auto_create_plan' => true,
                    'auto_recognize_obligation' => true,
                    'plan_frequency' => 'selected_dates',
                    'plan_starts_on' => $dayX->format('Y-m-d'),
                    'plan_start_time' => '08:00',
                    'plan_duration_minutes' => 540,
                    'plan_interval' => 1,
                    'plan_weekdays' => [],
                    'plan_selected_dates' => [
                        $dayX->format('Y-m-d'),
                        $dayY->format('Y-m-d'),
                        $dayZ->format('Y-m-d'),
                    ],
                    'plan_ends_on' => $dayZ->format('Y-m-d'),
                    'plan_occurrence_limit' => 3,
                    'window_before_minutes' => 0,
                    'window_after_minutes' => 0,
                    'reminder_offsets' => [60],
                    'timezone' => 'UTC',
                ],
            );

            $version = $contract->versions()
                ->with([
                    'serviceTerm.referenceMonetaryUnit',
                    'serviceTerm.referenceMarketQuote',
                    'serviceTerm.ietValuationQuote',
                ])
                ->sole();

            $terms = $version->serviceTerm;
            $this->assertNotNull($terms);
            $this->assertSame('IET', $terms->monetaryUnit->code);
            $this->assertSame(1_500, $terms->unit_rate_minor);
            $this->assertSame('IRT', $terms->referenceMonetaryUnit?->code);
            $this->assertSame(1_500_000, $terms->reference_unit_rate_minor);
            $this->assertSame(1_500, $terms->reference_usd_amount_minor);
            $this->assertSame($irtQuote->id, $terms->reference_market_quote_id);
            $this->assertSame($ietQuote->id, $terms->iet_valuation_quote_id);

            app(AcceptContractVersion::class)->execute($version, $worker->user);

            $version = $contract->fresh()->activeVersionRecord();
            $this->assertNotNull($version);
            $version->loadMissing('serviceTerm.commitment.planBinding.plan.occurrences');

            $commitment = $version->serviceTerm?->commitment;
            $plan = $commitment?->planBinding?->plan;
            $this->assertNotNull($commitment);
            $this->assertNotNull($plan);

            $occurrences = $plan->occurrences()->orderBy('scheduled_start_at')->get();
            $this->assertCount(3, $occurrences);

            $this->assertSame([
                $dayX->setTime(8, 0)->format('Y-m-d H:i'),
                $dayY->setTime(8, 0)->format('Y-m-d H:i'),
                $dayZ->setTime(8, 0)->format('Y-m-d H:i'),
            ], $occurrences->map(
                fn ($occurrence): string => $occurrence->scheduled_start_at
                    ->setTimezone('UTC')
                    ->format('Y-m-d H:i'),
            )->all());

            foreach ($occurrences as $occurrence) {
                $start = $occurrence->scheduled_start_at->setTimezone('UTC');
                $end = $occurrence->scheduled_end_at->setTimezone('UTC');

                CarbonImmutable::setTestNow($start);
                $occurrence = app(TransitionPlanOccurrence::class)->start(
                    $occurrence->fresh(),
                    $worker->user,
                );

                CarbonImmutable::setTestNow($end);
                $occurrence = app(TransitionPlanOccurrence::class)->complete(
                    $occurrence,
                    $worker->user,
                );

                $fulfillment = app(SubmitFulfillment::class)->execute(
                    $commitment,
                    $worker->user,
                    '1',
                    occurrence: $occurrence,
                    notes: 'Completed reserved daily work.',
                );

                app(ReviewFulfillment::class)->execute(
                    $fulfillment,
                    $father->user,
                    FulfillmentReviewDecision::Accepted,
                    'Accepted completed workday.',
                );
            }

            $obligations = FinancialObligation::query()
                ->with('monetaryUnit')
                ->orderBy('id')
                ->get();

            $this->assertCount(3, $obligations);
            $this->assertSame([1_500, 1_500, 1_500], $obligations->pluck('amount_minor')->all());
            $this->assertTrue($obligations->every(
                fn (FinancialObligation $obligation): bool => $obligation->monetaryUnit->code === 'IET',
            ));

            // Accepted work immediately posts both parties' receivable/payable bookkeeping.
            $this->assertDatabaseCount('journal_entries', 6);
            $this->assertSame(3, JournalEntry::query()->where('acting_user_id', $father->user->id)->count());
            $this->assertSame(3, JournalEntry::query()->where('acting_user_id', $worker->user->id)->count());

            $ietUnit = $obligations->first()->monetaryUnit;
            $summary = app(ContractFinancialSummary::class);

            $beforeCash = $summary->forContractUnit($contract, $ietUnit);
            $this->assertSame(4_500, $beforeCash['earned_minor']);
            $this->assertSame(4_500, $beforeCash['outstanding_minor']);
            $this->assertSame(0, $beforeCash['paid_minor']);

            CarbonImmutable::setTestNow($dayZ->setTime(18, 0));

            // Worker records: "I received 1,000,000 toman cash".
            $batch = app(ProposeReferencedContractSettlementBatch::class)->execute(
                $contract,
                $worker->user,
                'IRT',
                1_000_000,
                CarbonImmutable::now()->subMinute(),
                'FATHER-CASH-1',
                'Received 1,000,000 toman in cash.',
                'received',
            );

            $this->assertSame(1_000_000, $batch->reference_amount_minor);
            $this->assertSame(1_000, $batch->reference_usd_amount_minor);
            $this->assertSame(1_000, $batch->amount_minor);
            $this->assertSame('IRT', $batch->referenceMonetaryUnit?->code);
            $this->assertSame($irtQuote->id, $batch->reference_market_quote_id);
            $this->assertSame($ietQuote->id, $batch->iet_valuation_quote_id);
            $this->assertSame('external_cash', $batch->method);

            app(RespondToContractSettlementBatch::class)->confirm(
                $batch,
                $father->user,
            );

            $afterCash = $summary->forContractUnit($contract, $ietUnit);
            $this->assertSame(4_500, $afterCash['earned_minor']);
            $this->assertSame(1_000, $afterCash['paid_minor']);
            $this->assertSame(3_500, $afterCash['outstanding_minor']);

            $daily = $summary->dailyForContractUnit($contract, $ietUnit, 'UTC');
            $this->assertCount(3, $daily);
            $oldest = collect($daily)->firstWhere('date', $dayX->format('Y-m-d'));
            $this->assertSame(1_000, $oldest['paid_minor']);
            $this->assertSame(500, $oldest['outstanding_minor']);

            // External Toman cash updates both books, but does not fabricate an IET wallet transfer.
            $this->assertDatabaseCount('journal_entries', 8);
            $this->assertSame(4, JournalEntry::query()->where('acting_user_id', $father->user->id)->count());
            $this->assertSame(4, JournalEntry::query()->where('acting_user_id', $worker->user->id)->count());

            $fatherWallet = app(EnsureIetWallet::class)->execute($father->user);
            $workerWallet = app(EnsureIetWallet::class)->execute($worker->user);
            $accounting = app(AccountingSummary::class);

            $this->assertSame(0, $accounting->accountBalanceMinor($fatherWallet['wallet']));
            $this->assertSame(0, $accounting->accountBalanceMinor($workerWallet['wallet']));

            $fatherExternalCash = $fatherWallet['ledger']->accounts()
                ->where('system_key', 'external_cash_equivalent')
                ->sole();
            $workerExternalCash = $workerWallet['ledger']->accounts()
                ->where('system_key', 'external_cash_equivalent')
                ->sole();

            $this->assertInstanceOf(Account::class, $fatherExternalCash);
            $this->assertInstanceOf(Account::class, $workerExternalCash);
            $this->assertSame(-1_000, $accounting->accountBalanceMinor($fatherExternalCash));
            $this->assertSame(1_000, $accounting->accountBalanceMinor($workerExternalCash));

            $fatherPosition = app(IetPosition::class)->forUser($father->user);
            $workerPosition = app(IetPosition::class)->forUser($worker->user);

            $this->assertSame(-3_500, $fatherPosition['net_position_minor']);
            $this->assertSame(3_500, $workerPosition['net_position_minor']);
            $this->assertSame(0, $fatherPosition['wallet_minor']);
            $this->assertSame(0, $workerPosition['wallet_minor']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
