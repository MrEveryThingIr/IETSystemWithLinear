{{--
    An analog clock face drawn in a 200×200 box. The clock script turns the
    hands (data-hand) and writes the numerals and the date window in the
    page language. "detailed" adds minute ticks, numerals and the date window.
--}}
@props(['detailed' => false])

@php
    $ticks = [];
    foreach (range(0, 59) as $i) {
        $hour = $i % 5 === 0;
        $quarter = $i % 15 === 0;
        if (! $detailed && ! $quarter) {
            continue;
        }
        $angle = deg2rad($i * 6);
        $outer = $detailed ? 88 : 84;
        $inner = $detailed ? ($quarter ? 72 : ($hour ? 76 : 83)) : 72;
        $ticks[] = [
            'x1' => round(100 + $inner * sin($angle), 2), 'y1' => round(100 - $inner * cos($angle), 2),
            'x2' => round(100 + $outer * sin($angle), 2), 'y2' => round(100 - $outer * cos($angle), 2),
            'class' => $quarter ? 'iet-tick-quarter' : ($hour ? 'iet-tick-hour' : 'iet-tick-minute'),
        ];
    }
    $numerals = [];
    if ($detailed) {
        foreach (range(1, 12) as $n) {
            $angle = deg2rad($n * 30);
            $numerals[] = ['n' => $n, 'x' => round(100 + 59 * sin($angle), 2), 'y' => round(100 - 59 * cos($angle), 2)];
        }
    }
@endphp

<svg viewBox="0 0 200 200" {{ $attributes->class(['iet-analog', 'iet-analog-detailed' => $detailed]) }} aria-hidden="true" focusable="false" data-clock-analog>
    <circle cx="100" cy="100" r="98" class="iet-bezel" />
    <circle cx="100" cy="100" r="{{ $detailed ? 93 : 92 }}" class="iet-dial" />
    @if ($detailed)
        <circle cx="100" cy="100" r="66" class="iet-dial-ring" />
    @endif
    @foreach ($ticks as $tick)
        <line x1="{{ $tick['x1'] }}" y1="{{ $tick['y1'] }}" x2="{{ $tick['x2'] }}" y2="{{ $tick['y2'] }}" class="{{ $tick['class'] }}" />
    @endforeach
    @foreach ($numerals as $numeral)
        <text x="{{ $numeral['x'] }}" y="{{ $numeral['y'] }}" class="iet-numeral" text-anchor="middle" dominant-baseline="central" data-numeral="{{ $numeral['n'] }}">{{ $numeral['n'] }}</text>
    @endforeach
    @if ($detailed)
        <text x="100" y="72" class="iet-wordmark" text-anchor="middle" dominant-baseline="central">{{ __('brand.name') }}</text>
        <g class="iet-date-window">
            <rect x="121" y="91" width="26" height="18" rx="4" />
            <text x="134" y="100.5" text-anchor="middle" dominant-baseline="central" data-clock-day></text>
        </g>
    @endif
    <g class="iet-hand iet-hand-hour" data-hand="hour">
        <rect x="{{ $detailed ? 96.5 : 95.5 }}" y="{{ $detailed ? 52 : 50 }}" width="{{ $detailed ? 7 : 9 }}" height="{{ $detailed ? 56 : 58 }}" rx="{{ $detailed ? 3.5 : 4.5 }}" />
    </g>
    <g class="iet-hand iet-hand-minute" data-hand="minute">
        <rect x="{{ $detailed ? 97.75 : 96.5 }}" y="{{ $detailed ? 24 : 26 }}" width="{{ $detailed ? 4.5 : 7 }}" height="{{ $detailed ? 84 : 82 }}" rx="{{ $detailed ? 2.25 : 3.5 }}" />
    </g>
    <g class="iet-hand iet-hand-second" data-hand="second">
        <line x1="100" y1="{{ $detailed ? 122 : 116 }}" x2="100" y2="{{ $detailed ? 18 : 22 }}" />
        @if ($detailed)
            <circle cx="100" cy="30" r="3.2" />
        @endif
    </g>
    <circle cx="100" cy="100" r="{{ $detailed ? 5 : 8 }}" class="iet-cap" />
    <circle cx="100" cy="100" r="{{ $detailed ? 2 : 3 }}" class="iet-cap-inner" />
</svg>
