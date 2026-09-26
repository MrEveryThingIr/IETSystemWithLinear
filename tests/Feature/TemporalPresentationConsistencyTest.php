<?php

namespace Tests\Feature;

use App\CalendarSystem;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Models\User;
use App\Support\TemporalCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class TemporalPresentationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_persian_profile_calendar_controls_real_calendar_boundaries(): void
    {
        $this->assertTrue(extension_loaded('intl'));

        $user = User::factory()->create([
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'calendar' => CalendarSystem::Persian,
        ]);

        $date = CarbonImmutable::parse('2026-09-26', 'Asia/Tehran');

        $this->assertSame(
            '2026-09-23',
            TemporalCalendar::monthStart($date, $user, 'Asia/Tehran')->toDateString(),
        );
        $this->assertSame(
            '2026-03-21',
            TemporalCalendar::yearStart($date, $user, 'Asia/Tehran')->toDateString(),
        );
        $this->assertNotSame('2026', TemporalCalendar::yearLabel($date, $user, 'Asia/Tehran'));
    }

    public function test_planner_navigation_uses_selected_profile_calendar_not_gregorian_months(): void
    {
        $user = User::factory()->create([
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'calendar' => CalendarSystem::Persian,
        ]);

        $month = CarbonImmutable::parse('2026-09-26', 'Asia/Tehran');

        Livewire::actingAs($user)
            ->test(PlannerIndex::class)
            ->set('view', 'calendar')
            ->call('showMonth', '2026-09-26')
            ->assertSet('month', '2026-09-23')
            ->assertSee(TemporalCalendar::monthLabel($month, $user, 'Asia/Tehran'))
            ->call('showYear', '2026-09-26')
            ->assertSet('year', '2026-03-21')
            ->assertSee(TemporalCalendar::yearLabel($month, $user, 'Asia/Tehran'));
    }

    public function test_authenticated_shell_exposes_profile_calendar_timezone_and_equivalent_clock(): void
    {
        $this->withoutVite();

        $user = User::factory()->create([
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'calendar' => CalendarSystem::Persian,
        ]);
        $user->actor()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-calendar="persian"', false)
            ->assertSee('data-timezone="Asia/Tehran"', false)
            ->assertSee('data-ambient-equivalent', false);
    }

    public function test_date_only_values_never_shift_when_profile_timezone_changes(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
            'timezone' => 'America/Toronto',
            'calendar' => CalendarSystem::Gregorian,
        ]);
        $this->actingAs($user);

        $civilDate = CarbonImmutable::parse('2026-09-26 00:00:00', 'UTC');
        $html = Blade::render('<x-app.local-date :value="$value" />', ['value' => $civilDate]);

        $this->assertStringContainsString('data-profile-date="2026-09-26"', $html);
    }

    public function test_true_instants_are_rendered_in_profile_timezone(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
            'timezone' => 'America/Toronto',
            'calendar' => CalendarSystem::Gregorian,
        ]);

        $instant = CarbonImmutable::parse('2026-09-26T00:00:00Z');

        $this->assertStringContainsString(
            '20:00',
            TemporalCalendar::dateTimeLabel($instant, $user, 'America/Toronto'),
        );
    }

    public function test_user_facing_views_do_not_bypass_profile_calendar_with_native_date_controls(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = $file->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/type\s*=\s*["\'](?:date|datetime-local)["\']/i',
                $contents,
                'Native Gregorian date control remains in '.$file->getRelativePathname(),
            );
        }
    }

    public function test_user_facing_views_do_not_directly_format_gregorian_calendar_dates(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getRelativePathname() === 'components/app/local-date.blade.php') {
                continue;
            }

            $contents = $file->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/->(?:translatedFormat|format)\(\s*["\'][^"\']*(?:Y-m-d|Y\/m\/d|M j|j M|Y-m)[^"\']*["\']/',
                $contents,
                'Direct Gregorian calendar formatting remains in '.$file->getRelativePathname(),
            );
            $this->assertStringNotContainsString(
                '->toDayDateTimeString(',
                $contents,
                'Direct Gregorian date/time rendering remains in '.$file->getRelativePathname(),
            );
        }
    }
}
