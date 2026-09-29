<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\PlanScheduleRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerCalendarVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_rows_before_display_limit_cannot_hide_a_later_visible_occurrence(): void
    {
        $owner = Actor::factory()->create();
        $visitor = Actor::factory()->create();
        $privateContext = app(EnsurePersonalContext::class)->execute($owner->user);
        $visitorContext = app(EnsurePersonalContext::class)->execute($visitor->user);
        $privatePlan = Plan::factory()->create([
            'context_id' => $privateContext->id,
            'created_by_actor_id' => $owner->id,
            'title' => 'Owner-only activity',
        ]);
        $privateRule = PlanScheduleRule::factory()->create([
            'plan_id' => $privatePlan->id,
            'created_by_actor_id' => $owner->id,
        ]);
        $start = CarbonImmutable::parse('2026-09-26 00:00:00', 'UTC');

        PlanOccurrence::factory()->count(501)->sequence(fn ($sequence): array => [
            'scheduled_start_at' => $start->addMinutes($sequence->index),
            'scheduled_end_at' => $start->addMinutes($sequence->index + 30),
            'window_start_at' => $start->addMinutes($sequence->index),
            'window_end_at' => $start->addMinutes($sequence->index + 30),
        ])->create([
            'plan_id' => $privatePlan->id,
            'schedule_rule_id' => $privateRule->id,
            'local_date' => '2026-09-26',
            'timezone' => 'UTC',
        ]);

        $visiblePlan = Plan::factory()->create([
            'context_id' => $visitorContext->id,
            'created_by_actor_id' => $visitor->id,
            'title' => 'Visible appointment',
        ]);
        $visibleRule = PlanScheduleRule::factory()->create([
            'plan_id' => $visiblePlan->id,
            'created_by_actor_id' => $visitor->id,
        ]);
        $visibleOccurrence = PlanOccurrence::factory()->create([
            'plan_id' => $visiblePlan->id,
            'schedule_rule_id' => $visibleRule->id,
            'local_date' => '2026-09-27',
            'scheduled_start_at' => '2026-09-27 09:00:00',
            'scheduled_end_at' => '2026-09-27 10:00:00',
            'window_start_at' => '2026-09-27 09:00:00',
            'window_end_at' => '2026-09-27 10:00:00',
            'timezone' => 'UTC',
        ]);

        Livewire::actingAs($visitor->user)->test(PlannerIndex::class)
            ->set('view', 'calendar')
            ->call('showMonth', '2026-09-27')
            ->assertSee('#occurrence-'.$visibleOccurrence->uuid)
            ->assertDontSee('Owner-only activity');
    }
}
