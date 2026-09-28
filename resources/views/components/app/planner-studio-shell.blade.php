@props(['source' => null])

@php
    $rtl = in_array(app()->getLocale(), ['fa', 'ar'], true);
    $contentDir = $rtl ? 'rtl' : 'ltr';
@endphp

<div class="mx-auto max-w-[92rem] lg:flex lg:items-start lg:gap-4" dir="ltr">
    <aside
        class="mb-4 lg:sticky lg:top-6 lg:mb-0 lg:w-40 xl:w-44 lg:shrink-0 {{ $rtl ? 'lg:order-1' : 'lg:order-2' }}"
        dir="{{ $contentDir }}"
    >
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="px-2 pb-1 text-[0.68rem] font-semibold uppercase tracking-wider text-zinc-400">
                {{ __('planning_baseline.sidebar.tools') }}
            </div>

            <a
                href="{{ route('planner.tools.repeat', array_filter(['source' => $source])) }}"
                class="mt-1 block rounded-lg px-2.5 py-2 {{ request()->routeIs('planner.tools.repeat') ? 'bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-950 dark:ring-zinc-800' : 'hover:bg-white dark:hover:bg-zinc-950' }}"
            >
                <span class="block text-sm font-medium leading-5">{{ __('planning_baseline.tools.repeat.sidebar') }}</span>
                <span class="mt-1 hidden text-[0.72rem] leading-5 text-zinc-500 xl:block">
                    {{ __('planning_baseline.tools.repeat.sidebar_help') }}
                </span>
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
