<?php

namespace Tests\Feature;

use App\CalendarSystem;
use App\Livewire\Profile\TemporalPreferences as ProfileTemporalPreferences;
use App\Models\User;
use App\Support\TemporalCalendar;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TemporalCompactPresentationTest extends TestCase
{
    use RefreshDatabase;

    private const INSTANT = '2026-10-18 07:00:00 UTC';

    /** @return array<string, array{string, string, ?CalendarSystem, string}> */
    public static function profiles(): array
    {
        return [
            'persian tehran 24h' => ['fa', 'Asia/Tehran', CalendarSystem::Persian, '24h'],
            'gregorian tehran 24h' => ['en', 'Asia/Tehran', CalendarSystem::Gregorian, '24h'],
            'persian toronto 12h' => ['fa', 'America/Toronto', CalendarSystem::Persian, '12h'],
            'gregorian toronto 12h' => ['en', 'America/Toronto', CalendarSystem::Gregorian, '12h'],
        ];
    }

    #[DataProvider('profiles')]
    public function test_ordinary_datetime_labels_carry_no_timezone_suffix(string $locale, string $timezone, CalendarSystem $calendar, string $timeFormat): void
    {
        $user = $this->user($locale, $timezone, $calendar, $timeFormat);
        $label = TemporalCalendar::dateTimeLabel(CarbonImmutable::parse(self::INSTANT), $user);

        foreach (['+3:30', '+03:30', '+۳:۳۰', 'GMT', 'UTC', 'IRST', 'EDT', 'EST', 'Asia/Tehran', 'Toronto'] as $suffix) {
            $this->assertStringNotContainsString($suffix, $label, "[{$label}] must not print a timezone");
        }

        // The timezone still decides the value: 07:00 UTC is 10:30 in Tehran and 03:00 in Toronto.
        $time = TemporalCalendar::timeLabel(CarbonImmutable::parse(self::INSTANT), $user);
        $this->assertStringContainsString($time, $label);
    }

    public function test_timezone_still_shifts_the_rendered_time(): void
    {
        $tehran = $this->user('en', 'Asia/Tehran', CalendarSystem::Gregorian, '24h');
        $toronto = $this->user('en', 'America/Toronto', CalendarSystem::Gregorian, '24h');

        $this->assertStringContainsString('10:30', TemporalCalendar::dateTimeLabel(CarbonImmutable::parse(self::INSTANT), $tehran));
        $this->assertStringContainsString('03:00', TemporalCalendar::dateTimeLabel(CarbonImmutable::parse(self::INSTANT), $toronto));
    }

    public function test_mode_resolution_and_legacy_boolean_compatibility(): void
    {
        $this->assertSame('hover', TemporalPreferences::gregorianEquivalentModeFor(null));

        $legacyOff = new User(['show_gregorian_equivalent' => false]);
        $legacyOn = new User(['show_gregorian_equivalent' => true]);
        $inline = new User(['show_gregorian_equivalent' => true, 'gregorian_equivalent_mode' => 'inline']);

        $this->assertSame('hidden', TemporalPreferences::gregorianEquivalentModeFor($legacyOff));
        $this->assertSame('hover', TemporalPreferences::gregorianEquivalentModeFor($legacyOn));
        $this->assertSame('inline', TemporalPreferences::gregorianEquivalentModeFor($inline));

        $fresh = User::factory()->create()->fresh();
        $this->assertSame('hover', $fresh->gregorian_equivalent_mode);
    }

    public function test_hover_mode_renders_an_accessible_popover_instead_of_a_second_line(): void
    {
        $user = $this->user('fa', 'Asia/Tehran', CalendarSystem::Persian, '24h', 'hover');
        $this->viewAs($user);

        $html = Blade::render('<x-app.local-datetime :value="$value" />', ['value' => CarbonImmutable::parse(self::INSTANT)]);

        $this->assertStringContainsString('data-equivalent-mode="hover"', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('aria-describedby="temporal-', $html);
        $this->assertStringContainsString('Asia/Tehran (UTC+03:30)', $html);
        $this->assertStringContainsString(
            TemporalCalendar::dateTimeLabel(CarbonImmutable::parse(self::INSTANT), $user, null, false, CalendarSystem::Gregorian),
            $html,
        );
        $this->assertStringNotContainsString('text-[0.72rem]', $html);
    }

    public function test_inline_mode_keeps_the_permanent_second_line(): void
    {
        $user = $this->user('fa', 'Asia/Tehran', CalendarSystem::Persian, '24h', 'inline');
        $this->viewAs($user);

        $html = Blade::render('<x-app.local-datetime :value="$value" />', ['value' => CarbonImmutable::parse(self::INSTANT)]);

        $this->assertStringContainsString('data-equivalent-mode="inline"', $html);
        $this->assertStringContainsString('text-[0.72rem]', $html);
        $this->assertStringNotContainsString('role="tooltip"', $html);
        $this->assertStringContainsString(
            __('ui.profile.temporal.gregorian_equivalent'),
            TemporalCalendar::dateLabelWithEquivalent(CarbonImmutable::parse(self::INSTANT), $user),
        );
    }

    public function test_hidden_mode_shows_only_the_primary_calendar(): void
    {
        $user = $this->user('fa', 'Asia/Tehran', CalendarSystem::Persian, '24h', 'hidden');
        $this->viewAs($user);

        $html = Blade::render('<x-app.local-date :value="$value" />', ['value' => '2026-10-18']);

        $this->assertStringContainsString('data-show-equivalent="false"', $html);
        $this->assertStringNotContainsString('data-temporal-equivalent', $html);
        $this->assertStringNotContainsString('role="tooltip"', $html);
    }

    public function test_hover_mode_keeps_compact_labels_for_server_rendered_strings(): void
    {
        $user = $this->user('fa', 'Asia/Tehran', CalendarSystem::Persian, '24h', 'hover');
        $label = TemporalCalendar::dateLabelWithEquivalent(CarbonImmutable::parse(self::INSTANT), $user);

        $this->assertSame(TemporalCalendar::dateLabel(CarbonImmutable::parse(self::INSTANT), $user), $label);
    }

    public function test_profile_saves_the_mode_and_mirrors_the_legacy_boolean(): void
    {
        $user = $this->user('fa', 'Asia/Tehran', CalendarSystem::Persian, '24h', 'hover');

        Livewire::actingAs($user)
            ->test(ProfileTemporalPreferences::class)
            ->assertSet('gregorianEquivalentMode', 'hover')
            ->set('gregorianEquivalentMode', 'inline')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('inline', $user->gregorian_equivalent_mode);
        $this->assertTrue($user->show_gregorian_equivalent);

        Livewire::actingAs($user)
            ->test(ProfileTemporalPreferences::class)
            ->set('gregorianEquivalentMode', 'hidden')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('hidden', $user->gregorian_equivalent_mode);
        $this->assertFalse($user->show_gregorian_equivalent);
    }

    public function test_browser_formatter_matches_server_and_prints_no_timezone_name(): void
    {
        $script = (string) file_get_contents(resource_path('js/temporal.js'));

        $this->assertStringNotContainsString('timeZoneName', $script);
        $this->assertStringContainsString('data-temporal-popover-trigger', $script);
    }

    private function viewAs(User $user): void
    {
        $this->actingAs($user);
        request()->setUserResolver(fn () => $user);
    }

    private function user(string $locale, string $timezone, CalendarSystem $calendar, string $timeFormat, string $mode = 'hover'): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'locale' => $locale,
            'timezone' => $timezone,
            'timezone_mode' => 'fixed',
            'calendar' => $calendar,
            'time_display_format' => $timeFormat,
            'gregorian_equivalent_mode' => $mode,
            'show_gregorian_equivalent' => $mode !== 'hidden',
        ])->save();

        return $user->fresh();
    }
}
