@props([
    'value',
    'showEquivalent' => true,
    'seconds' => false,
])

@php
    $user = request()->user();
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
    $calendarSystem = \App\Support\TemporalPreferences::calendarFor($user);
    $calendar = $calendarSystem->value;
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $instantDate = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)
        : \Carbon\CarbonImmutable::parse((string) $value);
    $instant = $instantDate->toIso8601String();
    $primaryLabel = \App\Support\TemporalCalendar::dateTimeLabel($instantDate, $user, $timezone, $seconds, $calendarSystem);
    $equivalentLabel = $calendarSystem === \App\CalendarSystem::Gregorian
        ? ''
        : \App\Support\TemporalCalendar::dateTimeLabel($instantDate, $user, $timezone, $seconds, \App\CalendarSystem::Gregorian);
@endphp

<time
    {{ $attributes->merge(['class' => 'inline-flex flex-col']) }}
    datetime="{{ $instant }}"
    data-profile-datetime="{{ $instant }}"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-timezone="{{ $timezone }}"
    data-show-equivalent="{{ $showEquivalent ? 'true' : 'false' }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
    data-seconds="{{ $seconds ? 'true' : 'false' }}"
>
    <span data-temporal-primary>{{ $primaryLabel }}</span>
    @if ($showEquivalent)
        <span data-temporal-equivalent class="text-[0.72rem] font-normal text-zinc-500" @if (! $showEquivalent || $equivalentLabel === '') hidden @endif>{{ $equivalentLabel === '' ? '' : __('ui.profile.temporal.gregorian_equivalent').' · '.$equivalentLabel }}</span>
    @endif
</time>
