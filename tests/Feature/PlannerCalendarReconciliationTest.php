<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\CalendarSystem;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Models\Actor;
use App\Models\Plan;
use App\PlanScheduleFrequency;
use App\Support\TemporalCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerCalendarReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_calendar_boundaries_and_navigation_leave_gregorian_schedule_truth_unchanged(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill([
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'calendar' => CalendarSystem::Persian,
        ])->save();
        $user = $actor->user->fresh();
        $date = CarbonImmutable::parse('2026-09-26', 'Asia/Tehran');

        $this->assertSame('2026-09-23', TemporalCalendar::monthStart($date, $user, 'Asia/Tehran')->toDateString());
        $this->assertSame('2026-03-21', TemporalCalendar::yearStart($date, $user, 'Asia/Tehran')->toDateString());
        $this->assertSame('2026-10-23', TemporalCalendar::nextMonthStart($date, $user, 'Asia/Tehran')->toDateString());

        $plan = app(CreatePlan::class)->execute(
            app(EnsurePersonalContext::class)->execute($user), $user, 'Calendar truth', timezone: 'Asia/Tehran',
        );
        app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Once, '2026-09-26', '10:15', 60);
        $scheduled = $plan->occurrences()->sole()->scheduled_start_at->toISOString();

        Livewire::actingAs($user)->test(PlannerIndex::class)
            ->set('view', 'calendar')
            ->call('showYear', '2026-09-26')
            ->assertSet('year', '2026-03-21')
            ->assertSee('Calendar truth')
            ->call('showMonth', '2026-09-26')
            ->assertSet('month', '2026-09-23')
            ->call('showDay', '2026-09-26')
            ->assertSet('day', '2026-09-26')
            ->assertSee('Calendar truth')
            ->call('showHour', '2026-09-26', 10)
            ->assertSet('hour', 10)
            ->assertSee('Calendar truth')
            ->call('setSlotMinutes', 1)
            ->assertSet('slotMinutes', 1)
            ->assertSee('10:15');

        $this->assertSame($scheduled, $plan->occurrences()->sole()->scheduled_start_at->toISOString());
    }

    public function test_calendar_seeds_are_validated_and_do_not_create_records_until_submit(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill(['timezone' => 'Europe/Berlin'])->save();

        Livewire::actingAs($actor->user)
            ->withQueryParams(['date' => '2026-09-26', 'time' => '10:15', 'duration' => '15'])
            ->test(PlannerCreate::class)
            ->assertSet('startsOn', '2026-09-26')
            ->assertSet('startTime', '10:15')
            ->assertSet('durationMinutes', 15);
        $this->assertDatabaseCount('plans', 0);

        Livewire::actingAs($actor->user)
            ->withQueryParams(['date' => '2026-02-30', 'time' => '25:99', 'duration' => '15junk'])
            ->test(PlannerCreate::class)
            ->assertSet('durationMinutes', 60)
            ->assertNotSet('startsOn', '2026-02-30')
            ->assertNotSet('startTime', '25:99');

        Livewire::actingAs($actor->user)
            ->withQueryParams(['date' => '2026-09-26', 'time' => '10:15', 'duration' => '15'])
            ->test(PlannerCreate::class)
            ->set('title', 'Seeded appointment')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Seeded appointment', Plan::query()->sole()->title);
        $this->assertSame('2026-09-26 10:15', Plan::query()->sole()->occurrences()->sole()
            ->scheduled_start_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'));
    }

    public function test_calendar_rejects_invalid_day_and_hour_and_keeps_context_authorization(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($owner->user);

        Livewire::actingAs($owner->user)->test(PlannerIndex::class)
            ->call('showDay', '2026-02-30')->assertStatus(422);
        Livewire::actingAs($owner->user)->test(PlannerIndex::class)
            ->call('showHour', '2026-09-26', 24)->assertStatus(422);
        Livewire::actingAs($outsider->user)
            ->withQueryParams(['context' => $context->uuid, 'date' => '2026-09-26'])
            ->test(PlannerCreate::class)
            ->assertForbidden();
    }
}
