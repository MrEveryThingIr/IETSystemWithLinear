{{--
    The thin bar at the top of every page, signed in or not: a small live
    clock (Gregorian date and time on hover or tap) and the moving
    "text of the day" written by the superadmin.
--}}
@php
    $viewer = request()->user();
    $clockSettings = \App\Support\TimeBar\ClockSettings::for($viewer);
    $dayItems = \App\Support\TimeBar\DayTexts::today($viewer, app()->getLocale());
    $barId = 'iet-time-bar';
@endphp

<div {{ $attributes->class('iet-time-bar relative z-40 border-b border-indigo-200/70 bg-gradient-to-r from-indigo-50 via-white to-cyan-50 text-zinc-700 print:hidden dark:border-indigo-950 dark:from-indigo-950/60 dark:via-zinc-950 dark:to-cyan-950/40 dark:text-zinc-200') }} data-time-bar>
    <div class="flex h-10 min-w-0 items-center gap-2 px-2 sm:gap-3 sm:px-4">
        <div class="group/clock relative shrink-0" {!! \App\Support\TimeBar\ClockSettings::attributes($viewer) !!}>
            <button type="button" class="flex min-h-9 items-center gap-2 rounded-full px-1.5 py-1 text-start outline-none hover:bg-white/70 focus-visible:ring-2 focus-visible:ring-indigo-400 dark:hover:bg-white/5"
                aria-describedby="{{ $barId }}-gregorian" aria-label="{{ __('time_bar.bar.clock') }}" data-clock-trigger>
                <x-clock.analog class="size-6 shrink-0" />
                <span class="flex min-w-0 items-baseline gap-2 text-xs leading-none">
                    <time class="font-semibold tabular-nums text-zinc-900 dark:text-white" data-clock-time data-seconds></time>
                    <span class="hidden truncate text-zinc-500 md:inline dark:text-zinc-400" data-clock-date data-date-style="bar"></span>
                    @if ($clockSettings['inline_gregorian'])
                        <span class="hidden truncate text-[0.68rem] text-zinc-400 lg:inline" data-clock-gregorian-short></span>
                    @endif
                </span>
            </button>
            <div id="{{ $barId }}-gregorian" role="tooltip"
                class="pointer-events-none absolute start-0 top-full z-50 mt-1.5 hidden w-max max-w-[min(22rem,calc(100vw-1rem))] rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs shadow-lg group-hover/clock:block group-focus-within/clock:block dark:border-zinc-700 dark:bg-zinc-900">
                <span class="block text-[0.65rem] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">{{ __('time_bar.bar.gregorian') }}</span>
                <span class="mt-0.5 block font-medium text-zinc-900 dark:text-white" data-clock-gregorian></span>
                <span class="mt-1 block text-zinc-500 dark:text-zinc-400"><span class="sr-only">{{ __('time_bar.bar.timezone') }}: </span><span data-clock-zone></span></span>
            </div>
        </div>

        @if ($dayItems !== [])
            <span class="h-5 w-px shrink-0 bg-indigo-200 dark:bg-indigo-900" aria-hidden="true"></span>
            <section class="iet-marquee min-w-0 flex-1" aria-label="{{ __('time_bar.bar.label') }}" data-marquee>
                <div class="iet-marquee-viewport" data-marquee-viewport>
                    <div class="iet-marquee-track" data-marquee-track>
                        <div class="iet-marquee-group" data-marquee-group>
                            @foreach ($dayItems as $item)
                                <{{ $item['url'] ? 'a' : 'span' }} @if ($item['url']) href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer nofollow" @endif class="iet-marquee-item" data-marquee-item>
                                    <span class="iet-marquee-kind iet-kind-{{ $item['kind'] }}">{{ __('time_bar.kinds.'.$item['kind']) }}</span>
                                    <span dir="auto">{{ $item['text'] }}</span>
                                    @if ($item['attribution'])<span class="text-zinc-500 dark:text-zinc-400" dir="auto">— {{ $item['attribution'] }}</span>@endif
                                </{{ $item['url'] ? 'a' : 'span' }}>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
            <div class="flex shrink-0 items-center">
                <button type="button" class="grid size-8 place-items-center rounded-full text-zinc-500 hover:bg-white/70 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-indigo-400 dark:hover:bg-white/5 dark:hover:text-white"
                    data-marquee-toggle aria-pressed="false" data-label-pause="{{ __('time_bar.bar.pause') }}" data-label-play="{{ __('time_bar.bar.play') }}" aria-label="{{ __('time_bar.bar.pause') }}" title="{{ __('time_bar.bar.pause') }}">
                    <svg viewBox="0 0 20 20" class="size-4" fill="currentColor" aria-hidden="true" data-icon-pause><rect x="5" y="4" width="3.5" height="12" rx="1"/><rect x="11.5" y="4" width="3.5" height="12" rx="1"/></svg>
                    <svg viewBox="0 0 20 20" class="size-4 rtl:-scale-x-100" fill="currentColor" aria-hidden="true" data-icon-play hidden><path d="M6 4.2v11.6a.8.8 0 0 0 1.2.7l9.3-5.8a.8.8 0 0 0 0-1.4L7.2 3.5A.8.8 0 0 0 6 4.2Z"/></svg>
                </button>
                <button type="button" class="grid size-8 place-items-center rounded-full text-zinc-500 hover:bg-white/70 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-indigo-400 dark:hover:bg-white/5 dark:hover:text-white"
                    data-marquee-expand aria-expanded="false" aria-controls="{{ $barId }}-all" data-label-expand="{{ __('time_bar.bar.expand') }}" data-label-collapse="{{ __('time_bar.bar.collapse') }}" aria-label="{{ __('time_bar.bar.expand') }}" title="{{ __('time_bar.bar.expand') }}">
                    <svg viewBox="0 0 20 20" class="size-4 transition-transform" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-icon-expand><path d="m5 8 5 5 5-5"/></svg>
                </button>
            </div>
        @endif
    </div>

    @if ($dayItems !== [])
        <div id="{{ $barId }}-all" class="border-t border-indigo-100 px-4 py-3 text-sm dark:border-indigo-950" hidden data-marquee-panel>
            <ul class="mx-auto max-w-4xl space-y-2">
                @foreach ($dayItems as $item)
                    <li class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span class="iet-marquee-kind iet-kind-{{ $item['kind'] }}">{{ __('time_bar.kinds.'.$item['kind']) }}</span>
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="min-w-0 underline decoration-indigo-300 underline-offset-2 [overflow-wrap:anywhere]" dir="auto">{{ $item['text'] }}</a>
                        @else
                            <span class="min-w-0 [overflow-wrap:anywhere]" dir="auto">{{ $item['text'] }}</span>
                        @endif
                        @if ($item['attribution'])<span class="text-zinc-500 dark:text-zinc-400" dir="auto">— {{ $item['attribution'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
