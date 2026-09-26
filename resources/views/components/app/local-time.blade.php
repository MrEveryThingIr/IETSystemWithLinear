@props([
    'value',
])

@php
    $user = request()->user();
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $instant = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)->toIso8601String()
        : (string) $value;
@endphp

<time
    {{ $attributes }}
    datetime="{{ $instant }}"
    data-profile-time="{{ $instant }}"
    data-locale="{{ $locale }}"
    data-timezone="{{ $timezone }}"
>{{ $instant }}</time>
