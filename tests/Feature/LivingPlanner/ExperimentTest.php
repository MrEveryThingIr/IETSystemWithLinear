<?php

namespace Tests\Feature\LivingPlanner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\PersonalExperiment;
use App\Models\Actor;
use App\Models\Planner\ExperimentObservation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ExperimentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 12:00', 'UTC'));
        $user = Actor::factory()->create()->user;
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Exercise');
        $experiment = app(PersonalExperiment::class)->create($user, [
            'title' => 'Shorter routine', 'hypothesis' => 'A small start may help me follow through',
            'selected_change' => 'Walk after breakfast', 'metric' => 'active_minutes', 'baseline' => 5, 'target' => 15,
            'starts_on' => '2026-11-05', 'ends_on' => '2026-11-11', 'cue' => 'Breakfast finished', 'planned_response' => 'Put on walking shoes',
        ], plan: $plan);

        return [$user, $plan, $experiment, app(PersonalExperiment::class)];
    }

    public function test_optional_observations_compare_periods_without_rewriting_baseline_or_claiming_causation(): void
    {
        [$user, $plan, $experiment, $service] = $this->fixture();
        $service->observe($user, $experiment, 'baseline-observation', ['observed_at' => '2026-11-03T09:00:00Z', 'value' => 5]);
        $original = $service->observe($user, $experiment, 'current-observation', ['observed_at' => '2026-11-06T09:00:00Z', 'value' => 10, 'reflection' => 'Private reflection', 'difficulty' => 2]);
        $corrected = $service->observe($user, $experiment, 'correct-observation', ['observed_at' => '2026-11-06T09:00:00Z', 'value' => 15], $original);
        $this->assertSame($corrected->id, $service->observe($user, $experiment, 'correct-observation', ['observed_at' => '2026-11-06T09:00:00Z', 'value' => 15], $original)->id);
        $insights = $service->insights($user, $experiment);
        $this->assertSame(1, $insights['observed_n']);
        $this->assertSame(10.0, $insights['difference']);
        $this->assertSame('5.000', $experiment->fresh()->baseline);
        $this->assertSame(3, ExperimentObservation::query()->count());
        $this->assertStringContainsString('do not prove causation', $insights['interpretation']);
        $proposal = $service->propose($user, $experiment, 'Try 16 minutes next week');
        $this->assertSame('approved', $service->decide($user, $proposal, true)->decision);
        $this->assertSame('15.000', $experiment->fresh()->target);
        $this->assertSame(0, $plan->scheduleRules()->count());
    }

    public function test_another_actor_cannot_read_or_write_private_experiments(): void
    {
        [$user, $plan, $experiment, $service] = $this->fixture();
        $other = Actor::factory()->create()->user;
        $this->expectException(HttpException::class);
        $service->insights($other, $experiment);
    }
}
