<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ManageHabit;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Livewire\Home\Today;
use App\Livewire\Planner\HabitCreate;
use App\Livewire\Planner\Show;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\Planner\PlanUrge;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\Support\Planner\OccurrenceTiming;
use App\Support\Planner\RoutineProgress;
use App\Support\RequestMemo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Plans that know what time it is: nothing done before its time, nothing
 * waiting forever, honest expected-versus-actual reports, and habits that
 * are built or replaced in comparable periods.
 */
class LivingPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_a_future_occurrence_of_a_daily_routine_cannot_be_done_or_started_now(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $tomorrow = $this->occurrenceOn($plan, '2026-11-02');

        $this->assertSame('upcoming', $tomorrow->executionPhase());
        $this->assertSame(['skip', 'cancel'], OccurrenceTiming::actions($tomorrow));
        $this->assertRefused(fn () => app(TransitionPlanOccurrence::class)->markDone($tomorrow, $user));
        $this->assertRefused(fn () => app(TransitionPlanOccurrence::class)->start($tomorrow, $user));
        $this->assertRefused(fn () => app(TransitionPlanOccurrence::class)->report($tomorrow, $user, ['outcome' => 'done', 'started_at' => CarbonImmutable::now()]));
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $tomorrow->fresh()->status);

        // The plan page offers no Done button for it either.
        Livewire::actingAs($user)->test(Show::class, ['plan' => $plan])
            ->call('quickDone', $tomorrow->uuid)
            ->assertHasErrors('execution.'.$tomorrow->id);
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $tomorrow->fresh()->status);
    }

    public function test_a_forgotten_occurrence_moves_on_by_itself_and_is_asked_about_not_failed(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $today = $this->occurrenceOn($plan, '2026-11-01');

        $this->at('2026-11-01 06:05', 'Asia/Tehran');
        $this->assertSame('now', $today->executionPhase());
        $this->at('2026-11-01 09:00', 'Asia/Tehran');
        $this->assertSame('late', $today->executionPhase());
        $this->at('2026-11-02 10:00', 'Asia/Tehran');
        $this->assertSame('awaiting_report', $today->executionPhase());
        $this->assertSame(['report'], OccurrenceTiming::actions($today));

        // Today asks about it, and does not suggest it as the next thing to do.
        RequestMemo::flush();
        Livewire::actingAs($user)->test(Today::class)
            ->assertSeeHtml('data-follow-up="'.$today->uuid.'"');

        $this->at('2026-11-09 10:00', 'Asia/Tehran');
        $this->assertSame('no_report', $today->executionPhase());
        $this->assertSame([], OccurrenceTiming::actions($today));
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $today->fresh()->status, 'No outcome is invented.');
        RequestMemo::flush();
        Livewire::actingAs($user)->test(Today::class)->assertDontSeeHtml('data-follow-up="'.$today->uuid.'"');
    }

    public function test_expected_against_actual_day_by_day_with_reasons_and_a_trend(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $transition = app(TransitionPlanOccurrence::class);
        $answers = ['2026-11-01' => '06:48', '2026-11-02' => '07:12', '2026-11-03' => null, '2026-11-04' => '06:40', '2026-11-05' => '06:20', '2026-11-06' => '06:10'];

        foreach ($answers as $date => $time) {
            $this->at($date.' 23:00', 'Asia/Tehran');
            $occurrence = $this->occurrenceOn($plan, $date);
            $transition->report($occurrence, $user, $time === null
                ? ['outcome' => 'not_done', 'reason' => 'overslept', 'note' => 'Slept late the night before']
                : ['outcome' => 'done', 'started_at' => CarbonImmutable::parse($date.' '.$time, 'Asia/Tehran'), 'minutes' => 30, 'difficulty' => 4]);
        }

        $progress = RoutineProgress::between($plan, '2026-11-01', '2026-11-21');
        $rows = collect($progress['rows']);
        $this->assertCount(21, $rows);
        $this->assertSame([48, 72, null, 40, 20, 10], $rows->take(6)->pluck('deviation')->all());
        $this->assertSame('not_done', $rows[2]['phase']);
        $this->assertSame('overslept', $rows[2]['occurrence']->reason);
        $this->assertSame('upcoming', $rows[20]['phase']);

        $summary = $progress['summary'];
        $this->assertSame(5, $summary['done']);
        $this->assertSame(1, $summary['not_done']);
        $this->assertSame('closer', $summary['trend'], 'From about an hour late towards the target.');
        $this->assertSame('overslept', $summary['top_reason']);
        $this->assertEquals(4.0, $summary['difficulty']);

        $this->actingAs($user);
        Livewire::actingAs($user)->test(Show::class, ['plan' => $plan])
            ->assertSeeHtml('data-habit-table')
            ->assertSeeHtml('data-habit-trend="closer"');
    }

    public function test_reports_afterwards_are_limited_to_a_week_and_corrections_keep_history(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $occurrence = $this->occurrenceOn($plan, '2026-11-01');
        $transition = app(TransitionPlanOccurrence::class);

        $this->at('2026-11-03 09:00', 'Asia/Tehran');
        // A time on another day, or in the future, is refused.
        $this->assertRefused(fn () => $transition->report($occurrence, $user, ['outcome' => 'done', 'started_at' => CarbonImmutable::parse('2026-11-02 06:30', 'Asia/Tehran')]));
        $transition->report($occurrence, $user, ['outcome' => 'partly', 'percent' => 50, 'started_at' => CarbonImmutable::parse('2026-11-01 06:30', 'Asia/Tehran'), 'minutes' => 15]);
        $this->assertSame('partly', $occurrence->fresh()->executionPhase());

        $this->at('2026-11-05 09:00', 'Asia/Tehran');
        $transition->report($occurrence->fresh(), $user, ['outcome' => 'done', 'started_at' => CarbonImmutable::parse('2026-11-01 06:25', 'Asia/Tehran'), 'minutes' => 30]);
        $fresh = $occurrence->fresh();
        $this->assertSame('done', $fresh->executionPhase());
        $this->assertNull($fresh->completion_percent);
        $this->assertSame(25, OccurrenceTiming::deviationMinutes($fresh));
        $this->assertSame(
            [PlanOccurrenceEventType::Reported, PlanOccurrenceEventType::Corrected],
            PlanOccurrenceEvent::query()->where('plan_occurrence_id', $occurrence->id)->orderBy('id')->get()->pluck('event_type')->all(),
        );

        $this->at('2026-11-20 09:00', 'Asia/Tehran');
        $this->assertRefused(fn () => $transition->report($occurrence->fresh(), $user, ['outcome' => 'not_done']));
        $other = $this->occurrenceOn($plan, '2026-11-02');
        $this->assertRefused(fn () => $transition->report($other, $user, ['outcome' => 'done', 'started_at' => CarbonImmutable::parse('2026-11-02 06:10', 'Asia/Tehran')]));
    }

    public function test_skipping_in_advance_is_not_the_same_as_not_doing(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $transition = app(TransitionPlanOccurrence::class);

        $transition->skip($this->occurrenceOn($plan, '2026-11-03'), $user, 'plans_changed');
        $this->assertSame('skipped', $this->occurrenceOn($plan, '2026-11-03')->executionPhase());

        $this->at('2026-11-01 09:00', 'Asia/Tehran');
        $transition->skip($this->occurrenceOn($plan, '2026-11-01'), $user);
        $this->assertSame('not_done', $this->occurrenceOn($plan, '2026-11-01')->executionPhase());
    }

    public function test_a_due_date_can_be_handled_early_but_only_the_nearest_one(): void
    {
        $this->at('2026-11-01 10:00', 'Asia/Tehran');
        $user = $this->person();
        $plan = $this->plan($user, 'Pay the rent', 'due');
        app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Monthly, '2026-11-05', '09:00', 15, monthDay: 5);
        $first = PlanOccurrence::query()->where('plan_id', $plan->id)->orderBy('scheduled_start_at')->firstOrFail();
        $second = PlanOccurrence::query()->where('plan_id', $plan->id)->orderBy('scheduled_start_at')->skip(1)->firstOrFail();

        $this->assertNull($first->closes_at, 'A due date stays open until handled.');
        $this->assertContains('done', OccurrenceTiming::actions($first));
        $this->assertNotContains('done', OccurrenceTiming::actions($second));
        app(TransitionPlanOccurrence::class)->markDone($first, $user);
        $this->assertSame('done', $first->fresh()->executionPhase());

        $this->at('2026-12-20 10:00', 'Asia/Tehran');
        $this->assertSame('overdue', $second->fresh()->executionPhase());
    }

    public function test_periods_change_one_thing_and_compare(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $habits = app(ManageHabit::class);
        $habits->saveLoop($plan, $user, ['mode' => 'build', 'cue' => 'Right after the alarm', 'reward' => 'Coffee on the balcony', 'why' => 'Energy']);
        $first = $habits->startPeriod($plan, $user, 'Starting point', null, 7);
        $transition = app(TransitionPlanOccurrence::class);
        foreach (['2026-11-01' => '07:00', '2026-11-02' => '07:10', '2026-11-03' => '06:50'] as $date => $time) {
            $this->at($date.' 22:00', 'Asia/Tehran');
            $transition->report($this->occurrenceOn($plan, $date), $user, ['outcome' => 'done', 'started_at' => CarbonImmutable::parse($date.' '.$time, 'Asia/Tehran'), 'minutes' => 20]);
        }

        $this->at('2026-11-04 05:00', 'Asia/Tehran');
        $habits->finishPeriod($first, $user, 'adjust', 'Too far from 06:00 at once', [2, 2, 1, 1], endYesterday: true);
        $second = $habits->startPeriod($plan, $user, 'Clothes ready the night before', '06:30', 7);
        $this->assertSame('2026-11-03', $first->fresh()->ended_on->toDateString());
        $this->assertSame('2026-11-04', $second->starts_on->toDateString());
        $this->assertEquals(1.5, $first->fresh()->automaticityScore());

        $this->at('2026-11-04 22:00', 'Asia/Tehran');
        $transition->report($this->occurrenceOn($plan, '2026-11-04'), $user, ['outcome' => 'done', 'started_at' => CarbonImmutable::parse('2026-11-04 06:35', 'Asia/Tehran'), 'minutes' => 20]);

        $rows = RoutineProgress::between($plan, '2026-11-04', '2026-11-10', $second->fresh())['rows'];
        $this->assertSame(5, $rows[0]['deviation'], 'Measured against this period\'s in-between target, 06:30.');
        $this->assertSame(35, $rows[0]['from_ideal'], 'The ideal stays 06:00.');

        $plan->unsetRelation('periods');
        $comparison = RoutineProgress::periods($plan->fresh(), false);
        $this->assertCount(2, $comparison);
        $this->assertSame(3, $comparison[0]['summary']['done']);
        $this->assertSame(1, $comparison[1]['summary']['done']);
        $this->assertRefused(fn () => $habits->finishPeriod($first->fresh(), $user, 'keep', null, null));
        $this->assertRefused(fn () => $habits->finishPeriod($second->fresh(), $user, 'keep', null, [1, 2, 3]));
    }

    public function test_replacing_a_habit_with_urges_and_periods(): void
    {
        $this->at('2026-11-01 09:00', 'Asia/Tehran');
        $user = $this->person();

        Livewire::actingAs($user)->test(HabitCreate::class)
            ->set('replaces', 'Smoking')
            ->set('routine', 'Walk for 5 minutes')
            ->set('cue', 'After lunch')
            ->set('days', 14)
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::query()->where('created_by_actor_id', $user->actor->id)->latest('id')->firstOrFail();
        $this->assertSame('replace', $plan->habit->mode);
        $this->assertSame('Walk for 5 minutes', $plan->periods->first()->focus);
        $this->assertSame(0, $plan->scheduleRules()->count());

        $habits = app(ManageHabit::class);
        $habits->logUrge($plan, $user, 'replaced', 4, null);
        $habits->logUrge($plan, $user, 'replaced', 3, null);
        $last = $habits->logUrge($plan, $user, 'gave_in', 5, null);
        $habits->undoUrge($last, $user);
        $habits->logUrge($plan, $user, 'gave_in', 5, null);

        $urges = RoutineProgress::urges($plan, '2026-11-01', '2026-11-14');
        $this->assertSame(3, $urges['total']);
        $this->assertSame(67, $urges['rate']);
        $this->assertSame(1, $urges['days']['2026-11-01']['gave_in']);

        $this->at('2026-11-08 09:00', 'Asia/Tehran');
        $first = $plan->periods()->first();
        $habits->finishPeriod($first, $user, 'adjust', null, null, endYesterday: true);
        $habits->startPeriod($plan, $user, 'Read two pages', null, 14);
        $habits->logUrge($plan, $user, 'replaced', 2, null);
        $this->assertSame(2, PlanUrge::query()->where('plan_id', $plan->id)->whereNotNull('period_id')->distinct()->count('period_id'));

        Livewire::actingAs($user)->test(Show::class, ['plan' => $plan])
            ->assertSeeHtml('data-urge-panel')
            ->assertSeeHtml('data-habit-comparison')
            ->call('logUrge', 'replaced')
            ->assertHasNoErrors();

        // Today offers the urge buttons too.
        RequestMemo::flush();
        Livewire::actingAs($user)->test(Today::class)
            ->assertSeeHtml('data-today-habits')
            ->call('logUrge', $plan->uuid, 'replaced');
        $this->assertSame(6, PlanUrge::query()->where('plan_id', $plan->id)->count());
    }

    public function test_habit_data_stays_private_to_the_person_who_made_the_plan(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        app(ManageHabit::class)->saveLoop($plan, $user, ['mode' => 'replace', 'replaces' => 'Smoking']);
        $stranger = $this->person();

        $this->assertForbidden(fn () => app(ManageHabit::class)->logUrge($plan, $stranger, 'replaced', null, null));
        $this->assertForbidden(fn () => app(ManageHabit::class)->saveLoop($plan, $stranger, ['mode' => 'build']));
        $this->assertForbidden(fn () => app(ManageHabit::class)->startPeriod($plan, $stranger, 'x', null, 7));
    }

    public function test_today_one_tap_done_as_planned_and_kind_reporting_stats(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [$user, $plan] = $this->wakeUpRoutine();
        $this->at('2026-11-04 10:00', 'Asia/Tehran');
        $waiting = $this->occurrenceOn($plan, '2026-11-02');

        RequestMemo::flush();
        Livewire::actingAs($user)->test(Today::class)
            ->assertSeeHtml('data-today-follow-ups')
            ->assertSeeHtml('data-report-stats')
            ->call('quickDoneAsPlanned', $waiting->uuid)
            ->assertHasNoErrors();

        $fresh = $waiting->fresh();
        $this->assertSame('done', $fresh->executionPhase());
        $this->assertSame(0, OccurrenceTiming::deviationMinutes($fresh));

        Livewire::actingAs($user)->test(Today::class)
            ->call('openReport', $this->occurrenceOn($plan, '2026-11-03')->uuid, 'not_done')
            ->set('reportReason', 'tired')
            ->call('saveReport')
            ->assertHasNoErrors()
            ->assertSet('reportUuid', null);
        $this->assertSame('tired', $this->occurrenceOn($plan, '2026-11-03')->reason);
    }

    public function test_every_occurrence_knows_when_it_closes(): void
    {
        $this->at('2026-11-01 05:00', 'Asia/Tehran');
        [, $plan] = $this->wakeUpRoutine();
        $routine = $this->occurrenceOn($plan, '2026-11-01');
        // A routine is possible until the end of its day (Tehran), whatever its window.
        $this->assertSame('2026-11-01 20:29:59', $routine->closes_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(OccurrenceTiming::DAY, $routine->scheduleRule->close_policy);

        $user = $this->person();
        $meeting = $this->plan($user, 'Team meeting', 'meeting');
        $rule = app(CreatePlanScheduleRule::class)->execute($meeting, $user, PlanScheduleFrequency::Once, '2026-11-01', '10:00', 60, windowBeforeMinutes: 10, windowAfterMinutes: 15);
        $this->assertSame('2026-11-01 06:45:00', $rule->occurrences()->sole()->closes_at->utc()->format('Y-m-d H:i:s'));
    }

    /** @return array{0: User, 1: Plan} A daily 06:00 wake-up routine for 21 days (Tehran). */
    private function wakeUpRoutine(): array
    {
        $user = $this->person();
        $plan = $this->plan($user, 'Wake up at 06:00', 'routine');
        app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Daily, '2026-11-01', '06:00', 30,
            endsOn: '2026-11-21', windowBeforeMinutes: 15, windowAfterMinutes: 15);

        return [$user, $plan];
    }

    private function plan(User $user, string $title, string $kind): Plan
    {
        $context = app(EnsurePersonalContext::class)->execute($user);

        return app(CreatePlan::class)->execute($context, $user, $title, timezone: 'Asia/Tehran', metadata: ['kind' => $kind]);
    }

    private function person(): User
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill(['timezone' => 'Asia/Tehran', 'locale' => 'en'])->save();

        return $actor->user->fresh();
    }

    private function occurrenceOn(Plan $plan, string $date): PlanOccurrence
    {
        return PlanOccurrence::query()->with(['plan', 'scheduleRule'])->where('plan_id', $plan->id)->whereDate('local_date', $date)->firstOrFail();
    }

    private function at(string $local, string $timezone): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($local, $timezone)->utc());
        RequestMemo::flush();
    }

    private function assertRefused(callable $attempt): void
    {
        try {
            $attempt();
            $this->fail('A refused action succeeded.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    private function assertForbidden(callable $attempt): void
    {
        try {
            $attempt();
            $this->fail('A forbidden action succeeded.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
