<?php

namespace App\Support;

use App\Models\PlanOccurrence;
use App\Models\PlanReminder;
use App\Models\User;
use App\PlanOccurrenceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class DuePlanReminderEmitter
{
    public function __construct(private readonly NotificationOutboxWriter $outbox) {}

    public function emit(int $limit = 250): int
    {
        $now = CarbonImmutable::now('UTC');
        $limit = max(1, min($limit, 1000));
        $emitted = 0;

        $reminders = PlanReminder::query()
            ->where('status', 'active')
            ->where('channel', 'app')
            ->with(['plan.creator.user', 'plan.participants.actor.user', 'scheduleRule'])
            ->orderBy('id')
            ->get();

        foreach ($reminders as $reminder) {
            $through = $now->addMinutes($reminder->minutes_before);

            $occurrences = PlanOccurrence::query()
                ->where('plan_id', $reminder->plan_id)
                ->when(
                    $reminder->schedule_rule_id !== null,
                    fn ($query) => $query->where('schedule_rule_id', $reminder->schedule_rule_id),
                )
                ->where('status', PlanOccurrenceStatus::Scheduled->value)
                ->where('scheduled_start_at', '>', $now)
                ->where('scheduled_start_at', '<=', $through)
                ->orderBy('scheduled_start_at')
                ->limit($limit)
                ->get();

            foreach ($occurrences as $occurrence) {
                $users = collect([$reminder->plan->creator?->user])
                    ->merge($reminder->plan->participants->pluck('actor.user'))
                    ->filter(fn ($user): bool => $user instanceof User)
                    ->unique('id')
                    ->values();

                foreach ($users as $user) {
                    if ($emitted >= $limit) {
                        return $emitted;
                    }

                    if (! Gate::forUser($user)->allows('participate', $reminder->plan)) {
                        continue;
                    }

                    $this->outbox->request(
                        $user,
                        "plan-reminder:{$reminder->uuid}:{$occurrence->uuid}:{$user->id}",
                        'planner.reminder',
                        [
                            'title_key' => 'notifications.messages.plan_reminder_title',
                            'title_params' => ['title' => $reminder->plan->title],
                            'body_key' => 'notifications.messages.plan_reminder_body',
                            'body_params' => [
                                'time' => $occurrence->scheduled_start_at
                                    ->setTimezone($occurrence->timezone)
                                    ->format('Y-m-d H:i'),
                            ],
                            'url' => route('planner.show', $reminder->plan).'#occurrence-'.$occurrence->uuid,
                        ],
                        context: $reminder->plan->context,
                        subjectType: 'plan_occurrence',
                        subjectUuid: $occurrence->uuid,
                    );

                    $emitted++;
                }
            }
        }

        return $emitted;
    }
}
