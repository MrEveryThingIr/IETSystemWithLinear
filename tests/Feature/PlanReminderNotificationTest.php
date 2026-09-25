<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\PlanParticipant;
use App\Models\PlanReminder;
use App\Models\PlanScheduleRule;
use App\Support\DuePlanReminderEmitter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PlanReminderNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_due_app_reminder_requests_one_deduplicated_notification_per_participant(): void
    {
        Bus::fake();
        $owner = Actor::factory()->create();
        $participant = Actor::factory()->create();
        $plan = Plan::factory()->create([
            'created_by_actor_id' => $owner->id,
            'title' => 'Riverside inspection',
            'timezone' => 'UTC',
        ]);
        PlanParticipant::factory()->create([
            'plan_id' => $plan->id,
            'actor_id' => $participant->id,
            'assigned_by_actor_id' => $owner->id,
            'status' => 'active',
        ]);
        $rule = PlanScheduleRule::factory()->create(['plan_id' => $plan->id]);
        $occurrence = PlanOccurrence::factory()->create([
            'plan_id' => $plan->id,
            'schedule_rule_id' => $rule->id,
            'local_date' => now('UTC')->toDateString(),
            'scheduled_start_at' => now('UTC')->addMinutes(20),
            'scheduled_end_at' => now('UTC')->addMinutes(80),
            'timezone' => 'UTC',
        ]);
        PlanReminder::factory()->create([
            'plan_id' => $plan->id,
            'schedule_rule_id' => $rule->id,
            'created_by_actor_id' => $owner->id,
            'minutes_before' => 30,
            'channel' => 'app',
            'status' => 'active',
        ]);

        $emitter = app(DuePlanReminderEmitter::class);
        $this->assertSame(2, $emitter->emit());
        $this->assertSame(2, $emitter->emit());

        $this->assertDatabaseCount('notification_outbox', 2);
        $this->assertDatabaseHas('notification_outbox', ['recipient_user_id' => $owner->user->id, 'subject_uuid' => $occurrence->uuid]);
        $this->assertDatabaseHas('notification_outbox', ['recipient_user_id' => $participant->user->id, 'subject_uuid' => $occurrence->uuid]);
    }
}
