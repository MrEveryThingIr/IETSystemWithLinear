<?php

namespace Tests\Feature\LivingPlanner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ComposePlan;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ManagePurposes;
use App\Actions\Planner\ReuseRoutine;
use App\Models\Actor;
use App\Models\Plan;
use App\PlanScheduleFrequency;
use App\Support\Planner\RecurrenceProjection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchy_is_cycle_safe_and_events_are_repeatable_without_duplicates(): void
    {
        $user = Actor::factory()->create()->user;
        $context = app(EnsurePersonalContext::class)->execute($user);
        $parent = app(CreatePlan::class)->execute($context, $user, 'Morning routine');
        $child = app(CreatePlan::class)->execute($context, $user, 'Walk');
        app(ComposePlan::class)->execute($user, $child, $parent);
        app(ComposePlan::class)->execute($user, $child, $parent);
        $this->assertSame($parent->id, $child->fresh()->parent_plan_id);
        $this->assertSame(1, $child->events()->where('event_type', 'structure_changed')->count());
        $this->expectException(HttpException::class);
        app(ComposePlan::class)->execute($user, $parent, $child);
    }

    public function test_private_purposes_link_to_authorized_plans_without_duplicate_contributions(): void
    {
        $user = Actor::factory()->create()->user;
        $other = Actor::factory()->create()->user;
        $context = app(EnsurePersonalContext::class)->execute($user);
        $plan = app(CreatePlan::class)->execute($context, $user, 'Daily exercise');
        $purpose = app(ManagePurposes::class)->create($user, 'Feel stronger', 'A private personal purpose');
        app(ManagePurposes::class)->link($user, $purpose, $plan);
        app(ManagePurposes::class)->link($user, $purpose, $plan);
        $this->assertSame(1, $purpose->plans()->count());
        $this->expectException(HttpException::class);
        app(ManagePurposes::class)->link($other, $purpose, $plan);
    }

    public function test_routine_templates_copy_structure_but_never_execution_history(): void
    {
        $user = Actor::factory()->create()->user;
        $context = app(EnsurePersonalContext::class)->execute($user);
        $parent = app(CreatePlan::class)->execute($context, $user, 'Routine');
        $child = app(CreatePlan::class)->execute($context, $user, 'Read');
        app(ComposePlan::class)->execute($user, $child, $parent);
        $template = app(ReuseRoutine::class)->capture($user, $parent, 'Reusable morning');
        $copy = app(ReuseRoutine::class)->instantiate($user, $template, $context, '2026-10-23');
        $this->assertNotSame($parent->id, $copy->id);
        $this->assertSame('Read', $copy->childPlans()->sole()->title);
        $this->assertSame(0, $copy->occurrences()->count());
        $this->assertSame(4, Plan::query()->count());
        $this->assertArrayNotHasKey('actor_id', $template->composition[0]);
    }

    public function test_year_projection_does_not_materialize_hundreds_of_extra_occurrences(): void
    {
        CarbonImmutable::setTestNow('2026-10-23 08:00 UTC');
        try {
            $user = Actor::factory()->create()->user;
            $context = app(EnsurePersonalContext::class)->execute($user);
            $plan = app(CreatePlan::class)->execute($context, $user, 'Daily reading');
            $rule = app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Daily, '2026-10-23', '09:00', 15);
            $before = $plan->occurrences()->count();
            $summary = app(RecurrenceProjection::class)->summarize($user, $rule, CarbonImmutable::parse('2026-10-23'), CarbonImmutable::parse('2027-10-22'));
            $this->assertSame(365, $summary['expected']);
            $this->assertGreaterThan(200, $summary['virtual']);
            $this->assertSame($before, $plan->occurrences()->count());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
