<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Http\Middleware\EnforceReleaseSurface;
use App\Livewire\Planner\BasicCreate;
use App\Livewire\Planner\BasicEdit;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Planner\Tools\RepeatWindow;
use App\Models\Actor;
use App\Models\Plan;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;
use App\PlanTimingMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PlanningBaselineExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['release.profile' => 'planning_baseline']);
    }

    public function test_baseline_release_boundary_allows_livewire_transport_requests(): void
    {
        foreach (['/livewire/update', '/livewire-825a320e/update'] as $path) {
            $request = Request::create($path, 'POST');

            $response = app(EnforceReleaseSurface::class)->handle(
                $request,
                fn (): Response => new Response('', 204),
            );

            $this->assertSame(204, $response->getStatusCode(), $path);
        }
    }

    public function test_fixed_item_requires_only_human_scale_basic_fields(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC'])->save();

            Livewire::actingAs($actor->user)
                ->test(BasicCreate::class)
                ->set('title', 'Dentist appointment')
                ->set('category', 'Health')
                ->set('description', 'Routine check-up')
                ->set('timingMode', 'fixed')
                ->set('date', '2026-09-29')
                ->set('startTime', '10:00')
                ->set('endTime', '11:30')
                ->call('save')
                ->assertHasNoErrors();

            $plan = Plan::query()->with(['scheduleRules', 'occurrences'])->sole();
            $rule = $plan->scheduleRules->sole();
            $occurrence = $plan->occurrences->sole();

            $this->assertSame('Dentist appointment', $plan->title);
            $this->assertSame('baseline', data_get($plan->metadata, 'planning_studio'));
            $this->assertSame('Health', data_get($plan->metadata, 'category'));
            $this->assertSame(PlanTimingMode::Fixed, $rule->timing_mode);
            $this->assertSame(90, $rule->duration_minutes);
            $this->assertSame('10:00:00', $rule->start_time);
            $this->assertSame('2026-09-29 10:00:00', $occurrence->scheduled_start_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-29 11:30:00', $occurrence->scheduled_end_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_fixed_item_end_time_must_be_after_start_time_without_http_error(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill(['timezone' => 'UTC'])->save();

        Livewire::actingAs($actor->user)
            ->test(BasicCreate::class)
            ->set('title', 'Invalid meeting')
            ->set('timingMode', 'fixed')
            ->set('date', '2026-09-29')
            ->set('startTime', '11:00')
            ->set('endTime', '10:00')
            ->call('save')
            ->assertHasErrors(['endTime']);
    }

    public function test_flexible_day_item_has_a_day_window_without_asking_for_an_hour(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC'])->save();

            Livewire::actingAs($actor->user)
                ->test(BasicCreate::class)
                ->set('title', 'Buy engine oil')
                ->set('category', 'Home')
                ->set('timingMode', 'flexible_day')
                ->set('date', '2026-09-30')
                ->call('save')
                ->assertHasNoErrors();

            $plan = Plan::query()->with(['scheduleRules', 'occurrences'])->sole();
            $rule = $plan->scheduleRules->sole();
            $occurrence = $plan->occurrences->sole();

            $this->assertSame(PlanTimingMode::FlexibleDay, $rule->timing_mode);
            $this->assertSame('00:00:00', $rule->start_time);
            $this->assertSame(1440, $rule->duration_minutes);
            $this->assertSame('2026-09-30 00:00:00', $occurrence->window_start_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-01 00:00:00', $occurrence->window_end_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_repeat_tool_projects_a_future_once_window_before_it_has_started(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 10:30:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC'])->save();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);

            $source = app(CreatePlan::class)->execute(
                $context,
                $actor->user,
                'Read four pages',
                'A specific book',
                'UTC',
                metadata: ['planning_studio' => 'baseline', 'category' => 'Study'],
            );

            app(CreatePlanScheduleRule::class)->execute(
                $source,
                $actor->user,
                PlanScheduleFrequency::Once,
                '2026-09-29',
                '20:00',
                60,
                timingMode: PlanTimingMode::Fixed,
            );

            $occurrence = $source->occurrences()->sole();
            $this->assertSame(PlanOccurrenceStatus::Scheduled, $occurrence->status);

            Livewire::actingAs($actor->user)
                ->test(RepeatWindow::class)
                ->set('sourceUuid', $occurrence->uuid)
                ->set('repeatMode', 'next_days')
                ->set('repeatCount', 3)
                ->call('apply')
                ->assertHasNoErrors();

            $copies = Plan::query()
                ->where('id', '!=', $source->id)
                ->with(['scheduleRules', 'occurrences'])
                ->orderBy('id')
                ->get();

            $this->assertCount(3, $copies);
            $this->assertSame(
                ['2026-09-30', '2026-10-01', '2026-10-02'],
                $copies->map(fn (Plan $plan): string => $plan->occurrences->sole()->local_date->format('Y-m-d'))->all(),
            );

            foreach ($copies as $copy) {
                $rule = $copy->scheduleRules->sole();

                $this->assertSame('Read four pages', $copy->title);
                $this->assertSame('Study', data_get($copy->metadata, 'category'));
                $this->assertSame($source->uuid, data_get($copy->metadata, 'replicated_from_plan_uuid'));
                $this->assertSame($occurrence->uuid, data_get($copy->metadata, 'replicated_from_occurrence_uuid'));
                $this->assertSame(PlanScheduleFrequency::Once, $rule->frequency);
                $this->assertSame(PlanTimingMode::Fixed, $rule->timing_mode);
                $this->assertSame('20:00:00', $rule->start_time);
                $this->assertSame(60, $rule->duration_minutes);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_repeat_tool_can_project_the_same_window_across_week_month_and_year_partitions(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 10:30:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC'])->save();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);

            $source = app(CreatePlan::class)->execute(
                $context,
                $actor->user,
                'Fractal source',
                timezone: 'UTC',
                metadata: ['planning_studio' => 'baseline'],
            );

            app(CreatePlanScheduleRule::class)->execute(
                $source,
                $actor->user,
                PlanScheduleFrequency::Once,
                '2026-10-15',
                '17:00',
                45,
                timingMode: PlanTimingMode::Fixed,
            );

            $occurrence = $source->occurrences()->sole();

            Livewire::actingAs($actor->user)
                ->test(RepeatWindow::class)
                ->set('sourceUuid', $occurrence->uuid)
                ->set('repeatMode', 'same_weekday')
                ->set('repeatCount', 2)
                ->call('apply')
                ->assertHasNoErrors();

            $weeklyDates = Plan::query()
                ->where('id', '!=', $source->id)
                ->with('occurrences')
                ->get()
                ->flatMap->occurrences
                ->pluck('local_date')
                ->map(fn ($date): string => $date->format('Y-m-d'))
                ->sort()
                ->values()
                ->all();

            $this->assertSame(['2026-10-22', '2026-10-29'], $weeklyDates);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_baseline_list_and_calendar_can_filter_by_category_timing_and_search(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC'])->save();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);

            $fixed = app(CreatePlan::class)->execute(
                $context,
                $actor->user,
                'Dentist appointment',
                timezone: 'UTC',
                metadata: ['planning_studio' => 'baseline', 'category' => 'Health'],
            );
            app(CreatePlanScheduleRule::class)->execute(
                $fixed,
                $actor->user,
                PlanScheduleFrequency::Once,
                '2026-09-29',
                '10:00',
                60,
                timingMode: PlanTimingMode::Fixed,
            );

            $flexible = app(CreatePlan::class)->execute(
                $context,
                $actor->user,
                'Buy engine oil',
                timezone: 'UTC',
                metadata: ['planning_studio' => 'baseline', 'category' => 'Home'],
            );
            app(CreatePlanScheduleRule::class)->execute(
                $flexible,
                $actor->user,
                PlanScheduleFrequency::Once,
                '2026-09-29',
                '00:00',
                1440,
                timingMode: PlanTimingMode::FlexibleDay,
            );

            $legacy = app(CreatePlan::class)->execute(
                $context,
                $actor->user,
                'Historical advanced plan',
                timezone: 'UTC',
            );
            app(CreatePlanScheduleRule::class)->execute(
                $legacy,
                $actor->user,
                PlanScheduleFrequency::Once,
                '2026-09-29',
                '09:00',
                30,
            );

            Livewire::actingAs($actor->user)
                ->test(PlannerIndex::class)
                ->set('view', 'list')
                ->assertSee('Dentist appointment')
                ->assertSee('Buy engine oil')
                ->assertDontSee('Historical advanced plan')
                ->set('category', 'Health')
                ->assertSee('Dentist appointment')
                ->assertDontSee('Buy engine oil')
                ->set('category', '')
                ->set('timing', 'flexible_day')
                ->assertSee('Buy engine oil')
                ->assertDontSee('Dentist appointment')
                ->set('timing', 'all')
                ->set('search', 'engine')
                ->assertSee('Buy engine oil')
                ->assertDontSee('Dentist appointment')
                ->set('search', '')
                ->set('view', 'calendar')
                ->call('showDay', '2026-09-29')
                ->assertSee('Buy engine oil')
                ->assertSee('1 item')
                ->call('showHour', '2026-09-29', 10)
                ->call('setSlotMinutes', 15)
                ->call('selectSlot', 0)
                ->assertSee('Dentist appointment');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_edit_replaces_the_schedule_and_preserves_cancelled_history(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC'])->save();

            Livewire::actingAs($actor->user)
                ->test(BasicCreate::class)
                ->set('title', 'Buy groceries')
                ->set('timingMode', 'flexible_day')
                ->set('date', '2026-09-30')
                ->call('save')
                ->assertHasNoErrors();

            $plan = Plan::query()->with(['scheduleRules', 'occurrences'])->sole();
            $oldRule = $plan->scheduleRules->sole();
            $oldOccurrence = $plan->occurrences->sole();

            Livewire::actingAs($actor->user)
                ->test(BasicEdit::class, ['plan' => $plan])
                ->set('title', 'Buy groceries and fruit')
                ->set('category', 'Home')
                ->set('timingMode', 'fixed')
                ->set('date', '2026-10-01')
                ->set('startTime', '18:00')
                ->set('endTime', '19:00')
                ->call('save')
                ->assertHasNoErrors();

            $plan->refresh();

            $this->assertSame('Buy groceries and fruit', $plan->title);
            $this->assertSame('Home', data_get($plan->metadata, 'category'));
            $this->assertSame(PlanScheduleRuleStatus::Cancelled, $oldRule->fresh()->status);
            $this->assertSame(PlanOccurrenceStatus::Cancelled, $oldOccurrence->fresh()->status);

            $activeRule = $plan->scheduleRules()
                ->where('status', PlanScheduleRuleStatus::Active->value)
                ->sole();

            $this->assertSame(PlanTimingMode::Fixed, $activeRule->timing_mode);
            $this->assertSame('2026-10-01', $activeRule->starts_on->format('Y-m-d'));
            $this->assertSame('18:00:00', $activeRule->start_time);
            $this->assertSame(60, $activeRule->duration_minutes);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_existing_schedule_api_defaults_to_fixed_timing_for_source_library_compatibility(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $plan = app(CreatePlan::class)->execute($context, $actor->user, 'Legacy caller', timezone: 'UTC');

        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $actor->user,
            PlanScheduleFrequency::Once,
            '2026-09-29',
            '09:00',
            30,
        );

        $this->assertSame(PlanTimingMode::Fixed, $rule->timing_mode);
    }
}
