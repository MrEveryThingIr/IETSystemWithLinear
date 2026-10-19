<section class="mx-auto max-w-6xl space-y-6" data-bookkeeping-index>
    <x-app.page-header :title="__('bookkeeping.title')" :description="__('bookkeeping.help')">
        <x-slot name="actions">
            <flux:button wire:click="openCreate('shared')" variant="primary" icon="plus" data-new-shared-book>{{ __('bookkeeping.index.new_shared') }}</flux:button>
            <flux:button wire:click="openCreate('project')" variant="ghost" icon="briefcase">{{ __('bookkeeping.index.new_project') }}</flux:button>
        </x-slot>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-sm text-zinc-500">{{ __('bookkeeping.balances.owed_to_me') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-400" data-owed-to-me>{{ \App\Support\Bookkeeping\Toman::format($summary['owed_to_me']) }}</div>
            <div class="text-xs text-zinc-500">{{ __('bookkeeping.toman') }} · {{ __('bookkeeping.balances.confirmed_only') }}</div>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-sm text-zinc-500">{{ __('bookkeeping.balances.i_owe') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-red-700 dark:text-red-400" data-i-owe>{{ \App\Support\Bookkeeping\Toman::format($summary['i_owe']) }}</div>
            <div class="text-xs text-zinc-500">{{ __('bookkeeping.toman') }} · {{ __('bookkeeping.balances.confirmed_only') }}</div>
        </div>
        <div class="rounded-2xl border p-4 {{ $summary['waiting_for_me']->isNotEmpty() ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30' : 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' }}">
            <div class="text-sm text-zinc-500">{{ __('bookkeeping.index.waiting_for_me') }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $summary['waiting_for_me']->count() }}</div>
            <div class="text-xs text-zinc-500">{{ __('bookkeeping.index.waiting_help') }}</div>
        </div>
    </div>

    @if ($summary['waiting_for_me']->isNotEmpty())
        <flux:card class="space-y-3" data-waiting-list>
            <flux:heading size="lg">{{ __('bookkeeping.index.please_confirm') }}</flux:heading>
            @foreach ($summary['waiting_for_me'] as $entry)
                <div wire:key="waiting-{{ $entry->uuid }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                    <div class="min-w-0">
                        <div class="font-medium" dir="auto">
                            {{ __('bookkeeping.today.waiting_title', [
                                'who' => $entry->recorder?->user?->username ?? '',
                                'type' => __('bookkeeping.types.'.$entry->type->value),
                                'amount' => \App\Support\Bookkeeping\Toman::format((int) $entry->amount),
                            ]) }}
                        </div>
                        <div class="text-xs text-zinc-500" dir="auto">
                            {{ $entry->ledger->name }} · <x-app.local-date :value="$entry->occurred_on" />
                            @if ($entry->description) · {{ $entry->description }} @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <flux:button wire:click="approve('{{ $entry->uuid }}')" size="sm" variant="primary" icon="check">{{ __('bookkeeping.actions.confirm') }}</flux:button>
                        <flux:button :href="route('bookkeeping.books.show', $entry->ledger).'#entry-'.$entry->uuid" size="sm" variant="ghost">{{ __('bookkeeping.actions.open') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endif

    <a href="{{ route('bookkeeping.personal') }}" class="block rounded-2xl border border-zinc-200 bg-white p-5 transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900" data-personal-book-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 font-semibold"><flux:icon name="wallet" class="size-5" /> {{ __('bookkeeping.personal.title') }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ __('bookkeeping.personal.card_help') }}</div>
            </div>
            <div class="text-end">
                <div class="text-xs text-zinc-500">{{ __('bookkeeping.personal.in_wallets') }}</div>
                <div class="text-xl font-semibold tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($walletTotal) }}</div>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
            <div><div class="text-xs text-zinc-500">{{ __('bookkeeping.personal.month_income') }}</div><div class="font-medium tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($month['income']) }}</div></div>
            <div><div class="text-xs text-zinc-500">{{ __('bookkeeping.personal.month_expense') }}</div><div class="font-medium tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($month['expense']) }}</div></div>
            <div><div class="text-xs text-zinc-500">{{ __('bookkeeping.personal.month_net') }}</div><div class="font-medium tabular-nums {{ $month['net'] < 0 ? 'text-red-700 dark:text-red-400' : '' }}" dir="ltr">{{ \App\Support\Bookkeeping\Toman::format($month['net']) }}</div></div>
        </div>
    </a>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('bookkeeping.index.books') }}</flux:heading>
        @forelse ($books as $row)
            @php($position = $row['position'])
            <a wire:key="book-{{ $row['ledger']->uuid }}" href="{{ route('bookkeeping.books.show', $row['ledger']) }}" class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800 dark:bg-zinc-900" data-book-row>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="truncate font-semibold" dir="auto">{{ $row['ledger']->name }}</span>
                        <flux:badge size="sm">{{ __('bookkeeping.kinds.'.$row['ledger']->kind->value) }}</flux:badge>
                        @if ($row['ledger']->status === \App\Enums\Bookkeeping\LedgerStatus::Archived)
                            <flux:badge size="sm" color="zinc">{{ __('bookkeeping.archived') }}</flux:badge>
                        @endif
                        @if ($row['pending'] > 0)
                            <flux:badge size="sm" color="amber">{{ trans_choice('bookkeeping.index.pending_badge', $row['pending'], ['count' => $row['pending']]) }}</flux:badge>
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-zinc-500">{{ trans_choice('bookkeeping.index.people', $row['people'], ['count' => $row['people']]) }}</div>
                </div>
                <div class="shrink-0 text-sm sm:text-end">
                    @if ($position['net'] > 0)
                        <div class="text-emerald-700 dark:text-emerald-400">{{ __('bookkeeping.balances.they_owe_you') }}</div>
                        <div class="text-lg font-semibold tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($position['net']) }}</div>
                    @elseif ($position['net'] < 0)
                        <div class="text-red-700 dark:text-red-400">{{ __('bookkeeping.balances.you_owe') }}</div>
                        <div class="text-lg font-semibold tabular-nums">{{ \App\Support\Bookkeeping\Toman::format(-$position['net']) }}</div>
                    @else
                        <div class="text-zinc-500">{{ __('bookkeeping.balances.settled') }}</div>
                    @endif
                </div>
            </a>
        @empty
            <x-app.empty-state :title="__('bookkeeping.index.empty_title')" :description="__('bookkeeping.index.empty_help')" />
        @endforelse
    </div>

    <x-app.dialog :show="$createOpen" close="closeCreate" :title="$newKind === 'project' ? __('bookkeeping.index.new_project') : __('bookkeeping.index.new_shared')">
        <form wire:submit="createBook" class="space-y-4" data-create-book-form>
            <flux:text>{{ $newKind === 'project' ? __('bookkeeping.index.project_help') : __('bookkeeping.index.shared_help') }}</flux:text>
            <flux:input wire:model="newName" :label="__('bookkeeping.fields.book_name')" :placeholder="$newKind === 'project' ? __('bookkeeping.index.project_placeholder') : __('bookkeeping.index.shared_placeholder')" maxlength="160" required />
            <flux:textarea wire:model="newDescription" :label="__('bookkeeping.fields.description')" rows="2" maxlength="1000" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeCreate" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('bookkeeping.actions.create_book') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>
</section>
