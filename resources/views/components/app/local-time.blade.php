@props([
    'value',
])

@php
    $user = request()->user();
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $instantDate = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)
        : \Carbon\CarbonImmutable::parse((string) $value);
    $instant = $instantDate->toIso8601String();
    $label = \App\Support\TemporalCalendar::timeLabel($instantDate, $user, $timezone);
@endphp

<time
    {{ $attributes }}
    datetime="{{ $instant }}"
    data-profile-time="{{ $instant }}"
    data-locale="{{ $locale }}"
    data-timezone="{{ $timezone }}"
>{{ $label }}</time>
