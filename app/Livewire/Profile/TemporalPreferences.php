<?php

namespace App\Livewire\Profile;

use App\CalendarSystem;
use App\Models\User;
use App\TimezoneMode;
use App\Support\Localization;
use App\Support\TemporalPreferences as TemporalPreferenceResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TemporalPreferences extends Component
{
    public string $timezone = 'UTC';

    public string $calendar = 'auto';

    public string $timezoneMode = TimezoneMode::Auto->value;

    public function mount(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->timezone = TemporalPreferenceResolver::timezoneFor($user);
        $this->timezoneMode = $user->timezone_mode->value;
        $this->calendar = (string) ($user->getRawOriginal('calendar') ?: 'auto');
    }

    public function useBrowserTimezone(string $timezone): void
    {
        if (! TemporalPreferenceResolver::validTimezone($timezone)) {
            $this->addError('timezone', __('ui.profile.temporal.invalid_timezone'));

            return;
        }

        $this->resetErrorBag('timezone');
        $this->timezone = $timezone;

        if ($this->timezoneMode === TimezoneMode::Auto->value) {
            $user = request()->user();

            if ($user instanceof User && $user->timezone !== $timezone) {
                $user->timezone = $timezone;
                $user->timezone_mode = TimezoneMode::Auto;
                $user->save();

                $this->dispatch('temporal-preferences-updated');
            }
        }
    }

    public function save(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate([
            'timezone' => ['required', 'timezone'],
            'timezoneMode' => ['required', Rule::enum(TimezoneMode::class)],
            'calendar' => [
                'required',
                Rule::in([
                    'auto',
                    CalendarSystem::Gregorian->value,
                    CalendarSystem::Persian->value,
                    CalendarSystem::IslamicUmmAlQura->value,
                ]),
            ],
        ]);

        $user->timezone = $data['timezone'];
        $user->timezone_mode = TimezoneMode::from($data['timezoneMode']);
        $user->calendar = $data['calendar'] === 'auto'
            ? null
            : CalendarSystem::from($data['calendar']);
        $user->save();

        $this->dispatch('temporal-preferences-updated');
        session()->flash('status', __('ui.profile.temporal.saved'));
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.profile.temporal-preferences', [
            'resolvedCalendar' => TemporalPreferenceResolver::calendarFor($user)->value,
            'defaultCalendar' => Localization::defaultCalendar(),
            'intlLocale' => Localization::intlLocale(),
            'localeName' => Localization::supported()[app()->getLocale()]['native_name'] ?? app()->getLocale(),
            'calendarOptions' => CalendarSystem::cases(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
