<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\PlanReminder;
use App\Models\PlanScheduleRule;
use App\Models\User;
use App\PlanEventType;
use App\PlanScheduleFrequency;
use App\PlanStatus;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CreatePlanScheduleRule
{
    public function __construct(private readonly MaterializePlanOccurrences $materialize) {}

    /**
     * @param  list<int>  $weekdays
     * @param  list<string>  $selectedDates
     * @param  list<int>  $reminderOffsets
     */
    public function execute(
        Plan $plan,
        User $user,
        PlanScheduleFrequency $frequency,
        string $startsOn,
        string $startTime,
        int $durationMinutes,
        int $interval = 1,
        array $weekdays = [],
        array $selectedDates = [],
        ?string $endsOn = null,
        ?int $occurrenceLimit = null,
        int $windowBeforeMinutes = 0,
        int $windowAfterMinutes = 0,
        array $reminderOffsets = [],
    ): PlanScheduleRule {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('manage', $plan);

        $lockedPlan = Plan::query()->with('context')->findOrFail($plan->id);
        abort_unless($lockedPlan->status === PlanStatus::Active, 422, 'Only an active Plan can receive a Schedule Rule.');
        abort_unless(TemporalPreferences::validTimezone($lockedPlan->timezone), 422, 'Plan timezone is invalid.');

        $startDate = $this->date($startsOn, $lockedPlan->timezone, 'Schedule start date is invalid.');
        abort_unless(preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime) === 1, 422, 'Schedule start time is invalid.');
        abort_if($durationMinutes < 1 || $durationMinutes > 10080, 422, 'Schedule duration must be between 1 minute and 7 days.');
        abort_if($interval < 1 || $interval > 365, 422, 'Schedule interval must be between 1 and 365.');
        abort_if($windowBeforeMinutes < 0 || $windowBeforeMinutes > 10080, 422, 'Schedule early window is invalid.');
        abort_if($windowAfterMinutes < 0 || $windowAfterMinutes > 10080, 422, 'Schedule late window is invalid.');
        abort_if($occurrenceLimit !== null && ($occurrenceLimit < 1 || $occurrenceLimit > 10000), 422, 'Occurrence limit must be between 1 and 10000.');

        $normalizedWeekdays = collect($weekdays)
            ->map(fn (mixed $day): int => (int) $day)
            ->unique()
            ->sort()
            ->values()
            ->all();
        abort_if(collect($normalizedWeekdays)->contains(fn (int $day): bool => $day < 1 || $day > 7), 422, 'Weekly schedule weekdays must use ISO values 1 through 7.');

        $normalizedSelectedDates = collect($selectedDates)
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        foreach ($normalizedSelectedDates as $date) {
            $this->date($date, $lockedPlan->timezone, 'One of the selected schedule dates is invalid.');
        }

        if ($frequency === PlanScheduleFrequency::Weekly && $normalizedWeekdays === []) {
            $normalizedWeekdays = [$startDate->isoWeekday()];
        }

        if ($frequency === PlanScheduleFrequency::SelectedDates) {
            abort_if($normalizedSelectedDates === [], 422, 'Selected-date schedules require at least one date.');
            abort_if(count($normalizedSelectedDates) > 366, 422, 'Selected-date schedules may contain at most 366 dates.');
            $startDate = $this->date($normalizedSelectedDates[0], $lockedPlan->timezone, 'Selected schedule date is invalid.');
        } else {
            $normalizedSelectedDates = [];
        }

        if ($frequency !== PlanScheduleFrequency::Weekly) {
            $normalizedWeekdays = [];
        }

        $endDate = $endsOn !== null && trim($endsOn) !== ''
            ? $this->date($endsOn, $lockedPlan->timezone, 'Schedule end date is invalid.')
            : null;
        abort_if($endDate !== null && $endDate->lt($startDate), 422, 'Schedule end date cannot be before its start date.');

        $reminders = collect($reminderOffsets)
            ->map(fn (mixed $minutes): int => (int) $minutes)
            ->unique()
            ->sort()
            ->values()
            ->all();
        abort_if(count($reminders) > 10, 422, 'A Schedule Rule may have at most ten reminder offsets.');
        abort_if(collect($reminders)->contains(fn (int $minutes): bool => $minutes < 0 || $minutes > 10080), 422, 'Reminder offsets must be between 0 minutes and 7 days.');

        return DB::transaction(function () use (
            $lockedPlan,
            $current,
            $frequency,
            $startDate,
            $startTime,
            $durationMinutes,
            $interval,
            $normalizedWeekdays,
            $normalizedSelectedDates,
            $endDate,
            $occurrenceLimit,
            $windowBeforeMinutes,
            $windowAfterMinutes,
            $reminders,
        ): PlanScheduleRule {
            $plan = Plan::query()->lockForUpdate()->findOrFail($lockedPlan->id);
            Gate::forUser($current)->authorize('manage', $plan);
            abort_unless($plan->status === PlanStatus::Active, 422, 'Only an active Plan can receive a Schedule Rule.');

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $rule = PlanScheduleRule::query()->create([
                'plan_id' => $plan->id,
                'created_by_actor_id' => $actor->id,
                'frequency' => $frequency,
                'interval' => $interval,
                'starts_on' => $startDate->format('Y-m-d'),
                'start_time' => $startTime.':00',
                'duration_minutes' => $durationMinutes,
                'weekdays' => $normalizedWeekdays === [] ? null : $normalizedWeekdays,
                'selected_dates' => $normalizedSelectedDates === [] ? null : $normalizedSelectedDates,
                'ends_on' => $endDate?->format('Y-m-d'),
                'occurrence_limit' => $occurrenceLimit,
                'window_before_minutes' => $windowBeforeMinutes,
                'window_after_minutes' => $windowAfterMinutes,
                'timezone' => $plan->timezone,
            ]);

            foreach ($reminders as $minutesBefore) {
                PlanReminder::query()->create([
                    'plan_id' => $plan->id,
                    'schedule_rule_id' => $rule->id,
                    'created_by_actor_id' => $actor->id,
                    'minutes_before' => $minutesBefore,
                    'channel' => 'app',
                    'status' => 'active',
                ]);
            }

            PlanEvent::query()->create([
                'plan_id' => $plan->id,
                'actor_id' => $actor->id,
                'event_type' => PlanEventType::ScheduleRuleCreated,
                'payload' => [
                    'schedule_rule_uuid' => $rule->uuid,
                    'frequency' => $frequency->value,
                    'reminder_offsets' => $reminders,
                ],
            ]);

            $materializeFrom = $startDate;

            if (in_array($frequency, [PlanScheduleFrequency::Daily, PlanScheduleFrequency::Weekly], true)) {
                $recent = CarbonImmutable::now($plan->timezone)->subDays(30)->startOfDay();

                if ($recent->greaterThan($materializeFrom)) {
                    $materializeFrom = $recent;
                }
            }

            $materializeThrough = CarbonImmutable::now($plan->timezone)->addDays(120)->endOfDay();

            if ($frequency === PlanScheduleFrequency::Once) {
                $materializeThrough = $startDate->endOfDay();
            } elseif ($frequency === PlanScheduleFrequency::SelectedDates) {
                $materializeThrough = $this->date(
                    $normalizedSelectedDates[array_key_last($normalizedSelectedDates)],
                    $plan->timezone,
                    'Selected schedule date is invalid.',
                )->endOfDay();
            } elseif ($endDate !== null && $endDate->lessThan($materializeThrough)) {
                $materializeThrough = $endDate->endOfDay();
            }

            $this->materialize->execute($rule, $materializeFrom, $materializeThrough);

            return $rule->fresh(['plan', 'reminders', 'occurrences']);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }

    private function date(string $date, string $timezone, string $message): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($date), $timezone);
        } catch (Throwable) {
            abort(422, $message);
        }

        abort_unless($parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === trim($date), 422, $message);

        return $parsed;
    }
}
