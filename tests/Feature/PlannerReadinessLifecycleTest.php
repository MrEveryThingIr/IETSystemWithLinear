<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ConfigurePlanReadiness;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\RecordPlanOccurrenceExpense;
use App\Actions\Planner\SetPlanOccurrencePrerequisite;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanStatus;
use App\Support\MoneyAmount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlannerReadinessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_occurrence_cannot_start_or_complete_and_window_boundaries_are_authoritative(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 10:00:00 UTC');

        try {
            [$bob, $plan, $occurrence] = $this->oneTimeOccurrence(
                startsOn: '2026-09-27',
                startTime: '12:00',
                durationMinutes: 60,
                beforeMinutes: 15,
                afterMinutes: 30,
            );

            $this->assertSame('upcoming', $occurrence->executionPhase());

            $this->assertHttp422(
                fn () => app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user),
            );
            $this->assertHttp422(
                fn () => app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user),
            );

            $this->assertSame(PlanOccurrenceStatus::Scheduled, $occurrence->fresh()->status);
            $this->assertDatabaseCount('plan_occurrence_events', 0);

            CarbonImmutable::setTestNow('2026-09-27 11:45:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);

            $this->assertSame(PlanOccurrenceStatus::InProgress, $occurrence->status);
            $this->assertSame('2026-09-27 11:45:00', $occurrence->actual_start_at?->utc()->format('Y-m-d H:i:s'));

            CarbonImmutable::setTestNow('2026-09-27 11:50:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user);

            $this->assertSame(PlanOccurrenceStatus::Completed, $occurrence->status);
            $this->assertSame('2026-09-27 11:50:00', $occurrence->actual_end_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertNotNull($occurrence->completed_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_unstarted_occurrence_cannot_start_after_late_start_window_closes(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:31:00 UTC');

        try {
            [$bob, , $occurrence] = $this->oneTimeOccurrence(
                startsOn: '2026-09-27',
                startTime: '12:00',
                durationMinutes: 60,
                beforeMinutes: 15,
                afterMinutes: 30,
            );

            $this->assertSame('2026-09-27 11:45:00', $occurrence->window_start_at->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-27 12:30:00', $occurrence->window_end_at->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('missed', $occurrence->executionPhase());

            $this->assertHttp422(
                fn () => app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user),
            );

            $this->assertSame(PlanOccurrenceStatus::Scheduled, $occurrence->fresh()->status);
            $this->assertDatabaseCount('plan_occurrence_events', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_occurrence_moves_from_ready_to_late_after_nominal_start(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:00:00 UTC');

        try {
            [, , $occurrence] = $this->oneTimeOccurrence(
                startsOn: '2026-09-27',
                startTime: '12:00',
                durationMinutes: 60,
                beforeMinutes: 15,
                afterMinutes: 30,
            );

            $this->assertSame('ready', $occurrence->executionPhase());

            CarbonImmutable::setTestNow('2026-09-27 12:15:00 UTC');
            $this->assertSame('late', $occurrence->executionPhase());

            CarbonImmutable::setTestNow('2026-09-27 12:30:00 UTC');
            $this->assertSame('late', $occurrence->executionPhase());

            CarbonImmutable::setTestNow('2026-09-27 12:30:01 UTC');
            $this->assertSame('missed', $occurrence->executionPhase());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_required_prerequisites_are_checked_per_occurrence_before_start(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($bob->user);
            $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Workshop', timezone: 'UTC');

            app(ConfigurePlanReadiness::class)->execute(
                $plan,
                $bob->user,
                prerequisites: [
                    ['title' => 'Safety glasses ready', 'required' => true],
                    ['title' => 'Bring notebook', 'required' => false],
                ],
            );

            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $bob->user,
                PlanScheduleFrequency::Daily,
                '2026-09-27',
                '12:00',
                60,
                occurrenceLimit: 2,
            );

            $occurrences = $rule->occurrences()->orderBy('scheduled_start_at')->get();
            $first = $occurrences->firstOrFail();
            $second = $occurrences->last();
            $required = $plan->prerequisites()->where('is_required', true)->sole();

            $this->assertSame(1, $first->remainingRequiredPrerequisites());

            $this->assertHttp422(
                fn () => app(TransitionPlanOccurrence::class)->start($first, $bob->user),
            );

            app(SetPlanOccurrencePrerequisite::class)->execute(
                $first,
                $required,
                $bob->user,
                true,
            );

            $this->assertTrue($first->fresh()->prerequisitesSatisfied());
            $this->assertFalse($second->fresh()->prerequisitesSatisfied());

            $started = app(TransitionPlanOccurrence::class)->start($first->fresh(), $bob->user);
            $this->assertSame(PlanOccurrenceStatus::InProgress, $started->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_expected_expense_is_preserved_and_actual_expense_is_recorded_separately(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($bob->user);
            $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Buy materials', timezone: 'UTC');

            $plan = app(ConfigurePlanReadiness::class)->execute(
                $plan,
                $bob->user,
                expenseEstimates: [
                    ['label' => 'Materials', 'amount' => '25.00', 'unit_code' => 'EUR'],
                ],
            );

            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $bob->user,
                PlanScheduleFrequency::Once,
                '2026-09-27',
                '12:00',
                60,
            );
            $occurrence = app(TransitionPlanOccurrence::class)->start(
                $rule->occurrences()->sole(),
                $bob->user,
            );
            $estimate = $plan->expenseEstimates()->with('monetaryUnit')->sole();

            $expense = app(RecordPlanOccurrenceExpense::class)->execute(
                $occurrence,
                $bob->user,
                'Materials',
                '28.50',
                'EUR',
                $estimate,
                'Actual checkout total',
            );

            $this->assertSame(2500, $estimate->amount_minor);
            $this->assertSame(2850, $expense->amount_minor);
            $this->assertSame('25.00', MoneyAmount::format($estimate->amount_minor, $estimate->monetaryUnit->exponent));
            $this->assertSame('28.50', MoneyAmount::format($expense->amount_minor, $expense->monetaryUnit->exponent));
            $this->assertDatabaseCount('journal_entries', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_month_and_hour_calendar_buckets_stay_compact_until_drilled_down(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 08:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($bob->user);
            $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Dense calendar item', timezone: 'UTC');

            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $bob->user,
                PlanScheduleFrequency::Once,
                '2026-09-27',
                '10:15',
                15,
            );
            $occurrence = $rule->occurrences()->sole();
            $occurrenceUrl = route('planner.show', $plan).'#occurrence-'.$occurrence->uuid;

            $component = Livewire::actingAs($bob->user)
                ->test(PlannerIndex::class)
                ->set('view', 'calendar')
                ->call('showMonth', '2026-09-01')
                ->assertDontSee($occurrenceUrl, false)
                ->assertSee('1 calendar item');

            $component
                ->call('showDay', '2026-09-27')
                ->assertDontSee($occurrenceUrl, false)
                ->call('showHour', '2026-09-27', 10)
                ->assertDontSee($occurrenceUrl, false)
                ->call('setSlotMinutes', 15)
                ->call('selectSlot', 15)
                ->assertSee($occurrenceUrl, false)
                ->assertSee('Dense calendar item');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_plan_cannot_end_while_occurrence_is_in_progress(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:00:00 UTC');

        try {
            [$bob, $plan, $occurrence] = $this->oneTimeOccurrence(
                startsOn: '2026-09-27',
                startTime: '12:00',
                durationMinutes: 60,
            );

            app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);

            $this->assertHttp422(
                fn () => app(TransitionPlan::class)->execute($plan, $bob->user, PlanStatus::Completed),
            );

            $this->assertSame(PlanStatus::Active, $plan->fresh()->status);
            $this->assertSame(PlanOccurrenceStatus::InProgress, $occurrence->fresh()->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array{Actor, Plan, PlanOccurrence}
     */
    private function oneTimeOccurrence(
        string $startsOn,
        string $startTime,
        int $durationMinutes,
        int $beforeMinutes = 0,
        int $afterMinutes = 0,
    ): array {
        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);
        $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Timed work', timezone: 'UTC');

        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $bob->user,
            PlanScheduleFrequency::Once,
            $startsOn,
            $startTime,
            $durationMinutes,
            windowBeforeMinutes: $beforeMinutes,
            windowAfterMinutes: $afterMinutes,
        );

        return [$bob, $plan, $rule->occurrences()->sole()];
    }

    private function assertHttp422(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected HTTP 422.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }
}
