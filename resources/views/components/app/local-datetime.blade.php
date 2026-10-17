@props([
    'value',
    'showEquivalent' => null,
    'seconds' => false,
])

@php
    $user = request()->user();
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
    $calendarSystem = \App\Support\TemporalPreferences::calendarFor($user);
    $calendar = $calendarSystem->value;
    $dateFormat = \App\Support\TemporalPreferences::dateFormatFor($user);
    $timeFormat = \App\Support\TemporalPreferences::timeFormatFor($user);
    $equivalentMode = match ($showEquivalent) {
        true => 'inline',
        false => 'hidden',
        default => \App\Support\TemporalPreferences::gregorianEquivalentModeFor($user),
    };
    $showEquivalent = $equivalentMode !== 'hidden';
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $instantDate = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)
        : \Carbon\CarbonImmutable::parse((string) $value);
    $instant = $instantDate->toIso8601String();
    $primaryLabel = \App\Support\TemporalCalendar::dateTimeLabel($instantDate, $user, $timezone, $seconds, $calendarSystem);
    $equivalentLabel = $calendarSystem === \App\CalendarSystem::Gregorian
        ? ''
        : \App\Support\TemporalCalendar::dateTimeLabel($instantDate, $user, $timezone, $seconds, \App\CalendarSystem::Gregorian);
    $timezoneDescription = \App\Support\TemporalPreferences::timezoneDescription($timezone, $instantDate);
    $popover = $equivalentMode === 'hover';
    $popoverId = 'temporal-'.\Illuminate\Support\Str::random(8);
@endphp

<time
    {{ $attributes->merge(['class' => $popover ? 'group/temporal relative inline-flex flex-col' : 'inline-flex flex-col']) }}
    datetime="{{ $instant }}"
    data-profile-datetime="{{ $instant }}"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-timezone="{{ $timezone }}"
    data-date-format="{{ $dateFormat }}"
    data-time-format="{{ $timeFormat }}"
    data-show-equivalent="{{ $showEquivalent ? 'true' : 'false' }}"
    data-equivalent-mode="{{ $equivalentMode }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
    data-seconds="{{ $seconds ? 'true' : 'false' }}"
    title="{{ $timezoneDescription }}"
    @if ($popover) tabindex="0" data-temporal-popover-trigger aria-describedby="{{ $popoverId }}" @endif
>
    <span data-temporal-primary @class(['cursor-help decoration-dotted underline-offset-4 group-hover/temporal:underline group-focus/temporal:underline' => $popover])>{{ $primaryLabel }}</span>
    @if ($popover)
        <span id="{{ $popoverId }}" role="tooltip" data-temporal-popover class="pointer-events-none absolute start-0 top-full z-40 mt-1 hidden w-max max-w-xs rounded-md bg-zinc-900 px-2.5 py-1.5 text-start text-xs font-normal leading-5 text-white shadow-lg group-hover/temporal:block group-focus/temporal:block group-data-[temporal-open]/temporal:block dark:bg-zinc-100 dark:text-zinc-900">
            <span data-temporal-equivalent class="block" @if ($equivalentLabel === '') hidden @endif>{{ $equivalentLabel === '' ? '' : __('ui.profile.temporal.gregorian_equivalent').' · '.$equivalentLabel }}</span>
            <span class="block opacity-75">{{ $timezoneDescription }}</span>
        </span>
    @elseif ($showEquivalent)
        <span data-temporal-equivalent class="text-[0.72rem] font-normal text-zinc-500" @if ($equivalentLabel === '') hidden @endif>{{ $equivalentLabel === '' ? '' : __('ui.profile.temporal.gregorian_equivalent').' · '.$equivalentLabel }}</span>
    @endif
</time>
