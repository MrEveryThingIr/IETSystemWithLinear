<section class="mx-auto max-w-6xl space-y-6">
    <x-app.page-header
        :title="__('accounting.baseline.title')"
        :description="__('accounting.baseline.help')"
    />

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('accounting.baseline.iet_wallet') }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($ietBalance) }} IET</div>
                <p class="mt-1 text-sm text-zinc-500">{{ __('accounting.baseline.iet_wallet_help') }}</p>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('accounting.baseline.iet_quote') }}</div>
                <div class="mt-1 text-lg font-semibold tabular-nums" dir="ltr">
                    1 IET = &#36;{{ rtrim(rtrim((string) $ietQuote->usd_per_iet, '0'), '.') }}
                </div>
                <div class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-300" dir="ltr">
                    X = {{ $ietQuotePercent }}% of &#36;1
                </div>
                <p class="mt-1 text-sm text-zinc-500">{{ __('accounting.baseline.iet_quote_help') }}</p>
            </div>
        </div>

        <flux:button :href="route('exchange.index')" variant="primary">
            {{ __('accounting.baseline.open_exchange') }}
        </flux:button>
    </section>

    @if ($ledgers->isEmpty())
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <div class="max-w-2xl">
                <h2 class="text-lg font-semibold">{{ __('accounting.baseline.start_title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('accounting.baseline.start_help') }}</p>
            </div>

            <form wire:submit="createLedger" class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-end">
                <flux:select wire:model="unitCode" :label="__('accounting.monetary_unit')">
                    @foreach ($unitCatalog as $code => $meta)
                        <option value="{{ $code }}">{{ $code }} · {{ $meta['name'] }}</option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="ledgerName"
                    :label="__('accounting.ledger_name')"
                    :placeholder="__('accounting.baseline.ledger_placeholder')"
                    maxlength="180"
                />

                <flux:button type="submit" variant="primary">
                    {{ __('accounting.baseline.create_space') }}
                </flux:button>
            </form>
        </section>
    @else
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($ledgers as $ledgerOption)
                <button
                    type="button"
                    wire:key="money-ledger-{{ $ledgerOption->uuid }}"
                    wire:click="selectLedger('{{ $ledgerOption->uuid }}')"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition
                        {{ $ledger?->id === $ledgerOption->id
                            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                            : 'border-zinc-200 bg-white hover:border-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-600' }}"
                >
                    {{ $ledgerOption->name }} · {{ $ledgerOption->monetaryUnit->code }}
                </button>
            @endforeach
        </div>

        @if ($ledger)
            @php
                $unit = $ledger->monetaryUnit;
                $symbol = $unit->symbol ?: $unit->code;
                $exponent = (int) $unit->exponent;
            @endphp

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-5">
                    <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="font-semibold">{{ __('accounting.baseline.record_title') }}</div>
                                    <div class="mt-1 text-sm text-zinc-500">{{ __('accounting.baseline.record_help') }}</div>
                                </div>
                                <span class="text-zinc-400">⌄</span>
                            </div>
                        </summary>

                        <form wire:submit="record" class="space-y-4 border-t border-zinc-200 p-5 dark:border-zinc-800">
                            <div class="grid gap-4 md:grid-cols-3">
                                <flux:select wire:model.live="action" :label="__('accounting.baseline.kind')">
                                    <option value="expense">{{ __('accounting.action.expense') }}</option>
                                    <option value="income">{{ __('accounting.action.income') }}</option>
                                    <option value="transfer">{{ __('accounting.action.transfer') }}</option>
                                </flux:select>

                                <flux:input
                                    wire:model="amount"
                                    :label="__('accounting.amount')"
                                    :description="$unit->code"
                                    inputmode="decimal"
                                    autocomplete="off"
                                />

                                <x-app.calendar-date-input model="date" :label="__('accounting.date')" />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
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
                                @else
                                    <flux:input
                                        wire:model="category"
                                        :label="__('accounting.category')"
                                        :placeholder="$action === 'expense'
                                            ? __('accounting.baseline.expense_category_placeholder')
                                            : __('accounting.baseline.income_category_placeholder')"
                                        maxlength="180"
                                    />
                                @endif
                            </div>

                            <flux:input
                                wire:model="description"
                                :label="__('accounting.description')"
                                :placeholder="__('accounting.baseline.description_placeholder')"
                                maxlength="500"
                            />

                            <div class="flex justify-end">
                                <flux:button type="submit" variant="primary">
                                    {{ __('accounting.record') }}
                                </flux:button>
                            </div>
                        </form>
                    </details>

                    <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="font-semibold">{{ __('accounting.baseline.transactions') }}</div>
                                    <div class="mt-1 text-sm text-zinc-500">{{ __('accounting.baseline.transactions_help') }}</div>
                                </div>
                                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $rows->count() }}
                                </span>
                            </div>
                        </summary>

                        <div class="border-t border-zinc-200 dark:border-zinc-800">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-950/60">
                                        <tr>
                                            <th class="px-4 py-3 text-start">{{ __('accounting.date') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('accounting.baseline.kind') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('accounting.description') }}</th>
                                            <th class="px-4 py-3 text-start">{{ __('accounting.baseline.flow') }}</th>
                                            <th class="px-4 py-3 text-end">{{ __('accounting.amount') }}</th>
                                            <th class="w-12 px-2 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @forelse ($rows as $row)
                                            @php
                                                $sign = match ($row['kind']) {
                                                    'income' => '+',
                                                    'expense' => '−',
                                                    default => '',
                                                };
                                            @endphp
                                            <tr class="{{ $row['reversed'] ? 'opacity-45' : '' }}">
                                                <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                                    <x-app.local-date :value="$row['date']" />
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                                                        {{ __('accounting.entry_kind.'.$row['kind']) }}
                                                    </span>
                                                    @if ($row['reversed'])
                                                        <span class="ms-1 text-xs text-zinc-400">{{ __('accounting.reversed') }}</span>
                                                    @endif
                                                </td>
                                                <td class="max-w-xs px-4 py-3" dir="auto">
                                                    {{ $row['description'] !== '' ? $row['description'] : '—' }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-500">
                                                    @if ($row['from'])
                                                        <span dir="auto">{{ $row['from'] }}</span>
                                                    @endif
                                                    @if ($row['from'] || $row['to'])
                                                        <span class="mx-1">→</span>
                                                    @endif
                                                    @if ($row['to'])
                                                        <span dir="auto">{{ $row['to'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums">
                                                    {{ $sign }}{{ $symbol }} {{ \App\Support\MoneyAmount::format($row['amount_minor'], $exponent) }}
                                                </td>
                                                <td class="px-2 py-2 text-end">
                                                    @if (! $row['reversed'] && $row['kind'] !== 'reversal')
                                                        <button
                                                            type="button"
                                                            wire:click="reverseEntry({{ $row['id'] }})"
                                                            wire:confirm="{{ __('accounting.baseline.reverse_confirm') }}"
                                                            class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                                            title="{{ __('accounting.reverse') }}"
                                                            aria-label="{{ __('accounting.reverse') }}"
                                                        >
                                                            ↶
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500">
                                                    {{ __('accounting.empty_history') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>

                    <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="font-semibold">{{ __('accounting.baseline.intentions') }}</div>
                                    <div class="mt-1 text-sm text-zinc-500">{{ __('accounting.baseline.intentions_help') }}</div>
                                </div>
                                <span class="text-zinc-400">⌄</span>
                            </div>
                        </summary>

                        <div class="space-y-5 border-t border-zinc-200 p-5 dark:border-zinc-800">
                            <form wire:submit="addIntention" class="grid gap-4 md:grid-cols-2">
                                <flux:select wire:model="intentionKind" :label="__('accounting.baseline.intention_kind')">
                                    <option value="purchase">{{ __('accounting.baseline.intention_purchase') }}</option>
                                    <option value="earn">{{ __('accounting.baseline.intention_earn') }}</option>
                                </flux:select>

                                <flux:input
                                    wire:model="intentionTitle"
                                    :label="__('accounting.baseline.intention_title')"
                                    maxlength="180"
                                />

                                <flux:input
                                    wire:model="intentionAmount"
                                    :label="__('accounting.baseline.intention_amount')"
                                    :description="$unit->code"
                                    inputmode="decimal"
                                />

                                <x-app.calendar-date-input model="intentionDate" :label="__('accounting.baseline.target_date')" />

                                <div class="md:col-span-2">
                                    <flux:textarea
                                        wire:model="intentionNotes"
                                        :label="__('accounting.baseline.notes')"
                                        rows="2"
                                    />
                                </div>

                                <div class="flex justify-end md:col-span-2">
                                    <flux:button type="submit" variant="primary">
                                        {{ __('accounting.baseline.save_intention') }}
                                    </flux:button>
                                </div>
                            </form>

                            @if ($intentions->isNotEmpty())
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($intentions as $intention)
                                        <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="font-semibold" dir="auto">{{ $intention->title }}</div>
                                                    <div class="mt-1 text-sm font-medium tabular-nums">
                                                        {{ $symbol }} {{ \App\Support\MoneyAmount::format((int) $intention->amount_minor, $exponent) }}
                                                    </div>
                                                </div>
                                                <span class="rounded-full bg-white px-2 py-1 text-xs text-zinc-500 dark:bg-zinc-900">
                                                    {{ __('accounting.baseline.intention_'.$intention->kind) }}
                                                </span>
                                            </div>

                                            @if ($intention->target_on)
                                                <div class="mt-3 text-xs text-zinc-500">
                                                    {{ __('accounting.baseline.target') }}:
                                                    <x-app.local-date :value="$intention->target_on" />
                                                </div>
                                            @endif

                                            @if ($intention->notes)
                                                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ $intention->notes }}</div>
                                            @endif

                                            <button
                                                type="button"
                                                wire:click="archiveIntention({{ $intention->id }})"
                                                class="mt-3 text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white"
                                            >
                                                {{ __('accounting.baseline.archive') }}
                                            </button>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </details>
                </div>

                <aside class="space-y-4">
                    <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <summary class="cursor-pointer list-none px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                            <div class="font-semibold">{{ __('accounting.baseline.accounts_cards') }}</div>
                            <div class="mt-1 text-xs leading-5 text-zinc-500">{{ __('accounting.baseline.accounts_help') }}</div>
                        </summary>

                        <div class="space-y-3 border-t border-zinc-200 p-4 dark:border-zinc-800">
                            @foreach ($assetAccounts as $account)
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm dark:border-zinc-800 dark:bg-zinc-950/60" dir="auto">
                                    {{ $account->name }}
                                </div>
                            @endforeach

                            <form wire:submit="addAccount" class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                <flux:input
                                    wire:model="newAccountName"
                                    :label="__('accounting.account_name')"
                                    :placeholder="__('accounting.baseline.account_placeholder')"
                                    maxlength="180"
                                />
                                <p class="text-xs leading-5 text-zinc-500">{{ __('accounting.baseline.card_privacy') }}</p>
                                <flux:button type="submit" variant="ghost" class="w-full">
                                    {{ __('accounting.add_account') }}
                                </flux:button>
                            </form>
                        </div>
                    </details>

                    <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <summary class="cursor-pointer list-none px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                            <div class="font-semibold">{{ __('accounting.baseline.other_unit') }}</div>
                        </summary>
                        <form wire:submit="createLedger" class="space-y-3 border-t border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:select wire:model="unitCode" :label="__('accounting.monetary_unit')">
                                @foreach ($unitCatalog as $code => $meta)
                                    <option value="{{ $code }}">{{ $code }} · {{ $meta['name'] }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="ledgerName" :label="__('accounting.ledger_name')" maxlength="180" />
                            <flux:button type="submit" variant="ghost" class="w-full">
                                {{ __('accounting.baseline.add_unit') }}
                            </flux:button>
                        </form>
                    </details>
                </aside>
            </div>
        @endif
    @endif

    <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="font-semibold">{{ __('accounting.baseline.obligations') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('accounting.baseline.obligations_help') }}</div>
                </div>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ $obligationRows->count() }}
                </span>
            </div>
        </summary>

        <div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-800">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-950/60">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('accounting.baseline.role') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('accounting.baseline.counterparty') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('accounting.baseline.obligation_amount') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('accounting.baseline.outstanding') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('accounting.baseline.reference_value') }}</th>
                        <th class="w-12 px-2 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($obligationRows as $row)
                        @php
                            $obligation = $row['obligation'];
                            $unit = $obligation->monetaryUnit;
                            $priced = $row['iet_pricing'];
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                                    {{ __('accounting.baseline.'.$row['role']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium" dir="auto">{{ '@'.$row['counterparty'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums">
                                {{ \App\Support\MoneyAmount::format((int) $obligation->amount_minor, (int) $unit->exponent) }}
                                {{ $unit->code }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums">
                                {{ \App\Support\MoneyAmount::format((int) $row['outstanding_minor'], (int) $unit->exponent) }}
                                {{ $unit->code }}
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500">
                                @if ($priced)
                                    <span class="whitespace-nowrap">
                                        &#36;{{ \App\Support\MoneyAmount::format((int) $priced->reference_usd_amount_minor, 2) }}
                                    </span>
                                    <span class="ms-1" dir="ltr">
                                        @ {{ rtrim(rtrim((string) $priced->valuationQuote->usd_per_iet, '0'), '.') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-2 py-2 text-end">
                                <a
                                    href="{{ route('financial-obligations.show', $obligation) }}"
                                    class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    title="{{ __('accounting.baseline.open_obligation') }}"
                                    aria-label="{{ __('accounting.baseline.open_obligation') }}"
                                >→</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-zinc-500">
                                {{ __('accounting.baseline.no_obligations') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
</section>
