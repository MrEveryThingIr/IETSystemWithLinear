<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('accounting.title')" :description="__('accounting.help')">
        @if ($ledger)
            <x-slot:actions>
                <flux:button :href="route('contexts.timeline', $context)" variant="ghost">
                    {{ __('collaboration.tabs.timeline') }}
                </flux:button>
            </x-slot:actions>
        @endif
    </x-app.page-header>

    <flux:callout>{{ __('accounting.personal_only') }}</flux:callout>

    @if ($ledgers->isEmpty())
        <flux:card class="mx-auto max-w-xl space-y-5">
            <div>
                <flux:heading size="lg">{{ __('accounting.create_ledger') }}</flux:heading>
                <flux:text>{{ __('accounting.technical_boundary') }}</flux:text>
            </div>

            <form wire:submit="createLedger" class="space-y-4">
                <flux:select wire:model="unitCode" :label="__('accounting.monetary_unit')">
                    @foreach ($unitCatalog as $code => $meta)
                        <option value="{{ $code }}">{{ $code }} · {{ $meta['name'] }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="ledgerName" :label="__('accounting.ledger_name')" maxlength="180" />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('accounting.create') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach ($ledgers as $ledgerOption)
                <flux:button
                    wire:key="ledger-{{ $ledgerOption->uuid }}"
                    wire:click="selectLedger('{{ $ledgerOption->uuid }}')"
                    :variant="$ledger?->id === $ledgerOption->id ? 'primary' : 'ghost'"
                    size="sm"
                >
                    {{ $ledgerOption->name }} · {{ $ledgerOption->monetaryUnit->code }}
                </flux:button>
            @endforeach
        </div>

        @if ($ledger)
            @php
                $exponent = $ledger->monetaryUnit->exponent;
                $symbol = $ledger->monetaryUnit->symbol ?: $ledger->monetaryUnit->code.' ';
                $assetTotal = $assetAccounts->sum(fn ($account) => $balances[$account->id] ?? 0);
            @endphp

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <flux:card>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('accounting.current_balance') }}</div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ $symbol }}{{ \App\Support\MoneyAmount::format((int) $assetTotal, $exponent) }}
                    </div>
                </flux:card>
                <flux:card>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('accounting.income') }}</div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ $symbol }}{{ \App\Support\MoneyAmount::format((int) $period['income_minor'], $exponent) }}
                    </div>
                </flux:card>
                <flux:card>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('accounting.expense') }}</div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ $symbol }}{{ \App\Support\MoneyAmount::format((int) $period['expense_minor'], $exponent) }}
                    </div>
                </flux:card>
                <flux:card>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('accounting.net') }}</div>
                    <div class="mt-2 text-2xl font-semibold">
                        {{ $symbol }}{{ \App\Support\MoneyAmount::format((int) $period['net_minor'], $exponent) }}
                    </div>
                </flux:card>
            </div>

            <flux:card class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('accounting.period_summary') }}</flux:heading>
                        <flux:text>{{ $periodFrom }} → {{ $periodThrough }}</flux:text>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:select wire:model.live="periodMode">
                            @foreach (['day', 'week', 'month', 'year'] as $mode)
                                <option value="{{ $mode }}">{{ __('accounting.period.'.$mode) }}</option>
                            @endforeach
                        </flux:select>
                        <x-app.calendar-date-input model="periodDate" :label="__('accounting.date')" />
                    </div>
                </div>
            </flux:card>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <flux:card class="space-y-5">
                        <flux:heading size="lg">{{ __('accounting.quick_action') }}</flux:heading>

                        <form wire:submit="record" class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <flux:select wire:model.live="action" :label="__('accounting.quick_action')">
                                    @foreach (['opening_balance', 'expense', 'income', 'transfer'] as $kind)
                                        <option value="{{ $kind }}">{{ __('accounting.action.'.$kind) }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:input wire:model="amount" :label="__('accounting.amount')" inputmode="decimal" />
                                <x-app.calendar-date-input model="date" :label="__('accounting.date')" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="accountUuid" :label="$action === 'transfer' ? __('accounting.from_account') : __('accounting.account')">
                                    @foreach ($assetAccounts as $account)
                                        <option value="{{ $account->uuid }}">{{ $account->name }}</option>
                                    @endforeach
                                </flux:select>

                                @if ($action === 'transfer')
                                    <flux:select wire:model="toAccountUuid" :label="__('accounting.to_account')">
                                        <option value="">—</option>
                                        @foreach ($assetAccounts as $account)
                                            <option value="{{ $account->uuid }}">{{ $account->name }}</option>
                                        @endforeach
                                    </flux:select>
                                @elseif (in_array($action, ['expense', 'income'], true))
                                    <div>
                                        <flux:input wire:model="category" :label="__('accounting.category')" maxlength="180" />
                                        <p class="mt-1 text-xs text-zinc-500">{{ __('accounting.category_help') }}</p>
                                    </div>
                                @endif
                            </div>

                            <flux:input wire:model="description" :label="__('accounting.description')" maxlength="500" />

                            <div class="flex justify-end">
                                <flux:button type="submit" variant="primary">{{ __('accounting.record') }}</flux:button>
                            </div>
                        </form>
                    </flux:card>

                    <flux:card class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <flux:heading size="lg">{{ __('accounting.history') }}</flux:heading>
                            <span class="text-sm text-zinc-500">{{ $entries->count() }}</span>
                        </div>

                        <div class="space-y-3">
                            @forelse ($entries as $entry)
                                @php($entryAmount = (int) $entry->lines->sum('debit_minor'))
                                <article id="entry-{{ $entry->uuid }}" wire:key="journal-entry-{{ $entry->uuid }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <flux:badge size="sm">{{ __('accounting.entry_kind.'.$entry->kind->value) }}</flux:badge>
                                                @if ($entry->reversals->isNotEmpty())
                                                    <flux:badge size="sm" color="zinc">{{ __('accounting.reversed') }}</flux:badge>
                                                @endif
                                                <span class="text-xs text-zinc-500"><x-app.local-date :value="$entry->occurred_on" /></span>
                                            </div>
                                            <div class="mt-2 font-medium" dir="auto">{{ $entry->description ?: __('accounting.entry_kind.'.$entry->kind->value) }}</div>
                                            <div class="mt-1 text-xs text-zinc-500">
                                                {{ $entry->creator->user?->username ?? __('relationships.unknown_actor') }}
                                                · JE-{{ str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT) }}
                                            </div>
                                        </div>

                                        <div class="shrink-0 sm:text-end">
                                            <div class="font-semibold">{{ $symbol }}{{ \App\Support\MoneyAmount::format($entryAmount, $exponent) }}</div>
                                            @if ($entry->kind !== \App\JournalEntryKind::Reversal && $entry->reversals->isEmpty())
                                                <flux:button wire:click="reverseEntry({{ $entry->id }})" size="sm" variant="ghost" class="mt-2">
                                                    {{ __('accounting.reverse') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <x-app.empty-state :title="__('accounting.empty_history')" />
                            @endforelse
                        </div>
                    </flux:card>
                </div>

                <div class="space-y-6">
                    <flux:card class="space-y-4">
                        <flux:heading size="lg">{{ __('accounting.accounts') }}</flux:heading>

                        <div class="space-y-3">
                            @foreach ($assetAccounts as $account)
                                <div wire:key="account-{{ $account->uuid }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                    <span class="font-medium" dir="auto">{{ $account->name }}</span>
                                    <span>{{ $symbol }}{{ \App\Support\MoneyAmount::format((int) ($balances[$account->id] ?? 0), $exponent) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <form wire:submit="addAccount" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                            <flux:input wire:model="newAccountName" :label="__('accounting.account_name')" maxlength="180" />
                            <flux:button type="submit" variant="ghost" class="w-full">{{ __('accounting.add_account') }}</flux:button>
                        </form>
                    </flux:card>

                    <flux:callout>{{ __('accounting.technical_boundary') }}</flux:callout>

                    <flux:card class="space-y-3">
                        <flux:heading>{{ __('accounting.create_ledger') }}</flux:heading>
                        <form wire:submit="createLedger" class="space-y-3">
                            <flux:select wire:model="unitCode" :label="__('accounting.monetary_unit')">
                                @foreach ($unitCatalog as $code => $meta)
                                    <option value="{{ $code }}">{{ $code }} · {{ $meta['name'] }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="ledgerName" :label="__('accounting.ledger_name')" maxlength="180" />
                            <flux:button type="submit" variant="ghost" class="w-full">{{ __('accounting.create') }}</flux:button>
                        </form>
                    </flux:card>
                </div>
            </div>
        @endif
    @endif
</section>
