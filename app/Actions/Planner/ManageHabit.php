<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\Planner\PlanHabit;
use App\Models\Planner\PlanPeriod;
use App\Models\Planner\PlanUrge;
use App\Models\User;
use App\PlanStatus;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The habit side of a plan: its loop (cue, reward, why, what it replaces),
 * tuning periods that change one thing at a time, and the log of urges for
 * a habit being replaced. All of it is private to the person who made the
 * plan, even when the plan itself is shared.
 */
class ManageHabit
{
    /** @param array{mode?: string, cue?: ?string, reward?: ?string, why?: ?string, replaces?: ?string} $loop */
    public function saveLoop(Plan $plan, User $user, array $loop): PlanHabit
    {
        $actor = $this->owner($plan, $user);
        $mode = (string) ($loop['mode'] ?? ($plan->habit()->value('mode') ?? 'build'));
        abort_unless(in_array($mode, PlanHabit::MODES, true), 422);

        $values = ['mode' => $mode];
        foreach (['cue' => 300, 'reward' => 300, 'why' => 500, 'replaces' => 300] as $field => $max) {
            $value = Str::squish((string) ($loop[$field] ?? ''));
            abort_if(mb_strlen($value) > $max, 422, __('habits.validation.too_long'));
            $values[$field] = $value === '' ? null : $value;
        }
        abort_if($mode === 'replace' && $values['replaces'] === null, 422, __('habits.validation.replaces_required'));

        return DB::transaction(function () use ($plan, $actor, $values): PlanHabit {
            $habit = PlanHabit::query()->lockForUpdate()->firstOrNew(['plan_id' => $plan->id]);
            $habit->fill($values + ['created_by_actor_id' => $habit->created_by_actor_id ?? $actor->id])->save();

            return $habit;
        });
    }

    /**
     * Start the next period with one thing set differently. The current
     * period ends yesterday (its results stay as they are); a period that
     * starts today and has nothing yet is simply changed.
     */
    public function startPeriod(Plan $plan, User $user, string $focus, ?string $targetTime, int $days): PlanPeriod
    {
        $this->owner($plan, $user);
        abort_unless($plan->status === PlanStatus::Active, 422, __('planner.validation.active_plan_required'));
        $focus = Str::squish($focus);
        abort_if($focus === '' || mb_strlen($focus) > 300, 422, __('habits.validation.focus'));
        abort_unless(in_array($days, PlanPeriod::LENGTHS, true), 422, __('habits.validation.days'));
        $targetTime = $targetTime === '' ? null : $targetTime;
        abort_if($targetTime !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $targetTime) !== 1, 422, __('habits.validation.target_time'));

        $today = CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->startOfDay();

        return DB::transaction(function () use ($plan, $focus, $targetTime, $days, $today): PlanPeriod {
            $current = PlanPeriod::query()->where('plan_id', $plan->id)->whereNull('verdict')->lockForUpdate()->orderByDesc('number')->first();
            if ($current instanceof PlanPeriod && $current->starts_on->greaterThanOrEqualTo($today)) {
                $current->update(['focus' => $focus, 'target_time' => $targetTime, 'starts_on' => $today->toDateString(), 'ends_on' => $today->addDays($days - 1)->toDateString()]);

                return $current;
            }
            if ($current instanceof PlanPeriod) {
                $current->update([
                    'ended_on' => $current->ends_on->lessThan($today) ? $current->ends_on->toDateString() : $today->subDay()->toDateString(),
                    'verdict' => 'adjust',
                ]);
            }

            return PlanPeriod::query()->create([
                'plan_id' => $plan->id,
                'number' => ((int) PlanPeriod::query()->where('plan_id', $plan->id)->max('number')) + 1,
                'focus' => $focus,
                'target_time' => $targetTime,
                'starts_on' => $today->toDateString(),
                'ends_on' => $today->addDays($days - 1)->toDateString(),
            ]);
        });
    }

    /**
     * Close a period with the person's own verdict and, optionally, the four
     * automaticity answers (1 = strongly disagree … 5 = strongly agree).
     *
     * @param  ?array<int, mixed>  $automaticity  Exactly four answers, each 1 to 5.
     */
    public function finishPeriod(PlanPeriod $period, User $user, string $verdict, ?string $reflection, ?array $automaticity, bool $endYesterday = false): PlanPeriod
    {
        $this->owner($period->plan, $user);
        abort_unless(in_array($verdict, PlanPeriod::VERDICTS, true), 422, __('habits.validation.verdict'));
        abort_if($period->verdict !== null, 422, __('habits.validation.period_finished'));
        $reflection = trim((string) $reflection);
        abort_if(mb_strlen($reflection) > 2000, 422, __('habits.validation.too_long'));
        if ($automaticity !== null) {
            $automaticity = array_values($automaticity);
            abort_unless(count($automaticity) === 4 && collect($automaticity)->every(fn ($value) => is_int($value) && $value >= 1 && $value <= 5), 422, __('habits.validation.automaticity'));
        }

        $today = CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->startOfDay();
        // When the next period starts today, this one ends yesterday so no day belongs to both.
        $last = $endYesterday ? $today->subDay() : $today;
        abort_if($last->lessThan($period->starts_on), 422, __('habits.validation.period_too_new'));
        $period->update([
            'ended_on' => $period->ends_on->lessThan($last) ? $period->ends_on->toDateString() : $last->toDateString(),
            'verdict' => $verdict,
            'reflection' => $reflection === '' ? null : $reflection,
            'automaticity' => $automaticity,
        ]);

        return $period;
    }

    /** "The urge came": what happened this time. */
    public function logUrge(Plan $plan, User $user, string $outcome, ?int $strength, ?string $note): PlanUrge
    {
        $actor = $this->owner($plan, $user);
        abort_unless($plan->status === PlanStatus::Active, 422, __('planner.validation.active_plan_required'));
        abort_unless(in_array($outcome, PlanUrge::OUTCOMES, true), 422);
        abort_if($strength !== null && ($strength < 1 || $strength > 5), 422, __('habits.validation.strength'));
        $note = Str::squish((string) $note);
        abort_if(mb_strlen($note) > 300, 422, __('habits.validation.too_long'));

        $now = CarbonImmutable::now('UTC');
        $today = $now->setTimezone(TemporalPreferences::timezoneFor($user))->toDateString();
        $period = PlanPeriod::query()->where('plan_id', $plan->id)
            ->whereDate('starts_on', '<=', $today)
            ->where(fn ($query) => $query->where(fn ($open) => $open->whereNull('ended_on')->whereDate('ends_on', '>=', $today))->orWhereDate('ended_on', '>=', $today))
            ->orderByDesc('number')->first();

        return PlanUrge::query()->create([
            'plan_id' => $plan->id,
            'period_id' => $period?->id,
            'actor_id' => $actor->id,
            'occurred_at' => $now,
            'outcome' => $outcome,
            'strength' => $strength,
            'note' => $note === '' ? null : $note,
        ]);
    }

    /** Undo the last urge entry of the last few minutes (a mistaken tap). */
    public function undoUrge(PlanUrge $urge, User $user): void
    {
        $actor = $this->owner($urge->plan, $user);
        abort_unless((int) $urge->actor_id === (int) $actor->id && $urge->created_at->greaterThan(now()->subMinutes(10)), 422, __('habits.validation.undo_window'));
        $urge->delete();
    }

    private function owner(Plan $plan, User $user): Actor
    {
        $actor = User::query()->with('actor')->find($user->id)?->actor;
        abort_unless($actor instanceof Actor && (int) $plan->created_by_actor_id === (int) $actor->id, 403);

        return $actor;
    }
}
