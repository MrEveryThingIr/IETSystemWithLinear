@props(['source' => null])

@php
    $rtl = in_array(app()->getLocale(), ['fa', 'ar'], true);
    $contentDir = $rtl ? 'rtl' : 'ltr';
    $displayActive = request()->routeIs('planner.index') && request()->boolean('display');
@endphp

<div class="mx-auto max-w-[96rem] lg:flex lg:items-start lg:gap-3" dir="ltr">
    <aside
        class="mb-3 lg:sticky lg:top-6 lg:mb-0 lg:w-12 lg:shrink-0 {{ $rtl ? 'lg:order-1' : 'lg:order-2' }}"
        dir="{{ $contentDir }}"
        aria-label="{{ __('planning_baseline.sidebar.tools') }}"
    >
        <div class="flex gap-1 rounded-xl border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 lg:flex-col">
            <a
                href="{{ route('planner.tools.repeat', array_filter(['source' => $source])) }}"
                class="inline-flex size-9 items-center justify-center rounded-lg transition {{ request()->routeIs('planner.tools.repeat') ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white' }}"
                title="{{ __('planning_baseline.tools.repeat.sidebar') }}"
                aria-label="{{ __('planning_baseline.tools.repeat.sidebar') }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.5 9A7.5 7.5 0 0 1 17 5.1L19.5 7.5m0 0V3.75m0 3.75h-3.75M19.5 15A7.5 7.5 0 0 1 7 18.9L4.5 16.5m0 0v3.75m0-3.75h3.75"/>
                </svg>
            </a>

            <a
                href="{{ route('planner.index', ['view' => 'calendar', 'display' => 1]) }}"
                class="inline-flex size-9 items-center justify-center rounded-lg transition {{ $displayActive ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white' }}"
                title="{{ __('planning_baseline.tools.calendar_display.sidebar') }}"
                aria-label="{{ __('planning_baseline.tools.calendar_display.sidebar') }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 4.5v3m12-3v3M4.5 9h15M5.25 6h13.5A1.5 1.5 0 0 1 20.25 7.5v11.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V7.5A1.5 1.5 0 0 1 5.25 6Z"/>
                    <path stroke-linecap="round" stroke-width="1.8" d="M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01"/>
                </svg>
            </a>
        </div>
    </aside>

    <main
        class="min-w-0 flex-1 {{ $rtl ? 'lg:order-2' : 'lg:order-1' }}"
        dir="{{ $contentDir }}"
    >
        {{ $slot }}
    </main>
</div>
