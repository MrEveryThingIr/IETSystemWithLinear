@props([
    'value',
    'showEquivalent' => true,
])

@php
    $user = request()->user();
    $date = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    $calendarSystem = \App\Support\TemporalPreferences::calendarFor($user);
    $calendar = $calendarSystem->value;
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $dateValue = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)
        : \Carbon\CarbonImmutable::parse($date, 'UTC');
    $primaryLabel = \App\Support\TemporalCalendar::dateLabel($dateValue, $user, 'UTC', $calendarSystem);
    $equivalentLabel = $calendarSystem === \App\CalendarSystem::Gregorian
        ? ''
        : \App\Support\TemporalCalendar::dateLabel($dateValue, $user, 'UTC', \App\CalendarSystem::Gregorian);
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
