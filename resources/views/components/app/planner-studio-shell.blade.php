@props(['source' => null])

<div class="mx-auto grid max-w-[92rem] gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
            <div>
                <div class="text-sm font-semibold">{{ __('planning_baseline.sidebar.title') }}</div>
                <div class="mt-1 text-xs text-zinc-500">{{ __('planning_baseline.sidebar.help') }}</div>
            </div>

            <div class="space-y-2">
                <div class="text-[0.68rem] font-semibold uppercase tracking-wider text-zinc-400">
                    {{ __('planning_baseline.sidebar.plan') }}
                </div>
                <nav class="space-y-1">
                    <a href="{{ route('planner.index', ['view' => 'today']) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-900">
                        {{ __('planning_baseline.views.today') }}
                    </a>
                    <a href="{{ route('planner.index', ['view' => 'list']) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-900">
                        {{ __('planning_baseline.views.list') }}
                    </a>
                    <a href="{{ route('planner.index', ['view' => 'calendar']) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-900">
                        {{ __('planning_baseline.views.calendar') }}
                    </a>
                    <a href="{{ route('planner.create') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-900">
                        {{ __('planning_baseline.new') }}
                    </a>
                </nav>
            </div>

            <div class="space-y-2">
                <div class="text-[0.68rem] font-semibold uppercase tracking-wider text-zinc-400">
                    {{ __('planning_baseline.sidebar.tools') }}
                </div>
                <nav class="space-y-1">
                    <a
                        href="{{ route('planner.tools.repeat', array_filter(['source' => $source])) }}"
                        class="block rounded-lg px-3 py-2 text-sm {{ request()->routeIs('planner.tools.repeat') ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'hover:bg-zinc-100 dark:hover:bg-zinc-900' }}"
                    >
                        <span class="block font-medium">{{ __('planning_baseline.tools.repeat.sidebar') }}</span>
                        <span class="mt-0.5 block text-xs {{ request()->routeIs('planner.tools.repeat') ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-500' }}">
                            {{ __('planning_baseline.tools.repeat.sidebar_help') }}
                        </span>
                    </a>
                </nav>
            </div>
        </div>
    </aside>

    <main class="min-w-0">
        {{ $slot }}
    </main>
</div>
