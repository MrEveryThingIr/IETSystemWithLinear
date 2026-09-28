<div class="mx-auto max-w-6xl space-y-5">
    <nav class="inline-flex flex-wrap gap-1 rounded-xl border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-800 dark:bg-zinc-900" aria-label="{{ __('iet.finance.navigation') }}">
        <a
            href="{{ route('accounting.index') }}"
            class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('accounting.*') ? 'bg-zinc-900 font-medium text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
        >
            {{ __('iet.finance.money') }}
        </a>
        <a
            href="{{ route('iet.exchange.index') }}"
            class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('iet.exchange.*') ? 'bg-zinc-900 font-medium text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
        >
            {{ __('iet.finance.iet_exchange') }}
        </a>
        <a
            href="{{ route('finance.obligations.index') }}"
            class="rounded-lg px-3 py-2 text-sm {{ request()->routeIs('finance.obligations.*') || request()->routeIs('financial-obligations.*') ? 'bg-zinc-900 font-medium text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
        >
            {{ __('iet.finance.obligations') }}
        </a>
    </nav>

    {{ $slot }}
</div>
