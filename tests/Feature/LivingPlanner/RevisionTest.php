<?php

namespace Tests\Feature\LivingPlanner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ConfigureExecutionPolicy;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\PersonalExperiment;
use App\Actions\Planner\ReviseLivingSchedule;
use App\Models\Actor;
use App\PlanScheduleFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_adjustment_revises_future_once_without_changing_historical_targets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 12:00:00 UTC'));
        $user = Actor::factory()->create()->user;
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Exercise', timezone: 'UTC');
        app(ConfigureExecutionPolicy::class)->execute($user, $plan, 'flexible');
        $original = app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Daily, '2026-11-07', '09:00', 30);
        $history = $original->occurrences()->whereDate('local_date', '2026-11-07')->sole();
        $service = app(PersonalExperiment::class);
        $experiment = $service->create($user, ['title' => 'Small start', 'hypothesis' => 'An earlier start may help.',
            'selected_change' => 'Start earlier', 'metric' => 'active_minutes', 'starts_on' => '2026-11-08', 'ends_on' => '2026-11-15'], plan: $plan);
        $adjustment = $service->propose($user, $experiment, 'Start at eight.');
        $service->decide($user, $adjustment, true);
        $revision = app(ReviseLivingSchedule::class);
        $revision->execute($user, $plan, 'future', '2026-11-09', '08:00', 20, adjustment: $adjustment);
        $revision->execute($user, $plan, 'future', '2026-11-09', '08:00', 20, adjustment: $adjustment);
        $this->assertSame(2, $plan->scheduleRules()->count());
        $this->assertSame('09:00', $history->fresh()->scheduled_start_at->format('H:i'));
        $this->assertSame('scheduled', $history->fresh()->status->value);
        $this->assertSame('cancelled', $original->fresh()->status->value);
        $this->assertSame(20, $plan->scheduleRules()->where('status', 'active')->sole()->duration_minutes);
        $this->assertSame(1, $plan->events()->where('payload->adjustment_uuid', $adjustment->uuid)->count());
    }

    public function test_single_revision_leaves_recurring_rule_and_other_occurrences_unchanged(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 12:00:00 UTC'));
        $user = Actor::factory()->create()->user;
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Walk', timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Daily, '2026-11-09', '09:00', 30);
        $occurrence = $rule->occurrences()->orderBy('id')->firstOrFail();
        app(ReviseLivingSchedule::class)->execute($user, $plan, 'one', '2026-11-09', '10:00', 20, $occurrence);
        $this->assertSame('active', $rule->fresh()->status->value);
        $this->assertSame('cancelled', $occurrence->fresh()->status->value);
        $this->assertGreaterThan(0, $rule->occurrences()->where('status', 'scheduled')->count());
    }

    public function test_another_actor_cannot_revise_a_private_plan(): void
    {
        $user = Actor::factory()->create()->user;
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Private');
        $this->expectException(AuthorizationException::class);
        app(ReviseLivingSchedule::class)->execute(Actor::factory()->create()->user, $plan, 'future', now()->addDay()->toDateString(), '09:00', 20);
    }
}
