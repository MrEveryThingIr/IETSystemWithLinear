@php
    $viewer = auth()->user();
    $tz = $viewerTimezone ?? 'UTC';
@endphp
<span dir="auto">{{ \App\Support\TemporalCalendar::format($slot->starts_at, $viewer, $tz, 'EEEE d MMMM') }}</span>
<span class="whitespace-nowrap" dir="ltr">{{ \App\Support\TemporalCalendar::format($slot->starts_at, $viewer, $tz, 'HH:mm') }}–{{ \App\Support\TemporalCalendar::format($slot->ends_at, $viewer, $tz, 'HH:mm') }}</span>
