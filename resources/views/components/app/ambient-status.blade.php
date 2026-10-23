@props(['analog' => false])
@php
    $user = request()->user();
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $calendar = \App\Support\TemporalPreferences::calendarFor($user)->value;
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
    $equivalentMode = \App\Support\TemporalPreferences::gregorianEquivalentModeFor($user);
    $initial = \App\Support\TemporalCalendar::dateTimeLabel(now(), $user, $timezone, true);
    $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
@endphp

<iet-ambient-status
    class="{{ $analog ? 'ambient-expanded' : 'ambient-compact' }} min-w-0 flex-1"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-timezone="{{ $timezone }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
    data-date-format="{{ \App\Support\TemporalPreferences::dateFormatFor($user) }}"
    data-time-format="{{ \App\Support\TemporalPreferences::timeFormatFor($user) }}"
    data-equivalent-mode="{{ $equivalentMode }}"
    data-show-equivalent="{{ $equivalentMode !== 'hidden' ? 'true' : 'false' }}"
    data-pause-label="{{ __('ui.ambient.pause') }}"
    data-resume-label="{{ __('ui.ambient.resume') }}"
    data-fullscreen-label="{{ __('ui.ambient.fullscreen') }}"
    data-exit-fullscreen-label="{{ __('ui.ambient.exit_fullscreen') }}"
    data-fullscreen-unavailable="{{ __('ui.ambient.fullscreen_unavailable') }}"
>
    @if ($analog)
        <svg viewBox="0 0 240 240" class="ambient-face" aria-hidden="true" data-analog-clock>
            <circle cx="120" cy="120" r="116" class="ambient-face-rim" />
            <circle cx="120" cy="120" r="109" class="ambient-face-inner" />
            @for ($tick = 0; $tick < 60; $tick++)
                <line x1="120" y1="{{ $tick % 5 === 0 ? 15 : 18 }}" x2="120" y2="{{ $tick % 5 === 0 ? 25 : 22 }}" transform="rotate({{ $tick * 6 }} 120 120)" class="{{ $tick % 5 === 0 ? 'ambient-tick-major' : 'ambient-tick' }}" />
            @endfor
            @for ($hour = 1; $hour <= 12; $hour++)
                <text x="{{ 120 + 84 * sin(deg2rad($hour * 30)) }}" y="{{ 120 - 84 * cos(deg2rad($hour * 30)) }}" text-anchor="middle" dominant-baseline="central" class="ambient-numeral">{{ $numberFormatter->format($hour) }}</text>
            @endfor
            <text x="120" y="137" text-anchor="middle" class="ambient-face-brand">{{ config('app.name') }}</text>
            <line x1="120" y1="130" x2="120" y2="65" data-clock-hour class="ambient-hand-hour" />
            <line x1="120" y1="137" x2="120" y2="42" data-clock-minute class="ambient-hand-minute" />
            <line x1="120" y1="140" x2="120" y2="31" data-clock-second class="ambient-hand-second" />
            <circle cx="120" cy="120" r="5" class="ambient-hand-cap" />
        </svg>
    @endif
    <span class="ambient-time group/ambient relative min-w-0 tabular-nums" @if($equivalentMode === 'hover' && $calendar !== 'gregory') tabindex="0" data-temporal-popover-trigger @endif>
        <time class="block font-medium text-zinc-600 dark:text-zinc-300" data-ambient-clock datetime="{{ now()->toIso8601String() }}" aria-live="off">{{ $initial }}</time>
        <span class="ambient-equivalent text-zinc-500" data-ambient-equivalent @if($equivalentMode !== 'inline') hidden @endif></span>
    </span>
    <span class="min-w-0 text-xs font-medium text-zinc-500 dark:text-zinc-400" data-ambient-message aria-live="off">{{ __('ui.ambient.messages.plan') }}</span>
    <span class="ambient-controls">
        <button type="button" data-ambient-pause aria-pressed="false" class="ambient-control" hidden>{{ __('ui.ambient.pause') }}</button>
        @if ($analog)
            <button type="button" data-ambient-fullscreen class="ambient-control" hidden>{{ __('ui.ambient.fullscreen') }}</button>
        @endif
    </span>
    @if ($analog)<span role="status" class="text-xs text-zinc-500" data-clock-feedback></span>@endif
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.plan') }}</span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.record') }}</span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.review') }}</span>
</iet-ambient-status>
