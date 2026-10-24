<?php

namespace Database\Seeders;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ManageHabit;
use App\Models\Plan;
use App\Models\Planner\PlanPeriod;
use App\Models\Planner\PlanUrge;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Local demo of the living planner for the superadmin:
 * a 21-day "wake up at 06:00" routine with two weeks of answers (late,
 * later, not done, getting closer), two tuning periods, and a habit being
 * replaced ("instead of smoking") with urges in two periods.
 *
 * php artisan db:seed --class=LivingPlansDemoSeeder   (never in production)
 */
class LivingPlansDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_if(app()->environment('production'), 403, 'Demo data is not for production.');

        $username = (string) config('bootstrap.superadmin.username');
        $user = User::query()->where('username', $username)->firstOrFail();
        $timezone = $user->timezone ?: 'Asia/Tehran';
        $context = app(EnsurePersonalContext::class)->execute($user);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = $today->subDays(13);

        if (Plan::query()->where('created_by_actor_id', $user->actor->id)->where('title', 'Wake up at 06:00')->exists()) {
            $this->command?->info('Living plans demo already present.');

            return;
        }

        // 1. Build a habit: wake up at 06:00 every day for 21 days.
        $wake = app(CreatePlan::class)->execute($context, $user, 'Wake up at 06:00', 'Start the day early: exercise outside, then breakfast.', $timezone, metadata: ['kind' => 'routine']);
        app(CreatePlanScheduleRule::class)->execute($wake, $user, PlanScheduleFrequency::Daily, $start->toDateString(), '06:00', 30,
            endsOn: $start->addDays(20)->toDateString(), windowBeforeMinutes: 15, windowAfterMinutes: 15);
        $habits = app(ManageHabit::class);
        $habits->saveLoop($wake, $user, ['mode' => 'build', 'cue' => 'Right after the alarm rings', 'reward' => 'Coffee on the balcony', 'why' => 'To start my days with energy']);

        PlanPeriod::query()->create(['plan_id' => $wake->id, 'number' => 1, 'focus' => 'Starting point', 'starts_on' => $start->toDateString(), 'ends_on' => $start->addDays(6)->toDateString(), 'ended_on' => $start->addDays(6)->toDateString(), 'verdict' => 'adjust', 'reflection' => 'Too big a jump from 08:00 at once.', 'automaticity' => [2, 1, 1, 1]]);
        PlanPeriod::query()->create(['plan_id' => $wake->id, 'number' => 2, 'focus' => 'Clothes ready the night before', 'target_time' => '06:30', 'starts_on' => $start->addDays(7)->toDateString(), 'ends_on' => $start->addDays(20)->toDateString()]);

        // Minutes after 06:00 (null: not done, with a reason; 'skip': said in advance; 'none': no report).
        $answers = [48, 72, null, 40, 'none', 35, 30, 28, 'skip', 25, 18, 15, 12];
        $reasons = ['overslept', 'tired'];
        foreach ($answers as $index => $answer) {
            $day = $start->addDays($index);
            $occurrence = PlanOccurrence::query()->where('plan_id', $wake->id)->whereDate('local_date', $day->toDateString())->first();
            if (! $occurrence instanceof PlanOccurrence || $answer === 'none') {
                continue;
            }
            $toldAt = $day->setTime(22, 0)->utc();
            if ($answer === 'skip') {
                $occurrence->transition(PlanOccurrenceStatus::Skipped, null, null, null, ['reason' => 'plans_changed', 'reported_at' => $day->subDay()->setTime(21, 0)->utc()]);
            } elseif ($answer === null) {
                $occurrence->transition(PlanOccurrenceStatus::Skipped, null, null, null, ['reason' => $reasons[$index % 2], 'note' => 'Went to bed after 01:00', 'reported_at' => $toldAt]);
            } else {
                $started = $day->setTime(6, 0)->addMinutes($answer)->utc();
                $occurrence->transition(PlanOccurrenceStatus::Completed, $started, $started->addMinutes(30), $toldAt, ['difficulty' => max(1, 5 - intdiv($index, 3)), 'reported_at' => $toldAt]);
            }
            PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $occurrence->id, 'actor_id' => $user->actor->id, 'event_type' => PlanOccurrenceEventType::Reported, 'payload' => ['demo' => true]]);
        }

        // 2. Replace a habit: instead of smoking, two replacements compared.
        $smoke = app(CreatePlan::class)->execute($context, $user, 'Instead of smoking', null, $timezone, metadata: ['kind' => 'routine', 'habit' => 'replace']);
        $habits->saveLoop($smoke, $user, ['mode' => 'replace', 'replaces' => 'Smoking', 'cue' => 'After lunch, with coffee, when stressed', 'reward' => 'A short break and some calm']);
        $walk = PlanPeriod::query()->create(['plan_id' => $smoke->id, 'number' => 1, 'focus' => 'Walk for 5 minutes', 'starts_on' => $start->toDateString(), 'ends_on' => $start->addDays(6)->toDateString(), 'ended_on' => $start->addDays(6)->toDateString(), 'verdict' => 'adjust', 'automaticity' => [2, 2, 1, 1]]);
        $chess = PlanPeriod::query()->create(['plan_id' => $smoke->id, 'number' => 2, 'focus' => 'One quick game of chess', 'starts_on' => $start->addDays(7)->toDateString(), 'ends_on' => $start->addDays(20)->toDateString()]);
        foreach (range(0, 13) as $offset) {
            $period = $offset < 7 ? $walk : $chess;
            $urges = $offset < 7 ? 5 : 4;
            $replaced = $offset < 7 ? 2 + intdiv($offset, 3) : 3 + intdiv($offset - 7, 4);
            foreach (range(1, $urges) as $n) {
                PlanUrge::query()->create(['plan_id' => $smoke->id, 'period_id' => $period->id, 'actor_id' => $user->actor->id,
                    'occurred_at' => $start->addDays($offset)->setTime(9 + $n * 2, 15)->utc(), 'outcome' => $n <= $replaced ? 'replaced' : 'gave_in', 'strength' => max(1, 5 - intdiv($offset, 4))]);
            }
        }

        $this->command?->info('Living plans demo created for '.$username.'.');
    }
}
