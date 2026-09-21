<?php

namespace Tests\Feature;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\CalendarSystem;
use App\Models\Actor;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileItemVisibility;
use App\Support\Localization;
use App\Support\TemporalPreferences;
use App\TimezoneMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporalLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_defaults_keep_language_timezone_and_calendar_separate(): void
    {
        $user = User::factory()->create([
            'timezone' => 'UTC',
            'timezone_mode' => TimezoneMode::Auto,
            'calendar' => null,
        ]);

        app()->setLocale('en');
        $this->assertSame(CalendarSystem::Gregorian, TemporalPreferences::calendarFor($user));

        app()->setLocale('ar');
        $this->assertSame(CalendarSystem::Gregorian, TemporalPreferences::calendarFor($user));

        app()->setLocale('zh_CN');
        $this->assertSame(CalendarSystem::Gregorian, TemporalPreferences::calendarFor($user));

        app()->setLocale('fa');
        $this->assertSame(CalendarSystem::Persian, TemporalPreferences::calendarFor($user));

        $this->assertSame('fa-IR', Localization::intlLocale());
        $this->assertSame(6, Localization::firstDayOfWeek());
        $this->assertSame([6, 7, 1, 2, 3, 4, 5], TemporalPreferences::weekdayOrder());
    }

    public function test_explicit_calendar_and_timezone_preferences_override_locale_defaults(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
            'timezone' => 'America/Toronto',
            'timezone_mode' => TimezoneMode::Fixed,
            'calendar' => CalendarSystem::IslamicUmmAlQura,
        ]);

        app()->setLocale('fa');

        $this->assertSame('America/Toronto', TemporalPreferences::timezoneFor($user));
        $this->assertSame(CalendarSystem::IslamicUmmAlQura, TemporalPreferences::calendarFor($user));

        $user->calendar = CalendarSystem::Persian;
        $user->timezone = 'Asia/Tehran';
        $user->timezone_mode = TimezoneMode::Fixed;
        $user->save();

        $this->assertSame(CalendarSystem::Persian, $user->refresh()->calendar);
        $this->assertSame('Asia/Tehran', TemporalPreferences::timezoneFor($user));
    }

    public function test_blank_optional_temporal_fields_are_persisted_as_null_not_empty_strings(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->timezone = 'Asia/Tehran';
        $actor->user->timezone_mode = TimezoneMode::Fixed;
        $actor->user->save();

        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        $intent = app(CreateActorProfileIntent::class)->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Transportation',
            [
                'title' => 'Transportation',
                'description' => 'Weekly transport need.',
                'schedule_kind' => ProfileIntentScheduleKind::Weekly->value,
                'starts_on' => '',
                'ends_on' => '',
                'timezone' => '',
                'recurrence_interval' => 1,
                'recurrence_weekdays' => [6],
                'recurrence_day_of_month' => '',
                'time_window_start' => '',
                'time_window_end' => '',
                'origin_text' => 'Esfahan',
                'destination_text' => 'Tehran',
                'round_trip' => true,
                'return_after_days' => 1,
                'quantity' => '',
                'unit' => '',
                'visibility' => ProfileItemVisibility::Inherited->value,
            ],
        );

        $this->assertNull($intent->starts_on);
        $this->assertNull($intent->ends_on);
        $this->assertNull($intent->time_window_start);
        $this->assertNull($intent->time_window_end);
        $this->assertNull($intent->recurrence_day_of_month);
        $this->assertNull($intent->quantity);
        $this->assertSame('Asia/Tehran', $intent->timezone);

        $this->assertDatabaseHas('actor_profile_intents', [
            'id' => $intent->id,
            'starts_on' => null,
            'ends_on' => null,
            'time_window_start' => null,
            'time_window_end' => null,
        ]);
    }

    public function test_persian_profile_editor_emits_persian_calendar_picker_metadata(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->locale = 'fa';
        $actor->user->timezone = 'Asia/Tehran';
        $actor->user->timezone_mode = TimezoneMode::Fixed;
        $actor->user->calendar = null;
        $actor->user->save();

        $this->actingAs($actor->user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-calendar="persian"', false)
            ->assertSee('data-locale="fa-IR"', false)
            ->assertSee(__('ui.profile.temporal.calendar_auto', [
                'calendar' => __('ui.profile.temporal.calendars.persian'),
            ]));
    }
}
