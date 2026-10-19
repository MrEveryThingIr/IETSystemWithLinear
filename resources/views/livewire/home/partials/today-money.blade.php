{{-- Today's money at a glance, from Bookkeeping. --}}
@if (\Illuminate\Support\Facades\Route::has('bookkeeping.index'))
    <flux:card class="space-y-4" data-today-money>
        <div class="flex items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('bookkeeping.today.title') }}</flux:heading>
                <flux:text>{{ __('bookkeeping.today.help') }}</flux:text>
            </div>
            <flux:button :href="route('bookkeeping.index')" size="sm" variant="ghost">{{ __('bookkeeping.today.open') }}</flux:button>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                <div class="text-xs text-zinc-500">{{ __('bookkeeping.today.spent_today') }}</div>
                <div class="mt-1 font-semibold tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($money['today_expense']) }}</div>
            </div>
            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                <div class="text-xs text-zinc-500">{{ __('bookkeeping.today.received_today') }}</div>
                <div class="mt-1 font-semibold tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($money['today_income']) }}</div>
            </div>
            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                <div class="text-xs text-zinc-500">{{ __('bookkeeping.balances.owed_to_me') }}</div>
                <div class="mt-1 font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ \App\Support\Bookkeeping\Toman::format($money['owed_to_me']) }}</div>
            </div>
            <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                <div class="text-xs text-zinc-500">{{ __('bookkeeping.balances.i_owe') }}</div>
                <div class="mt-1 font-semibold tabular-nums text-red-700 dark:text-red-400">{{ \App\Support\Bookkeeping\Toman::format($money['i_owe']) }}</div>
            </div>
        </div>

        @if ($money['waiting_for_me']->isNotEmpty())
            <flux:callout icon="bell-alert" variant="warning">
                {{ trans_choice('bookkeeping.today.waiting_count', $money['waiting_for_me']->count(), ['count' => $money['waiting_for_me']->count()]) }}
            </flux:callout>
        @endif
        <div class="text-xs text-zinc-500">{{ __('bookkeeping.currency_note') }}</div>
    </flux:card>
@endif
