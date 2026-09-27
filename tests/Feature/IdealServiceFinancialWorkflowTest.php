<?php

namespace Tests\Feature;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Contracts\ProposeContractAmendment;
use App\Actions\Financial\PostFinancialObligationAccounting;
use App\Actions\Financial\PostSettlementAccounting;
use App\Actions\Financial\ProposeContractSettlementBatch;
use App\Actions\Financial\RespondToContractSettlementBatch;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\ContractEventType;
use App\FulfillmentReviewDecision;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\ContractSettlementBatch;
use App\Models\FinancialObligation;
use App\Models\Fulfillment;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\SettlementStatus;
use App\Support\ContractFinancialSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdealServiceFinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_service_contract_drives_work_earned_value_periodic_cash_settlement_and_accounting(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 07:30:00 UTC');

        try {
            $employer = Actor::factory()->create();
            $worker = Actor::factory()->create();

            $employer->user->update(['timezone' => 'UTC']);
            $worker->user->update(['timezone' => 'UTC']);

            $contract = app(CreateDirectContract::class)->execute(
                $employer->user,
                'Three service days',
                [['actor' => $worker, 'role' => 'worker']],
                'Worker provides three accepted service days. Employer pays EUR 100.00 per accepted day.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'employer',
                serviceTerms: [
                    'employer_username' => $employer->user->username,
                    'worker_username' => $worker->user->username,
                    'service_title' => 'On-site service day',
                    'service_kind' => 'service',
                    'total_quantity' => '3',
                    'quantity_per_occurrence' => '1',
                    'unit' => 'day',
                    'unit_rate' => '100.00',
                    'monetary_unit_code' => 'EUR',
                    'settlement_cycle' => 'weekly',
                    'payment_due_days' => 0,
                    'auto_create_plan' => true,
                    'auto_recognize_obligation' => true,
                    'plan_frequency' => 'daily',
                    'plan_starts_on' => '2026-09-27',
                    'plan_start_time' => '08:00',
                    'plan_duration_minutes' => 60,
                    'plan_interval' => 1,
                    'plan_weekdays' => [],
                    'plan_selected_dates' => [],
                    'plan_ends_on' => null,
                    'plan_occurrence_limit' => null,
                    'window_before_minutes' => 0,
                    'window_after_minutes' => 30,
                    'reminder_offsets' => [15],
                    'timezone' => 'UTC',
                ],
            );

            $version = $contract->versions()
                ->with(['serviceTerm.monetaryUnit', 'parties.acceptance'])
                ->sole();

            $this->assertNotNull($version->serviceTerm);
            $this->assertSame(10_000, $version->serviceTerm->unit_rate_minor);
            $this->assertDatabaseCount('contract_acceptances', 1);
            $this->assertDatabaseCount('commitments', 0);
            $this->assertDatabaseCount('plans', 0);

            $versionEvents = $contract->events()
                ->where('contract_version_id', $version->id)
                ->orderBy('id')
                ->pluck('event_type')
                ->all();

            $this->assertSame([
                ContractEventType::VersionProposed,
                ContractEventType::ServiceTermsConfigured,
                ContractEventType::PartyAccepted,
            ], $versionEvents);

            app(AcceptContractVersion::class)->execute($version, $worker->user);

            $contract = $contract->fresh([
                'contextBinding.context',
                'versions.serviceTerm.commitment.planBinding.plan.occurrences',
            ]);
            $version = $contract->activeVersionRecord();
            $this->assertNotNull($version);
            $version->loadMissing('serviceTerm.commitment.planBinding.plan.occurrences');

            $serviceTerms = $version->serviceTerm;
            $this->assertNotNull($serviceTerms);

            $commitment = $serviceTerms->commitment;
            $this->assertInstanceOf(Commitment::class, $commitment);
            $this->assertSame($serviceTerms->id, $commitment->serviceTerm?->id);
            $this->assertTrue((bool) $commitment->serviceTerm?->auto_recognize_obligation);
            $this->assertSame($worker->id, $commitment->obligor_actor_id);
            $this->assertSame($employer->id, $commitment->beneficiary_actor_id);
            $this->assertSame('3.0000', $commitment->quantity);
            $this->assertSame('day', $commitment->unit);

            $plan = $commitment->planBinding?->plan;
            $this->assertInstanceOf(Plan::class, $plan);
            $this->assertSame('commitment', $plan->origin_type);
            $this->assertSame($commitment->uuid, $plan->origin_uuid);
            $this->assertSame(3, $plan->occurrences()->count());

            $context = $contract->contextBinding?->context;
            $this->assertNotNull($context);
            $this->assertTrue($employer->user->can('interactContent', $context));
            $this->assertTrue($worker->user->can('interactContent', $context));

            $this->performAndAcceptOccurrence(
                $commitment,
                $plan->occurrences()->orderBy('scheduled_start_at')->firstOrFail(),
                $worker,
                $employer,
                '2026-09-27 08:00:00 UTC',
                '2026-09-27 09:00:00 UTC',
            );

            $firstObligation = FinancialObligation::query()->orderBy('id')->firstOrFail();
            $this->assertSame(10_000, $firstObligation->amount_minor);
            $this->assertSame($employer->id, $firstObligation->debtor_actor_id);
            $this->assertSame($worker->id, $firstObligation->creditor_actor_id);

            $this->performAndAcceptOccurrence(
                $commitment,
                $plan->occurrences()->orderBy('scheduled_start_at')->skip(1)->firstOrFail(),
                $worker,
                $employer,
                '2026-09-28 08:00:00 UTC',
                '2026-09-28 09:00:00 UTC',
            );

            $this->assertDatabaseCount('financial_obligations', 2);
            $unit = app(EnsureMonetaryUnit::class)->execute('EUR');
            $summaryService = app(ContractFinancialSummary::class);

            $beforePayment = $summaryService->forContractUnit($contract, $unit);
            $this->assertSame(20_000, $beforePayment['earned_minor']);
            $this->assertSame(0, $beforePayment['pending_minor']);
            $this->assertSame(0, $beforePayment['paid_minor']);
            $this->assertSame(20_000, $beforePayment['outstanding_minor']);
            $this->assertSame(20_000, $beforePayment['available_minor']);

            CarbonImmutable::setTestNow('2026-09-28 10:00:00 UTC');

            $batch = app(ProposeContractSettlementBatch::class)->execute(
                $contract,
                $employer->user,
                $unit,
                15_000,
                CarbonImmutable::now()->subMinute(),
                'cash',
                'WEEK-1',
                'Partial weekly cash settlement.',
            );

            $this->assertInstanceOf(ContractSettlementBatch::class, $batch);
            $this->assertSame(15_000, $batch->amount_minor);
            $this->assertSame(2, $batch->settlements()->count());
            $this->assertSame(10_000, $batch->settlements()->orderBy('id')->firstOrFail()->amount_minor);
            $this->assertSame(5_000, $batch->settlements()->orderBy('id')->skip(1)->firstOrFail()->amount_minor);

            $pending = $summaryService->forContractUnit($contract, $unit);
            $this->assertSame(20_000, $pending['earned_minor']);
            $this->assertSame(15_000, $pending['pending_minor']);
            $this->assertSame(0, $pending['paid_minor']);
            $this->assertSame(20_000, $pending['outstanding_minor']);
            $this->assertSame(5_000, $pending['available_minor']);

            app(RespondToContractSettlementBatch::class)->confirm($batch, $worker->user);

            $this->assertTrue(
                $batch->settlements()->get()->every(
                    fn ($settlement): bool => $settlement->status === SettlementStatus::Confirmed,
                ),
            );

            $confirmed = $summaryService->forContractUnit($contract, $unit);
            $this->assertSame(20_000, $confirmed['earned_minor']);
            $this->assertSame(0, $confirmed['pending_minor']);
            $this->assertSame(15_000, $confirmed['paid_minor']);
            $this->assertSame(5_000, $confirmed['outstanding_minor']);
            $this->assertSame(5_000, $confirmed['available_minor']);

            $receivedBatch = app(ProposeContractSettlementBatch::class)->execute(
                $contract,
                $worker->user,
                $unit,
                5_000,
                CarbonImmutable::now()->subSeconds(30),
                'cash',
                'WEEK-1-RECEIPT',
                'Worker records the remaining cash as received.',
                perspective: 'received',
            );

            $this->assertSame($worker->id, $receivedBatch->proposed_by_actor_id);
            $this->assertSame($employer->id, $receivedBatch->debtor_actor_id);
            $this->assertSame($worker->id, $receivedBatch->creditor_actor_id);

            app(RespondToContractSettlementBatch::class)->confirm(
                $receivedBatch,
                $employer->user,
            );

            $fullySettled = $summaryService->forContractUnit($contract, $unit);
            $this->assertSame(20_000, $fullySettled['earned_minor']);
            $this->assertSame(0, $fullySettled['pending_minor']);
            $this->assertSame(20_000, $fullySettled['paid_minor']);
            $this->assertSame(0, $fullySettled['outstanding_minor']);
            $this->assertSame(0, $fullySettled['available_minor']);

            $daily = $summaryService->dailyForContractUnit($contract, $unit, 'UTC');
            $this->assertCount(2, $daily);
            $this->assertSame(['2026-09-28', '2026-09-27'], array_column($daily, 'date'));

            $this->assertDatabaseCount('journal_entries', 0);

            $firstObligation = FinancialObligation::query()->orderBy('id')->firstOrFail();
            $firstSettlement = $batch->settlements()->orderBy('id')->firstOrFail();

            app(PostFinancialObligationAccounting::class)->execute($firstObligation, $employer->user);
            app(PostFinancialObligationAccounting::class)->execute($firstObligation, $worker->user);
            app(PostSettlementAccounting::class)->execute($firstSettlement->fresh(), $employer->user);
            app(PostSettlementAccounting::class)->execute($firstSettlement->fresh(), $worker->user);

            $this->assertDatabaseCount('journal_entries', 4);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_structured_service_contract_can_keep_planning_manual_while_still_generating_commitment(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 07:30:00 UTC');

        try {
            $employer = Actor::factory()->create();
            $worker = Actor::factory()->create();

            $contract = app(CreateDirectContract::class)->execute(
                $employer->user,
                'Custom-planned service',
                [['actor' => $worker, 'role' => 'worker']],
                'Worker performs two service units under a manually arranged work plan.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'employer',
                serviceTerms: [
                    'employer_username' => $employer->user->username,
                    'worker_username' => $worker->user->username,
                    'service_title' => 'Custom-planned service unit',
                    'service_kind' => 'service',
                    'total_quantity' => '2',
                    'quantity_per_occurrence' => '1',
                    'unit' => 'unit',
                    'unit_rate' => '25.00',
                    'monetary_unit_code' => 'EUR',
                    'settlement_cycle' => 'per_fulfillment',
                    'payment_due_days' => 0,
                    'auto_create_plan' => false,
                    'auto_recognize_obligation' => true,
                    'plan_frequency' => 'daily',
                    'plan_starts_on' => '2026-09-27',
                    'plan_start_time' => '08:00',
                    'plan_duration_minutes' => 60,
                    'plan_interval' => 1,
                    'plan_weekdays' => [],
                    'plan_selected_dates' => [],
                    'plan_ends_on' => null,
                    'plan_occurrence_limit' => null,
                    'window_before_minutes' => 0,
                    'window_after_minutes' => 0,
                    'reminder_offsets' => [],
                    'timezone' => 'UTC',
                ],
            );

            app(AcceptContractVersion::class)->execute(
                $contract->versions()->sole(),
                $worker->user,
            );

            $version = $contract->fresh()->activeVersionRecord();
            $this->assertNotNull($version);
            $version->loadMissing('serviceTerm.commitment');

            $this->assertInstanceOf(Commitment::class, $version->serviceTerm?->commitment);
            $this->assertDatabaseCount('commitments', 1);
            $this->assertDatabaseCount('plans', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_structured_service_contract_does_not_allow_prose_only_amendment_of_economics(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 07:30:00 UTC');

        try {
            $employer = Actor::factory()->create();
            $worker = Actor::factory()->create();

            $contract = app(CreateDirectContract::class)->execute(
                $employer->user,
                'Fixed service terms',
                [['actor' => $worker, 'role' => 'worker']],
                'Worker provides one service unit.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'employer',
                serviceTerms: [
                    'employer_username' => $employer->user->username,
                    'worker_username' => $worker->user->username,
                    'service_title' => 'One service unit',
                    'service_kind' => 'service',
                    'total_quantity' => '1',
                    'quantity_per_occurrence' => '1',
                    'unit' => 'service',
                    'unit_rate' => '50.00',
                    'monetary_unit_code' => 'EUR',
                    'settlement_cycle' => 'per_fulfillment',
                    'payment_due_days' => 0,
                    'auto_create_plan' => true,
                    'auto_recognize_obligation' => true,
                    'plan_frequency' => 'once',
                    'plan_starts_on' => '2026-09-27',
                    'plan_start_time' => '08:00',
                    'plan_duration_minutes' => 60,
                    'plan_interval' => 1,
                    'plan_weekdays' => [],
                    'plan_selected_dates' => [],
                    'plan_ends_on' => null,
                    'plan_occurrence_limit' => 1,
                    'window_before_minutes' => 0,
                    'window_after_minutes' => 0,
                    'reminder_offsets' => [],
                    'timezone' => 'UTC',
                ],
            );

            app(AcceptContractVersion::class)->execute(
                $contract->versions()->sole(),
                $worker->user,
            );

            try {
                app(ProposeContractAmendment::class)->execute(
                    $contract->fresh(),
                    $employer->user,
                    'Changed economics',
                    'Change the rate without structured terms.',
                    CarbonImmutable::now()->addDay(),
                    'UTC',
                );

                $this->fail('Structured service economics were changed by a prose-only amendment.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }

            $this->assertDatabaseCount('contract_versions', 1);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function performAndAcceptOccurrence(
        Commitment $commitment,
        PlanOccurrence $occurrence,
        Actor $worker,
        Actor $employer,
        string $startAt,
        string $endAt,
    ): Fulfillment {
        CarbonImmutable::setTestNow($startAt);
        $occurrence = app(TransitionPlanOccurrence::class)->start($occurrence, $worker->user);

        CarbonImmutable::setTestNow($endAt);
        $occurrence = app(TransitionPlanOccurrence::class)->complete($occurrence, $worker->user);

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $worker->user,
            '1',
            occurrence: $occurrence,
            notes: 'Completed contracted service unit.',
        );

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $employer->user,
            FulfillmentReviewDecision::Accepted,
        );

        return $fulfillment->fresh(['financialObligation']);
    }
}
