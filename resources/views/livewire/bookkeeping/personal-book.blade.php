<section class="mx-auto max-w-6xl space-y-6" data-personal-book>
    <div>
        <flux:button :href="route('bookkeeping.index')" variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ __('bookkeeping.title') }}</flux:button>
    </div>
    <x-app.page-header :title="__('bookkeeping.personal.title')" :description="__('bookkeeping.personal.help')" />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- Quick entry --}}
        <flux:card class="space-y-4" data-personal-form>
            <div class="inline-flex rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800" role="tablist">
                @foreach (['expense', 'income', 'transfer'] as $option)
                    <button type="button" wire:click="setType('{{ $option }}')" role="tab" aria-selected="{{ $type === $option ? 'true' : 'false' }}"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium {{ $type === $option ? 'bg-white shadow dark:bg-zinc-900' : 'text-zinc-600 dark:text-zinc-300' }}"
                        data-type-{{ $option }}>
                        {{ __('bookkeeping.personal.type_'.$option) }}
                    </button>
                @endforeach
            </div>

            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model.live.debounce.400ms="amount" :label="__('bookkeeping.fields.amount_toman')" inputmode="numeric" autocomplete="off" required data-amount />
                        @if ($amountPreview)
                            <div class="mt-1 text-xs text-zinc-500" data-amount-preview>{{ $amountPreview }} {{ __('bookkeeping.toman') }}</div>
                        @endif
                    </div>
                    <x-app.calendar-date-input model="occurredOn" :label="__('bookkeeping.fields.date')" />
                </div>
                @error('occurredOn')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="walletUuid" :label="$type === 'transfer' ? __('bookkeeping.fields.from_wallet') : ($type === 'income' ? __('bookkeeping.fields.into_wallet') : __('bookkeeping.fields.from_wallet'))">
                        @foreach ($wallets as $row)
                            <option value="{{ $row['wallet']->uuid }}">{{ $row['wallet']->name }}</option>
                        @endforeach
                    </flux:select>
                    @if ($type === 'transfer')
                        <flux:select wire:model="toWalletUuid" :label="__('bookkeeping.fields.to_wallet')">
                            <option value="">—</option>
                            @foreach ($wallets as $row)
                                @if ($row['wallet']->uuid !== $walletUuid)
                                    <option value="{{ $row['wallet']->uuid }}">{{ $row['wallet']->name }}</option>
                                @endif
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="counterpartyName" :label="$type === 'income' ? __('bookkeeping.fields.from_whom') : __('bookkeeping.fields.paid_to')" maxlength="120" />
                    @endif
                </div>

                @if ($type !== 'transfer')
                    <div class="space-y-2">
                        <div class="text-sm font-medium">{{ __('bookkeeping.fields.category') }}</div>
                        <div class="flex flex-wrap gap-2" data-category-chips>
                            @foreach ($categories as $category)
                                <button type="button" wire:click="$set('categoryUuid', '{{ $category->uuid }}')" wire:key="cat-{{ $category->uuid }}"
                                    class="rounded-full border px-3 py-1 text-sm {{ $categoryUuid === $category->uuid ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-700' }}">
                                    {{ $category->label() }}
                                </button>
                            @endforeach
                            <button type="button" wire:click="openCategory" class="rounded-full border border-dashed border-zinc-300 px-3 py-1 text-sm text-zinc-500 dark:border-zinc-700">+ {{ __('bookkeeping.personal.new_category') }}</button>
                        </div>
                    </div>
                @endif

                <flux:input wire:model="description" :label="__('bookkeeping.fields.note')" maxlength="500" />

                <div>
                    <label class="text-sm font-medium">{{ __('bookkeeping.fields.receipt') }}</label>
                    <input type="file" wire:model="receipt" accept="image/*,application/pdf" class="mt-1 block w-full text-sm" />
                    @error('receipt')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                @error('amount')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('walletUuid')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('toWalletUuid')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,receipt" data-save-entry>{{ __('bookkeeping.actions.save') }}</flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Wallets --}}
        <div class="space-y-3" data-wallets>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('bookkeeping.personal.wallets') }}</flux:heading>
                <flux:button wire:click="openWallet" size="sm" variant="ghost" icon="plus">{{ __('bookkeeping.personal.add_wallet') }}</flux:button>
            </div>
            @foreach ($wallets as $row)
                <div wire:key="wallet-{{ $row['wallet']->uuid }}" class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-medium" dir="auto">{{ $row['wallet']->name }}</div>
                            <div class="text-xs text-zinc-500">{{ __('bookkeeping.wallet_kinds.'.$row['wallet']->kind->value) }} @if ($row['wallet']->masked_identifier) · <span dir="ltr">{{ $row['wallet']->masked_identifier }}</span> @endif</div>
                        </div>
                        <div class="text-end font-semibold tabular-nums {{ $row['balance'] < 0 ? 'text-red-700 dark:text-red-400' : '' }}" dir="ltr">{{ \App\Support\Bookkeeping\Toman::format($row['balance']) }}</div>
                    </div>
                    @if ($wallets->count() > 1)
                        <button type="button" wire:click="archiveWallet('{{ $row['wallet']->uuid }}')" wire:confirm="{{ __('bookkeeping.personal.archive_wallet_confirm') }}" class="mt-2 text-xs text-zinc-500 underline-offset-4 hover:underline">{{ __('bookkeeping.personal.archive_wallet') }}</button>
                    @endif
                </div>
            @endforeach
            <div class="text-xs text-zinc-500">{{ __('bookkeeping.personal.wallets_private') }}</div>
        </div>
    </div>

    {{-- Month --}}
    <flux:card class="space-y-4" data-month>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <flux:button wire:click="shiftMonth(-1)" size="sm" variant="ghost" icon="chevron-left" class="rtl:[&_svg]:rotate-180" :aria-label="__('bookkeeping.personal.previous_month')" />
                <div class="min-w-32 text-center font-semibold" data-month-label>{{ $monthLabel }}</div>
                <flux:button wire:click="shiftMonth(1)" size="sm" variant="ghost" icon="chevron-right" class="rtl:[&_svg]:rotate-180" :aria-label="__('bookkeeping.personal.next_month')" />
            </div>
            <div class="flex gap-4 text-sm">
                <span>{{ __('bookkeeping.personal.month_income') }}: <strong class="tabular-nums text-emerald-700 dark:text-emerald-400">{{ \App\Support\Bookkeeping\Toman::format($period['income']) }}</strong></span>
                <span>{{ __('bookkeeping.personal.month_expense') }}: <strong class="tabular-nums text-red-700 dark:text-red-400">{{ \App\Support\Bookkeeping\Toman::format($period['expense']) }}</strong></span>
            </div>
        </div>

        @if ($period['by_category'] !== [])
            <div class="space-y-2" data-by-category>
                @foreach ($period['by_category'] as $row)
                    @php
                        $category = $row['category_id'] !== null ? $allCategories->get($row['category_id']) : null;
                        $base = max(1, $row['direction'] === 'income' ? $period['income'] : $period['expense']);
                        $width = max(2, (int) round($row['total'] * 100 / $base));
                    @endphp
                    <div class="grid grid-cols-[8rem_minmax(0,1fr)_7rem] items-center gap-3 text-sm sm:grid-cols-[10rem_minmax(0,1fr)_8rem]">
                        <div class="truncate" dir="auto">{{ $category?->label() ?? __('bookkeeping.personal.uncategorized') }}</div>
                        <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-2 rounded-full {{ $row['direction'] === 'income' ? 'bg-emerald-500' : 'bg-red-400' }}" style="width: {{ $width }}%"></div></div>
                        <div class="text-end tabular-nums">{{ \App\Support\Bookkeeping\Toman::format($row['total']) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800" data-entries>
            @forelse ($entries as $entry)
                <div wire:key="entry-{{ $entry->uuid }}" id="entry-{{ $entry->uuid }}" class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium" dir="auto">
                                @if ($entry->type === \App\Enums\Bookkeeping\TransactionType::Transfer)
                                    {{ $entry->wallet?->name }} → {{ $entry->counterWallet?->name }}
                                @else
                                    {{ $entry->category?->label() ?? __('bookkeeping.personal.uncategorized') }}@if ($entry->counterparty_name) · {{ $entry->counterparty_name }}@endif
                                @endif
                            </span>
                            @if ($entry->assets->isNotEmpty())
                                @foreach ($entry->assets as $asset)
                                    <a href="{{ route('bookkeeping.books.evidence', [$ledger, $entry, $asset]) }}" target="_blank" class="text-xs text-zinc-500 underline-offset-4 hover:underline">📎 {{ __('bookkeeping.evidence.file') }}</a>
                                @endforeach
                            @endif
                        </div>
                        <div class="text-xs text-zinc-500" dir="auto">
                            <x-app.local-date :value="$entry->occurred_on" />
                            @if ($entry->type !== \App\Enums\Bookkeeping\TransactionType::Transfer) · {{ $entry->wallet?->name }} @endif
                            @if ($entry->description) · {{ $entry->description }} @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="font-semibold tabular-nums {{ $entry->type === \App\Enums\Bookkeeping\TransactionType::Income ? 'text-emerald-700 dark:text-emerald-400' : ($entry->type === \App\Enums\Bookkeeping\TransactionType::Expense ? 'text-red-700 dark:text-red-400' : '') }}" dir="ltr">
                            {{ $entry->type === \App\Enums\Bookkeeping\TransactionType::Income ? '+' : ($entry->type === \App\Enums\Bookkeeping\TransactionType::Expense ? '−' : '') }}{{ \App\Support\Bookkeeping\Toman::format((int) $entry->amount) }}
                        </span>
                        @if ($entry->related_transaction_id !== null)
                            <flux:badge size="sm" color="zinc">{{ __('bookkeeping.personal.correction') }}</flux:badge>
                        @elseif (in_array($entry->id, $undone, true))
                            <flux:badge size="sm" color="zinc">{{ __('bookkeeping.personal.undone') }}</flux:badge>
                        @else
                            <button type="button" wire:click="undo('{{ $entry->uuid }}')" wire:confirm="{{ __('bookkeeping.personal.undo_confirm') }}" class="text-xs text-zinc-500 underline-offset-4 hover:underline" data-undo>{{ __('bookkeeping.personal.undo') }}</button>
                        @endif
                    </div>
                </div>
            @empty
                <x-app.empty-state :title="__('bookkeeping.personal.empty')" />
            @endforelse
        </div>

        @if ($sharedMoves->isNotEmpty())
            <div class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <div class="text-sm font-medium">{{ __('bookkeeping.personal.shared_moves') }}</div>
                @foreach ($sharedMoves as $move)
                    <a href="{{ route('bookkeeping.books.show', $move->ledger).'#entry-'.$move->uuid }}" class="flex items-center justify-between gap-3 text-sm hover:underline">
                        <span dir="auto">{{ $move->ledger->name }} · {{ __('bookkeeping.types.'.$move->type->value) }} · {{ $move->wallet?->name }} <flux:badge size="sm" color="zinc">{{ __('bookkeeping.status.'.$move->status->value) }}</flux:badge></span>
                        <span class="tabular-nums" dir="ltr">{{ \App\Support\Bookkeeping\Toman::format((int) $move->amount) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </flux:card>

    <x-app.dialog :show="$walletOpen" close="closeWallet" :title="__('bookkeeping.personal.add_wallet')" size="sm">
        <form wire:submit="addWallet" class="space-y-4">
            <flux:input wire:model="walletName" :label="__('bookkeeping.fields.wallet_name')" :placeholder="__('bookkeeping.personal.wallet_placeholder')" maxlength="120" required />
            <flux:select wire:model="walletKind" :label="__('bookkeeping.fields.wallet_kind')">
                @foreach ($walletKinds as $kind)
                    <option value="{{ $kind->value }}">{{ __('bookkeeping.wallet_kinds.'.$kind->value) }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="walletOpening" :label="__('bookkeeping.fields.opening_balance')" inputmode="numeric" />
            <flux:input wire:model="walletDigits" :label="__('bookkeeping.fields.last_digits')" :description="__('bookkeeping.personal.last_digits_help')" inputmode="numeric" maxlength="30" />
            @error('walletName')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('walletOpening')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeWallet" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('bookkeeping.actions.save') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>

    <x-app.dialog :show="$categoryOpen" close="closeCategory" :title="__('bookkeeping.personal.new_category')" size="sm">
        <form wire:submit="addCategory" class="space-y-4">
            <flux:input wire:model="categoryName" :label="__('bookkeeping.fields.category_name')" maxlength="80" required />
            @error('categoryName')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeCategory" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('bookkeeping.actions.save') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>
</section>
