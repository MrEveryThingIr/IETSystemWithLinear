@props([
    'value',
    'showEquivalent' => null,
])

@php
    $user = request()->user();
    $calendarSystem = \App\Support\TemporalPreferences::calendarFor($user);
    $calendar = $calendarSystem->value;
    $dateFormat = \App\Support\TemporalPreferences::dateFormatFor($user);
    $equivalentMode = match ($showEquivalent) {
        true => 'inline',
        false => 'hidden',
        default => \App\Support\TemporalPreferences::gregorianEquivalentModeFor($user),
    };
    $showEquivalent = $equivalentMode !== 'hidden';
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    // This component represents a civil date, not an instant. Preserve the
    // supplied Y-m-d exactly so changing timezone can never move it a day.
    $displayTimezone = 'UTC';
    $date = $value instanceof \DateTimeInterface
        ? $value->format('Y-m-d')
        : (string) $value;
    $dateValue = \Carbon\CarbonImmutable::parse($date, 'UTC');
    $primaryLabel = \App\Support\TemporalCalendar::dateLabel($dateValue, $user, $displayTimezone, $calendarSystem);
    $equivalentLabel = $calendarSystem === \App\CalendarSystem::Gregorian
        ? ''
        : \App\Support\TemporalCalendar::dateLabel($dateValue, $user, $displayTimezone, \App\CalendarSystem::Gregorian);
    // A civil date carries no timezone, so the popover only exists when there is an equivalent to show.
    $popover = $equivalentMode === 'hover' && $equivalentLabel !== '';
    $popoverId = 'temporal-'.\Illuminate\Support\Str::random(8);
@endphp

<span
    {{ $attributes->merge(['class' => $popover ? 'group/temporal relative inline-flex flex-col' : 'inline-flex flex-col']) }}
    data-profile-date="{{ $date }}"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-date-format="{{ $dateFormat }}"
    data-show-equivalent="{{ $showEquivalent ? 'true' : 'false' }}"
    data-equivalent-mode="{{ $equivalentMode }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
    @if ($popover) tabindex="0" data-temporal-popover-trigger aria-describedby="{{ $popoverId }}" @endif
>
    <span data-temporal-primary @class(['cursor-help decoration-dotted underline-offset-4 group-hover/temporal:underline group-focus/temporal:underline' => $popover])>{{ $primaryLabel }}</span>
    @if ($popover)
        <span id="{{ $popoverId }}" role="tooltip" data-temporal-popover class="pointer-events-none absolute start-0 top-full z-40 mt-1 hidden w-max max-w-xs rounded-md bg-zinc-900 px-2.5 py-1.5 text-start text-xs font-normal leading-5 text-white shadow-lg group-hover/temporal:block group-focus/temporal:block group-data-[temporal-open]/temporal:block dark:bg-zinc-100 dark:text-zinc-900">
            <span data-temporal-equivalent class="block">{{ __('ui.profile.temporal.gregorian_equivalent').' · '.$equivalentLabel }}</span>
        </span>
    @elseif ($showEquivalent)
        <span data-temporal-equivalent class="text-[0.72rem] font-normal text-zinc-500" @if ($equivalentLabel === '') hidden @endif>{{ $equivalentLabel === '' ? '' : __('ui.profile.temporal.gregorian_equivalent').' · '.$equivalentLabel }}</span>
    @endif
</span>
