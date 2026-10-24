<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Planner\LabelsManager;
use App\Livewire\Planner\Show as PlannerShow;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\Planner\PlannerLabel;
use App\PlanScheduleFrequency;
use App\Support\Planner\PlannerHorizons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_wizard_walks_through_steps_and_creates_a_coloured_labelled_plan_on_several_days(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
        $me = Actor::factory()->create();
        $me->user->forceFill(['timezone' => 'Asia/Tehran', 'locale' => 'fa'])->save();

        $wizard = Livewire::actingAs($me->user)
            ->test(PlannerCreate::class)
            ->assertSet('step', 'what')
            ->assertSeeHtml('data-plan-kind="routine"')
            ->call('next')
            ->assertHasErrors('title')
            ->call('chooseKind', 'focus')
            ->assertSet('durationMinutes', 90)
            ->set('title', 'Write chapter 3')
            ->call('next')
            ->assertSet('step', 'when')
            ->assertSeeHtml('data-time-picker')
            ->assertSeeHtml('data-horizon="next_week"')
            // The visual picker writes these:
            ->set('selectedDays', ['2026-10-22', '2026-10-24'])
            ->set('startsOn', '2026-10-22')
            ->set('startTime', '09:30')
            ->set('durationMinutes', 120)
            ->call('next')
            ->assertSet('step', 'details')
            ->set('newLabelName', 'Book')
            ->call('createLabel')
            ->call('chooseColor', '#14b8a6')
            ->call('toggleReminder', 30)
            ->call('next')
            ->assertSet('step', 'review')
            ->assertSeeHtml('data-wizard-review')
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::query()->with(['occurrences', 'scheduleRules'])->sole();
        $label = PlannerLabel::query()->sole();
        $this->assertSame('Book', $label->name);
        $this->assertSame(['kind' => 'focus', 'label_uuid' => $label->uuid, 'color' => '#14b8a6'], $plan->metadata);
        $this->assertSame(PlanScheduleFrequency::SelectedDates, $plan->scheduleRules->sole()->frequency);
        $this->assertSame(['2026-10-22 09:30', '2026-10-24 09:30'], $plan->occurrences->sortBy('scheduled_start_at')
            ->map(fn ($occurrence) => $occurrence->scheduled_start_at->setTimezone('Asia/Tehran')->format('Y-m-d H:i'))->values()->all());
        $wizard->assertRedirect(route('planner.show', $plan));
    }

    public function test_weekly_repeat_takes_the_weekdays_of_the_chosen_days(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
        $me = Actor::factory()->create();

        Livewire::actingAs($me->user)
            ->test(PlannerCreate::class)
            ->call('chooseKind', 'routine')
            ->set('title', 'Gym')
            ->set('selectedDays', ['2026-10-26', '2026-10-28'])
            ->set('startsOn', '2026-10-26')
            ->call('setRepeat', 'weekly')
            ->assertSet('weekdays', [1, 3])
            ->set('occurrenceLimit', '4')
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::query()->with('occurrences')->sole();
        $this->assertSame(4, $plan->occurrences->count());
        $this->assertSame('routine', $plan->metadata['kind']);
    }

    public function test_horizons_follow_the_viewers_week_and_calendar(): void
    {
        $me = Actor::factory()->create();
        $me->user->forceFill(['timezone' => 'Asia/Tehran', 'locale' => 'fa', 'calendar' => 'persian'])->save();

        $picker = PlannerHorizons::for($me->user, CarbonImmutable::parse('2026-10-21 10:00', 'Asia/Tehran'));

        $this->assertSame(['2026-10-21'], $picker['horizons']['today']['days']);
        $this->assertSame(['2026-10-22'], $picker['horizons']['tomorrow']['days']);
        // A Persian week starts on Saturday: Wed–Fri remain this week.
        $this->assertSame(['2026-10-21', '2026-10-22', '2026-10-23'], $picker['horizons']['this_week']['days']);
        $this->assertSame('2026-10-24', $picker['horizons']['next_week']['days'][0]);
        $this->assertCount(7, $picker['horizons']['next_week']['days']);
        // Aban 1405 starts on 23 October 2026.
        $this->assertSame('2026-10-22', last($picker['horizons']['this_month']['days']));
        $this->assertSame('2026-10-23', $picker['horizons']['next_month']['days'][0]);
        $this->assertGreaterThan(1, count($picker['horizons']['next_month']['weeks']));
    }

    public function test_repeating_plans_appear_once_in_the_list_and_compactly_on_their_page(): void
    {
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
        $me = Actor::factory()->create();
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($me->user), $me->user, 'Morning walk', metadata: ['kind' => 'routine']);
        app(CreatePlanScheduleRule::class)->execute($plan, $me->user, PlanScheduleFrequency::Daily, '2026-10-21', '07:00', 30, occurrenceLimit: 20);

        Livewire::actingAs($me->user)
            ->test(PlannerIndex::class)
            ->set('view', 'list')
            ->assertSeeHtml('data-plan-series="'.$plan->uuid.'"')
            ->assertSee(trans_choice('planner.series.daily', 1))
            ->assertSeeHtml('data-series-more')
            ->call('toggleSeries', $plan->uuid)
            ->assertDontSeeHtml('data-series-more');

        $page = Livewire::actingAs($me->user)->test(PlannerShow::class, ['plan' => $plan])
            ->assertSeeHtml('data-occurrence-group="upcoming"')
            ->assertSeeHtml('data-plan-appearance');
        $this->assertSame(3, substr_count($page->html(), 'wire:key="plan-occurrence-'));

        $page->set('appearanceColor', '#ef4444')->call('saveAppearance');
        $this->assertSame('#ef4444', $plan->fresh()->metadata['color']);
    }

    public function test_labels_are_managed_by_their_owner_only(): void
    {
        $me = Actor::factory()->create();
        $other = Actor::factory()->create();

        $manager = Livewire::actingAs($me->user)->test(LabelsManager::class)
            ->set('newName', 'Clients')->set('newColor', '#ff0000')->call('add');
        $label = PlannerLabel::query()->sole();
        $manager->set("edits.{$label->uuid}.name", 'Customers')->call('saveLabel', $label->uuid);
        $this->assertSame('Customers', $label->fresh()->name);

        // Someone else cannot remove my label: it does not even exist for them.
        Livewire::actingAs($other->user)->test(LabelsManager::class)->call('remove', $label->uuid)->assertNotFound();
        $this->assertNotNull($label->fresh());
    }
}
