<?php

namespace Tests\Feature\LivingPlanner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ConfigureExecutionPolicy;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ExecuteLivingOccurrence;
use App\Actions\Planner\ReconcileOccurrenceWindows;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Actor;
use App\Models\Planner\ExecutionSegment;
use App\Models\PlanParticipant;
use App\PlanScheduleFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExecutionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $style = 'hard'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'));
        $user = Actor::factory()->create()->user;
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Commitment', timezone: 'UTC');
        app(ConfigureExecutionPolicy::class)->execute($user, $plan, $style);
        $rule = app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Once, '2026-11-05', '09:00', 60, windowAfterMinutes: 60);

        return [$user, $rule->occurrences()->sole()];
    }

    public function test_pause_resume_counts_active_seconds_and_retry_never_duplicates_execution(): void
    {
        [$user, $occurrence] = $this->fixture();
        $act = app(ExecuteLivingOccurrence::class);
        $act->execute($user, $occurrence, 'start', 'start-retry-1');
        $act->execute($user, $occurrence, 'start', 'start-retry-1');
        $this->travel(10)->minutes();
        $act->execute($user, $occurrence, 'pause', 'pause-retry-1');
        $this->travel(5)->minutes();
        $act->execute($user, $occurrence, 'resume', 'resume-retry-1');
        $this->travel(10)->minutes();
        $result = $act->execute($user, $occurrence, 'finish', 'finish-retry-1', ['outcome' => 'partial']);
        $this->assertSame(1200, $result->active_seconds);
        $this->assertSame('partial', $result->evaluated_outcome);
        $this->assertSame('self_reported', $result->evidence_confidence);
        $this->assertSame('09:00', $result->scheduled_start_at->format('H:i'));
        $this->assertSame(2, ExecutionSegment::query()->count());
        $this->assertSame(1, $occurrence->events()->where('event_type', 'started')->count());
    }

    public function test_scheduler_catches_up_without_claiming_missed_and_records_effective_time_once(): void
    {
        [$user, $occurrence] = $this->fixture();
        $this->travel(3)->days();
        $this->assertSame(1, app(ReconcileOccurrenceWindows::class)->execute());
        $this->assertSame(0, app(ReconcileOccurrenceWindows::class)->execute());
        $this->assertSame('unconfirmed', $occurrence->fresh()->evaluated_outcome);
        $event = $occurrence->events()->where('event_type', 'window_closed')->sole();
        $this->assertNull($event->actor_id);
        $this->assertTrue($event->effective_at->equalTo($occurrence->window_end_at));
        $this->assertTrue($event->created_at->gt($event->effective_at));
        $result = app(ExecuteLivingOccurrence::class)->execute($user, $occurrence, 'report', 'late-report-1', [
            'outcome' => 'completed', 'actual_start_at' => '2026-11-05T09:10:00Z', 'actual_end_at' => '2026-11-05T09:50:00Z',
        ]);
        $this->assertSame('completed', $result->evaluated_outcome);
        $this->assertSame(2400, $result->active_seconds);
    }

    public function test_hard_windows_reject_late_start_but_flexible_policy_remains_actionable(): void
    {
        [$user, $occurrence] = $this->fixture('flexible');
        $this->travel(2)->hours();
        $result = app(ExecuteLivingOccurrence::class)->execute($user, $occurrence, 'start', 'late-start-1');
        $this->assertSame('running', $result->execution_phase);
        [$user2, $hard] = $this->fixture();
        $this->travel(2)->hours();
        $this->expectException(HttpException::class);
        app(TransitionPlanOccurrence::class)->start($hard, $user2);
    }

    public function test_corrections_preserve_reports_and_require_the_latest_event(): void
    {
        [$user, $occurrence] = $this->fixture();
        $this->travel(2)->hours();
        $act = app(ExecuteLivingOccurrence::class);
        $act->execute($user, $occurrence, 'report', 'report-original', ['outcome' => 'missed']);
        $first = $occurrence->events()->where('event_type', 'outcome_reported')->sole();
        $result = $act->execute($user, $occurrence, 'correct', 'correct-report-1', [
            'outcome' => 'partial', 'actual_start_at' => '2026-11-05T09:10:00Z', 'actual_end_at' => '2026-11-05T09:25:00Z',
            'reason' => 'Found my actual activity record', 'supersedes' => $first->uuid,
        ]);
        $this->assertSame('partial', $result->evaluated_outcome);
        $this->assertSame('missed', $first->fresh()->payload['outcome']);
        $this->assertSame($first->uuid, $occurrence->events()->where('event_type', 'outcome_corrected')->sole()->payload['supersedes']);
        $this->expectException(HttpException::class);
        $act->execute($user, $occurrence, 'correct', 'correct-stale-1', ['outcome' => 'missed', 'reason' => 'Stale edit', 'supersedes' => $first->uuid]);
    }

    public function test_one_participant_cannot_report_completion_for_the_entire_group(): void
    {
        [$user, $occurrence] = $this->fixture();
        $other = Actor::factory()->create();
        PlanParticipant::query()->create(['plan_id' => $occurrence->plan_id, 'actor_id' => $other->id, 'assigned_by_actor_id' => $user->actor->id, 'role' => 'participant', 'status' => 'active']);
        $act = app(ExecuteLivingOccurrence::class);
        $act->execute($user, $occurrence, 'start', 'group-start-1');
        $this->travel(20)->minutes();
        $result = $act->execute($user, $occurrence, 'finish', 'group-finish-1');
        $this->assertSame('unconfirmed', $result->evaluated_outcome);
        $this->assertNull($result->actual_end_at);
        $this->assertSame('in_progress', $result->status->value);
    }

    public function test_new_policy_versions_do_not_rewrite_existing_rule_or_occurrence(): void
    {
        [$user, $occurrence] = $this->fixture();
        app(ConfigureExecutionPolicy::class)->execute($user, $occurrence->plan, 'flexible', 7);
        $this->assertSame('hard', $occurrence->fresh()->policy_snapshot['window_style']);
        $this->assertSame(1, $occurrence->fresh()->policy_snapshot['version']);
        $this->expectException(HttpException::class);
        $act = app(ExecuteLivingOccurrence::class);
        $act->execute($user, $occurrence, 'acknowledge', 'identical-key-1');
        $act->execute($user, $occurrence, 'attend', 'identical-key-1');
    }
}
