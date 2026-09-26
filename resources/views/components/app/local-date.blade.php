@props([
    'value',
    'showEquivalent' => true,
])

@php
    $user = request()->user();
    $date = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    $calendar = \App\Support\TemporalPreferences::calendarFor($user)->value;
    $locale = \App\Support\Localization::intlLocale($user?->locale);
@endphp

<span
    {{ $attributes->merge(['class' => 'inline-flex flex-col']) }}
    data-profile-date="{{ $date }}"
    data-locale="{{ $locale }}"
    data-calendar="{{ $calendar }}"
    data-show-equivalent="{{ $showEquivalent ? 'true' : 'false' }}"
    data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
>
    <span data-temporal-primary>{{ $date }}</span>
    @if ($showEquivalent)
        <span data-temporal-equivalent class="text-[0.72rem] font-normal text-zinc-500" hidden></span>
    @endif
</span>
