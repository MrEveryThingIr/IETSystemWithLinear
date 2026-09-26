@php
    $user = request()->user();
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $calendar = \App\Support\TemporalPreferences::calendarFor($user)->value;
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
@endphp

<iet-ambient-status
    class="hidden min-w-0 max-w-xl items-center gap-3 lg:flex"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-timezone="{{ $timezone }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
>
    <span class="shrink-0 text-xs tabular-nums">
        <time class="block font-medium text-zinc-600 dark:text-zinc-300" data-ambient-clock></time>
        <span class="block text-[0.65rem] text-zinc-500" data-ambient-equivalent hidden></span>
    </span>
    <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">·</span>
    <span class="min-w-0 truncate text-xs text-zinc-500" data-ambient-message aria-live="polite"></span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.plan') }}</span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.record') }}</span>
    <span hidden data-ambient-source>{{ __('ui.ambient.messages.review') }}</span>
</iet-ambient-status>
