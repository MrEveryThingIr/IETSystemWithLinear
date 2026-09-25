@php
    $user = request()->user();
    $timezone = $user instanceof \App\Models\User
        ? \App\Support\TemporalPreferences::timezoneFor($user)
        : 'UTC';
    $calendar = $user instanceof \App\Models\User
        ? \App\Support\TemporalPreferences::calendarFor($user)->value
        : 'gregory';
    $locale = \App\Support\Localization::intlLocale();
    $tips = __('ui.ambient.tips');
    $tips = is_array($tips) ? array_values($tips) : [];
@endphp

<div
    class="hidden min-w-0 items-center gap-3 lg:flex"
    data-ambient-status
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-timezone="{{ $timezone }}"
    data-tips='@json($tips)'
>
    <span class="whitespace-nowrap text-xs font-medium text-zinc-600 dark:text-zinc-300" data-ambient-clock aria-label="{{ __('ui.ambient.clock_label') }}">
        {{ __('ui.profile.temporal.preview_loading') }}
    </span>
    <span class="h-4 w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
    <span class="max-w-72 truncate text-xs text-zinc-500 dark:text-zinc-400" data-ambient-tip></span>
</div>
