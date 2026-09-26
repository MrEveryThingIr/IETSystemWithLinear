@props([
    'value',
    'showEquivalent' => true,
])

@php
    $user = request()->user();
    $calendarSystem = \App\Support\TemporalPreferences::calendarFor($user);
    $calendar = $calendarSystem->value;
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
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex flex-col']) }}
    data-profile-date="{{ $date }}"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-show-equivalent="{{ $showEquivalent ? 'true' : 'false' }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
>
    <span data-temporal-primary>{{ $primaryLabel }}</span>
    @if ($showEquivalent)
        <span data-temporal-equivalent class="text-[0.72rem] font-normal text-zinc-500" @if (! $showEquivalent || $equivalentLabel === '') hidden @endif>{{ $equivalentLabel === '' ? '' : __('ui.profile.temporal.gregorian_equivalent').' · '.$equivalentLabel }}</span>
    @endif
</span>
