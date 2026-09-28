@props(['source' => null])

@php
    $rtl = in_array(app()->getLocale(), ['fa', 'ar'], true);
    $contentDir = $rtl ? 'rtl' : 'ltr';
@endphp

<div class="mx-auto max-w-[92rem] lg:flex lg:items-start lg:gap-5" dir="ltr">
    <aside
        class="mb-4 lg:sticky lg:top-6 lg:mb-0 lg:w-52 lg:shrink-0 {{ $rtl ? 'lg:order-1' : 'lg:order-2' }}"
        dir="{{ $contentDir }}"
    >
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="px-2 pb-3">
                <div class="text-sm font-semibold">{{ __('planning_baseline.sidebar.title') }}</div>
                <div class="mt-1 text-xs leading-5 text-zinc-500">{{ __('planning_baseline.sidebar.help') }}</div>
            </div>

            <div class="space-y-4">
                <div>
                    <div class="px-2 pb-1 text-[0.68rem] font-semibold uppercase tracking-wider text-zinc-400">
                        {{ __('planning_baseline.sidebar.plan') }}
                    </div>
                    <nav class="space-y-0.5">
                        <a href="{{ route('planner.index', ['view' => 'today']) }}" class="block rounded-lg px-2.5 py-2 text-sm hover:bg-white dark:hover:bg-zinc-950">
                            {{ __('planning_baseline.views.today') }}
                        </a>
                        <a href="{{ route('planner.index', ['view' => 'list']) }}" class="block rounded-lg px-2.5 py-2 text-sm hover:bg-white dark:hover:bg-zinc-950">
                            {{ __('planning_baseline.views.list') }}
                        </a>
                        <a href="{{ route('planner.index', ['view' => 'calendar']) }}" class="block rounded-lg px-2.5 py-2 text-sm hover:bg-white dark:hover:bg-zinc-950">
                            {{ __('planning_baseline.views.calendar') }}
                        </a>
                        <a href="{{ route('planner.create') }}" class="block rounded-lg px-2.5 py-2 text-sm hover:bg-white dark:hover:bg-zinc-950">
                            {{ __('planning_baseline.new') }}
                        </a>
                    </nav>
                </div>

                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                    <div class="px-2 pb-1 text-[0.68rem] font-semibold uppercase tracking-wider text-zinc-400">
                        {{ __('planning_baseline.sidebar.tools') }}
                    </div>
                    <nav>
                        <a
                            href="{{ route('planner.tools.repeat', array_filter(['source' => $source])) }}"
                            class="block rounded-xl px-2.5 py-2.5 {{ request()->routeIs('planner.tools.repeat') ? 'bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-950 dark:ring-zinc-800' : 'hover:bg-white dark:hover:bg-zinc-950' }}"
                        >
                            <span class="block text-sm font-medium">{{ __('planning_baseline.tools.repeat.sidebar') }}</span>
                            <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                {{ __('planning_baseline.tools.repeat.sidebar_help') }}
                            </span>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </aside>

    <main
        class="min-w-0 flex-1 {{ $rtl ? 'lg:order-2' : 'lg:order-1' }}"
        dir="{{ $contentDir }}"
    >
        {{ $slot }}
    </main>
</div>
