@php
    $user = request()->user();
    $locale = \App\Support\Localization::intlLocale();
    $calendar = \App\Support\TemporalPreferences::calendarFor($user)->value;
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
@endphp

<iet-ambient-status
    class="hidden min-w-0 max-w-xl items-center gap-3 lg:flex"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-timezone="{{ $timezone }}"
>
    <time class="shrink-0 text-xs font-medium tabular-nums text-zinc-600 dark:text-zinc-300" data-ambient-clock></time>
    <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">·</span>
    <span class="min-w-0 truncate text-xs text-zinc-500" data-ambient-message aria-live="polite"></span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.plan') }}</span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.record') }}</span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.review') }}</span>
</iet-ambient-status>
