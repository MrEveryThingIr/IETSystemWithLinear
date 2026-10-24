<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanScheduleRule;
use App\Models\PlanTemplate;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Saves, renames and archives schedule templates. Only the schedule's shape is copied. */
class SavePlanTemplate
{
    public function __construct(private readonly PartyAuthority $authority) {}

    public function fromPlan(User $user, Plan $plan, string $name, Party $owner): PlanTemplate
    {
        Gate::forUser($user)->authorize('view', $plan);
        abort_unless($this->authority->canActFor($user, $owner), 403);

        $rule = PlanScheduleRule::query()->where('plan_id', $plan->id)->where('status', 'active')->with('reminders')->latest('id')->first();
        if (! $rule instanceof PlanScheduleRule) {
            throw ValidationException::withMessages(['template' => __('work.templates.no_schedule')]);
        }
        if (! in_array($rule->frequency, PlanTemplate::FREQUENCIES, true)) {
            throw ValidationException::withMessages(['template' => __('work.templates.dates_not_reusable')]);
        }

        return $this->create($user, $owner, $name, [
            'frequency' => $rule->frequency,
            'timing_mode' => $rule->timing_mode ?? PlanTimingMode::Fixed,
            'interval' => (int) $rule->interval,
            'start_time' => substr((string) $rule->start_time, 0, 5),
            'duration_minutes' => (int) $rule->duration_minutes,
            'weekdays' => $rule->frequency === PlanScheduleFrequency::Weekly ? array_values(array_map('intval', (array) $rule->weekdays)) : null,
            'month_day' => $rule->frequency === PlanScheduleFrequency::Monthly ? $rule->month_day : null,
            'occurrence_limit' => $rule->occurrence_limit,
            'window_before_minutes' => (int) $rule->window_before_minutes,
            'window_after_minutes' => (int) $rule->window_after_minutes,
            'reminder_offsets' => $rule->reminders->where('status', 'active')->pluck('minutes_before')->map(fn ($m): int => (int) $m)->sort()->values()->all(),
            'source_plan_uuid' => $plan->uuid,
        ]);
    }

    /** @param array{frequency: PlanScheduleFrequency, timing_mode: PlanTimingMode, interval: int, start_time: string, duration_minutes: int, weekdays: list<int>|null, month_day: int|null, occurrence_limit: int|null, window_before_minutes: int, window_after_minutes: int, reminder_offsets: list<int>, source_plan_uuid: string|null} $shape */
    public function create(User $user, Party $owner, string $name, array $shape): PlanTemplate
    {
        abort_unless($this->authority->canActFor($user, $owner), 403);
        $name = $this->name($name);
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor, 403);

        $columns = $owner->columns('owner');

        return DB::transaction(fn (): PlanTemplate => PlanTemplate::query()->create([
            'owner_actor_id' => $columns['owner_actor_id'],
            'owner_business_id' => $columns['owner_business_id'],
            'owner_group_id' => $columns['owner_group_id'],
            ...$shape,
            'name' => $name,
            'status' => 'active',
            'created_by_actor_id' => $actor->id,
        ]));
    }

    public function rename(User $user, PlanTemplate $template, string $name): void
    {
        abort_unless($this->authority->canActFor($user, $template->owner()), 403);
        $template->update(['name' => $this->name($name)]);
    }

    public function archive(User $user, PlanTemplate $template): void
    {
        abort_unless($this->authority->canActFor($user, $template->owner()), 403);
        $template->update(['status' => 'archived']);
    }

    private function name(string $name): string
    {
        $name = Str::squish($name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['templateName' => __('work.templates.name_required')]);
        }

        return $name;
    }
}
