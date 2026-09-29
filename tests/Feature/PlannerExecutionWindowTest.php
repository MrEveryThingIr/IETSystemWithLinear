<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Actor;
use App\PlanOccurrenceStatus;
use App\PlanOccurrenceWindowState;
use App\PlanScheduleFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlannerExecutionWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_requires_the_window_and_completion_requires_a_recorded_start(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 05:30:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);
            $plan = app(CreatePlan::class)->execute($context, $actor->user, 'Study', timezone: 'Europe/Berlin');
            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan, $actor->user, PlanScheduleFrequency::Once, '2026-09-25', '08:00', 60,
                windowBeforeMinutes: 15, windowAfterMinutes: 10,
            );
            $occurrence = $rule->occurrences()->sole();
            $transition = app(TransitionPlanOccurrence::class);

            $this->assertSame(PlanOccurrenceWindowState::Upcoming, $occurrence->windowState());
            $this->assertFalse($occurrence->canStart());
            $this->assertRejected(fn () => $transition->start($occurrence, $actor->user));
            $this->assertNull($occurrence->fresh()->actual_start_at);

            CarbonImmutable::setTestNow('2026-09-25 05:45:00 UTC');
            $this->assertSame(PlanOccurrenceWindowState::Ready, $occurrence->windowState());
            $this->assertRejected(fn () => $transition->complete($occurrence, $actor->user));
            $this->assertNull($occurrence->fresh()->actual_start_at);

            CarbonImmutable::setTestNow('2026-09-25 07:05:00 UTC');
            $this->assertSame(PlanOccurrenceWindowState::Late, $occurrence->windowState());
            $occurrence = $transition->start($occurrence, $actor->user);
            $this->assertSame(PlanOccurrenceStatus::InProgress, $occurrence->status);
            $this->assertSame('2026-09-25 07:05:00', $occurrence->actual_start_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertSame(PlanOccurrenceWindowState::InProgress, $occurrence->windowState());

            CarbonImmutable::setTestNow('2026-09-25 07:15:00 UTC');
            $occurrence = $transition->complete($occurrence, $actor->user);
            $this->assertSame('2026-09-25 07:15:00', $occurrence->actual_end_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertSame(PlanOccurrenceWindowState::Completed, $occurrence->windowState());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_missed_window_does_not_fabricate_an_actual_start(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 07:11:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);
            $plan = app(CreatePlan::class)->execute($context, $actor->user, 'Study', timezone: 'Europe/Berlin');
            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan, $actor->user, PlanScheduleFrequency::Once, '2026-09-25', '08:00', 60,
                windowBeforeMinutes: 15, windowAfterMinutes: 10,
            );
            $occurrence = $rule->occurrences()->sole();

            $this->assertSame(PlanOccurrenceWindowState::Missed, $occurrence->windowState());
            $this->assertRejected(fn () => app(TransitionPlanOccurrence::class)->start($occurrence, $actor->user));
            $this->assertSame(PlanOccurrenceStatus::Scheduled, $occurrence->fresh()->status);
            $this->assertNull($occurrence->fresh()->actual_start_at);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function assertRejected(callable $attempt): void
    {
        try {
            $attempt();
            $this->fail('A forbidden transition succeeded.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }
}
