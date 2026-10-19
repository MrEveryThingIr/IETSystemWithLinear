<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Vault\Index as VaultIndex;
use App\Models\Actor;
use App\Models\PersonalSecret;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\TemporalCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class PersonalLifeToolsBaselineTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['release.profile' => 'planning_baseline']);
    }

    public function test_vault_encrypts_revealable_values_at_rest_and_hides_them_by_default(): void
    {
        $actor = Actor::factory()->create();

        $component = Livewire::actingAs($actor->user)
            ->test(VaultIndex::class)
            ->set('kind', 'login')
            ->set('title', 'Example service')
            ->set('identifier', 'person@example.test')
            ->set('secret', 'super-secret-value')
            ->set('url', 'https://example.test/login')
            ->set('notes', 'private recovery note')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Example service')
            ->assertDontSee('super-secret-value')
            ->assertDontSee('person@example.test')
            ->assertDontSee('https://example.test/login')
            ->assertDontSee('private recovery note');

        $stored = DB::table('personal_secrets')->sole();

        $this->assertNotSame('person@example.test', $stored->identifier);
        $this->assertNotSame('super-secret-value', $stored->secret);
        $this->assertNotSame('https://example.test/login', $stored->url);
        $this->assertNotSame('private recovery note', $stored->notes);

        $item = PersonalSecret::query()->sole();

        $component
            ->call('toggleReveal', $item->id)
            ->assertSee('super-secret-value')
            ->assertSee('person@example.test')
            ->assertSee('https://example.test/login')
            ->assertSee('private recovery note')
            ->call('toggleReveal', $item->id)
            ->assertDontSee('super-secret-value');
    }

    public function test_another_user_cannot_see_private_vault_records(): void
    {
        $owner = Actor::factory()->create();
        $other = Actor::factory()->create();

        PersonalSecret::query()->create([
            'user_id' => $owner->user->id,
            'kind' => 'login',
            'title' => 'Owner only',
            'identifier' => 'owner@example.test',
            'secret' => 'owner-secret',
        ]);

        Livewire::actingAs($other->user)
            ->test(VaultIndex::class)
            ->assertDontSee('Owner only')
            ->assertDontSee('owner-secret');
    }

    public function test_calendar_display_controls_can_reveal_weekdays_counts_and_plan_titles_without_changing_calendar_truth(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 08:00:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $actor->user->forceFill(['timezone' => 'UTC', 'locale' => 'en'])->save();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);

            $plan = app(CreatePlan::class)->execute(
                $context,
                $actor->user,
                'Dentist',
                timezone: 'UTC',
                metadata: ['planning_studio' => 'baseline'],
            );

            app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $actor->user,
                PlanScheduleFrequency::Once,
                '2026-09-29',
                '17:00',
                60,
                timingMode: PlanTimingMode::Fixed,
            );

            $weekday = TemporalCalendar::format(
                CarbonImmutable::parse('2026-09-29', 'UTC'),
                $actor->user,
                'UTC',
                'EEE',
            );

            Livewire::actingAs($actor->user)
                ->test(PlannerIndex::class)
                ->set('view', 'calendar')
                ->set('month', '2026-09-01')
                ->set('calendarLevel', 'month')
                ->set('calendarDisplayOpen', true)
                ->set('showWeekdayNames', true)
                ->set('showCalendarCounts', true)
                ->set('showCalendarTitles', true)
                ->assertSee($weekday)
                ->assertSee('Dentist')
                ->assertSee(__('planning_baseline.tools.calendar_display.title'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_baseline_routes_admit_money_and_vault(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['money', 'vault']);

        $this->actingAs($actor->user)
            ->get(route('bookkeeping.personal'))
            ->assertOk();

        $this->actingAs($actor->user)
            ->get(route('vault.index'))
            ->assertOk();
    }
}
