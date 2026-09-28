<section class="mx-auto max-w-7xl space-y-6">
    <x-app.page-header :title="__('finance_baseline.title')" :description="__('finance_baseline.help')" />

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-3 md:grid-cols-3">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('finance_baseline.iet.available') }}</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ \App\Support\IetValueMath::formatIetMinor($availableIet) }}</div>
            <div class="mt-1 text-xs text-zinc-500">IET</div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('finance_baseline.iet.reserved') }}</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ \App\Support\IetValueMath::formatIetMinor($reservedIet) }}</div>
            <div class="mt-1 text-xs text-zinc-500">IET</div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('finance_baseline.iet.valuation') }}</div>
            <div class="mt-2 font-semibold tabular-nums">
                {{ __('finance_baseline.iet.usd_per_iet', ['value' => \App\Support\IetValueMath::usdPerIet($snapshot->usd_pico_per_iet)]) }}
            </div>
            <div class="mt-1 text-xs text-zinc-500">
                {{ __('finance_baseline.iet.x_percent', ['value' => \App\Support\IetValueMath::xPercent($snapshot->usd_pico_per_iet)]) }}
            </div>
        </section>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm leading-6 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300">
        {{ __('finance_baseline.iet.notice') }}
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold">{{ __('finance_baseline.exchange.title') }}</div>
                            <div class="mt-1 text-sm leading-6 text-zinc-500">{{ __('finance_baseline.exchange.help') }}</div>
                        </div>
                        <span class="text-zinc-400">⌄</span>
                    </div>
                </summary>

                <div class="space-y-5 border-t border-zinc-200 p-5 dark:border-zinc-800">
                    <form wire:submit="submitExchange" class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model.live="exchangeDirection" :label="__('finance_baseline.exchange.direction')">
                            <option value="deposit">{{ __('finance_baseline.exchange.deposit') }}</option>
                            <option value="cashout">{{ __('finance_baseline.exchange.cashout') }}</option>
                        </flux:select>

                        <flux:input
                            wire:model.live.debounce.300ms="exchangeUsdAmount"
                            :label="__('finance_baseline.exchange.usd_amount')"
                            inputmode="decimal"
                            placeholder="3.50"
                        />

                        <flux:input
                            wire:model="externalReference"
                            :label="__('finance_baseline.exchange.reference')"
                            :placeholder="__('finance_baseline.exchange.reference_placeholder')"
                            maxlength="255"
                        />

                        <flux:input wire:model="exchangeNote" :label="__('finance_baseline.exchange.note')" maxlength="5000" />

                        @if ($exchangePreview !== null)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium tabular-nums dark:border-zinc-800 dark:bg-zinc-950/60 md:col-span-2">
                                {{ __('finance_baseline.exchange.preview', ['amount' => \App\Support\IetValueMath::formatIetMinor($exchangePreview)]) }}
                            </div>
                        @endif

                        <div class="flex justify-end md:col-span-2">
                            <flux:button type="submit" variant="primary">{{ __('finance_baseline.exchange.submit') }}</flux:button>
                        </div>
                    </form>

                    <div>
                        <div class="mb-3 text-sm font-semibold">{{ __('finance_baseline.exchange.history') }}</div>
                        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <table class="min-w-full text-sm">
                                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-950/60">
                                    <tr>
                                        <th class="px-3 py-2.5 text-start">{{ __('finance_baseline.exchange.direction') }}</th>
                                        <th class="px-3 py-2.5 text-end">USD</th>
                                        <th class="px-3 py-2.5 text-end">IET</th>
                                        <th class="px-3 py-2.5 text-start">{{ __('finance_baseline.iet.valuation') }}</th>
                                        <th class="px-3 py-2.5 text-start">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @forelse ($myExchangeRequests as $request)
                                        <tr>
                                            <td class="px-3 py-3">{{ __('finance_baseline.exchange.'.$request->direction->value) }}</td>
                                            <td class="px-3 py-3 text-end tabular-nums">USD {{ \App\Support\MoneyAmount::format($request->fiat_amount_minor, 2) }}</td>
                                            <td class="px-3 py-3 text-end font-medium tabular-nums">{{ \App\Support\IetValueMath::formatIetMinor($request->iet_amount_minor) }}</td>
                                            <td class="px-3 py-3 text-xs text-zinc-500 tabular-nums">{{ \App\Support\IetValueMath::xPercent($request->valuation->usd_pico_per_iet) }}%</td>
                                            <td class="px-3 py-3">
                                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                                                    {{ __('finance_baseline.exchange.'.$request->status->value) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('finance_baseline.exchange.none') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </details>

            <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-semibold">{{ __('finance_baseline.obligations.title') }}</div>
                            <div class="mt-1 text-sm leading-6 text-zinc-500">{{ __('finance_baseline.obligations.help') }}</div>
                        </div>
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $obligations->count() }}</span>
                    </div>
                </summary>

                <div class="divide-y divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @forelse ($obligations as $obligation)
                        @php
                            $isDebtor = (int) auth()->user()->actor->id === (int) $obligation->debtor_actor_id;
                            $other = $isDebtor ? $obligation->creditor : $obligation->debtor;
                            $available = $obligation->availableToSettleMinor();
                        @endphp
                        <a href="{{ route('financial-obligations.show', $obligation) }}" class="block p-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-950/50 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                                            {{ $isDebtor ? __('finance_baseline.obligations.debtor') : __('finance_baseline.obligations.creditor') }}
                                        </span>
                                        <span class="truncate font-semibold" dir="auto">{{ $other->user?->username ?? ('actor-'.$other->id) }}</span>
                                    </div>
                                    @if ($obligation->description)
                                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ $obligation->description }}</div>
                                    @endif
                                </div>

                                <div class="shrink-0 text-start sm:text-end">
                                    <div class="font-semibold tabular-nums">
                                        {{ \App\Support\MoneyAmount::format($obligation->outstandingMinor(), $obligation->monetaryUnit->exponent) }}
                                        {{ $obligation->monetaryUnit->code }}
                                    </div>

                                    @if (isset($obligationQuotes[$obligation->id]))
                                        <div class="mt-1 text-xs text-zinc-500 tabular-nums">
                                            {{ __('finance_baseline.obligations.iet_required', ['amount' => \App\Support\IetValueMath::formatIetMinor($obligationQuotes[$obligation->id])]) }}
                                        </div>
                                    @elseif ($available > 0 && $obligation->monetaryUnit->code !== 'USD')
                                        <div class="mt-1 max-w-xs text-xs text-zinc-500">{{ __('finance_baseline.obligations.unsupported') }}</div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-zinc-500">{{ __('finance_baseline.obligations.none') }}</div>
                    @endforelse
                </div>
            </details>
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="font-semibold">{{ __('finance_baseline.accounting.title') }}</div>
                <div class="mt-1 text-sm leading-6 text-zinc-500">{{ __('finance_baseline.accounting.help') }}</div>
                <flux:button :href="route('accounting.index')" variant="ghost" class="mt-4 w-full">{{ __('finance_baseline.accounting.open') }}</flux:button>
            </section>

            @if ($canManageExchange)
                <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <summary class="cursor-pointer list-none px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ __('finance_baseline.review.title') }}</div>
                                <div class="mt-1 text-xs leading-5 text-zinc-500">{{ __('finance_baseline.review.help') }}</div>
                            </div>
                            <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-semibold dark:bg-zinc-800">{{ $pendingExchangeRequests->count() }}</span>
                        </div>
                    </summary>

                    <div class="space-y-3 border-t border-zinc-200 p-4 dark:border-zinc-800">
                        @forelse ($pendingExchangeRequests as $pending)
                            <article class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold">{{ $pending->user->username }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">
                                            {{ __('finance_baseline.exchange.'.$pending->direction->value) }}
                                            · USD {{ \App\Support\MoneyAmount::format($pending->fiat_amount_minor, 2) }}
                                        </div>
                                    </div>
                                    <div class="text-end text-xs font-medium tabular-nums">{{ \App\Support\IetValueMath::formatIetMinor($pending->iet_amount_minor) }} IET</div>
                                </div>

                                @if ($pending->external_reference)
                                    <div class="mt-2 break-all text-xs text-zinc-500" dir="auto">{{ $pending->external_reference }}</div>
                                @endif

                                <flux:input wire:model="reviewNotes.{{ $pending->id }}" :label="__('finance_baseline.review.note')" class="mt-3" />

                                <div class="mt-3 flex gap-2">
                                    <flux:button wire:click="reviewExchange({{ $pending->id }}, true)" size="sm" variant="primary" class="flex-1">{{ __('finance_baseline.review.confirm') }}</flux:button>
                                    <flux:button wire:click="reviewExchange({{ $pending->id }}, false)" size="sm" variant="ghost" class="flex-1">{{ __('finance_baseline.review.reject') }}</flux:button>
                                </div>
                            </article>
                        @empty
                            <div class="py-4 text-center text-sm text-zinc-500">{{ __('finance_baseline.review.none') }}</div>
                        @endforelse
                    </div>
                </details>

                <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <summary class="cursor-pointer list-none px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                        <div class="font-semibold">{{ __('finance_baseline.valuation.title') }}</div>
                        <div class="mt-1 text-xs leading-5 text-zinc-500">{{ __('finance_baseline.valuation.help') }}</div>
                    </summary>

                    <form wire:submit="publishValuation" class="space-y-3 border-t border-zinc-200 p-4 dark:border-zinc-800">
                        <flux:input wire:model="valuationXPercent" :label="__('finance_baseline.valuation.x_percent')" />
                        <flux:textarea wire:model="valuationNote" :label="__('finance_baseline.valuation.note')" rows="2" />
                        <div class="text-xs leading-5 text-zinc-500">{{ __('finance_baseline.valuation.future_policy') }}</div>
                        <flux:button type="submit" variant="ghost" class="w-full">{{ __('finance_baseline.valuation.publish') }}</flux:button>
                    </form>
                </details>
            @endif
        </aside>
    </div>
</section>