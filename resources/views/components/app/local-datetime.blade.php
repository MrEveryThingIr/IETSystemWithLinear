@props([
    'value',
    'showEquivalent' => true,
    'seconds' => false,
])

@php
    $user = request()->user();
    $timezone = \App\Support\TemporalPreferences::timezoneFor($user);
    $calendar = \App\Support\TemporalPreferences::calendarFor($user)->value;
    $locale = \App\Support\Localization::intlLocale($user?->locale);
    $instant = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance($value)->toIso8601String()
        : (string) $value;
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
    <span data-temporal-primary>{{ $instant }}</span>
    @if ($showEquivalent)
        <span data-temporal-equivalent class="text-[0.72rem] font-normal text-zinc-500" hidden></span>
    @endif
</time>
