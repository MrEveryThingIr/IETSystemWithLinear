<section class="mx-auto max-w-6xl space-y-6">
    <x-app.page-header :title="__('exchange.title')" :description="__('exchange.help')">
        <x-slot:actions>
            <flux:button :href="route('accounting.index')" variant="ghost">
                {{ __('exchange.back_to_money') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('exchange.wallet_balance') }}</div>
            <div class="mt-2 text-3xl font-semibold tabular-nums">{{ number_format($walletBalance) }}</div>
            <div class="mt-1 text-sm text-zinc-500">IET</div>
        </article>

        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('exchange.current_quote') }}</div>
            <div class="mt-2 text-xl font-semibold tabular-nums" dir="ltr">
                1 IET = &#36;{{ rtrim(rtrim((string) $quote->usd_per_iet, '0'), '.') }}
            </div>
            <div class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-300" dir="ltr">
                X = {{ $quotePercent }}% of &#36;1
            </div>
            <div class="mt-1 text-xs text-zinc-500">
                {{ __('exchange.effective') }} <x-app.local-datetime :value="$quote->effective_at" />
            </div>
        </article>

        <article class="rounded-2xl border border-zinc-800 bg-zinc-950 p-5 text-zinc-50 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('exchange.boundary_title') }}</div>
            <p class="mt-2 text-sm leading-6 text-zinc-300">{{ __('exchange.boundary') }}</p>
        </article>
    </div>

    <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-semibold">{{ __('exchange.request_title') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('exchange.request_help') }}</div>
                </div>
                <span class="text-zinc-400">⌄</span>
            </div>
        </summary>

        <form wire:submit="submit" class="space-y-5 border-t border-zinc-200 p-5 dark:border-zinc-800">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (['deposit', 'cashout'] as $option)
                    <label class="cursor-pointer rounded-xl border p-4 transition {{ $direction === $option ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-950' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input class="sr-only" type="radio" wire:model.live="direction" value="{{ $option }}">
                        <div class="font-medium">{{ __('exchange.direction.'.$option) }}</div>
                        <div class="mt-1 text-sm leading-5 text-zinc-500">{{ __('exchange.direction_help.'.$option) }}</div>
                    </label>
                @endforeach
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input
                    wire:model="usdAmount"
                    :label="__('exchange.usd_amount')"
                    description="USD"
                    inputmode="decimal"
                    autocomplete="off"
                />

                <flux:input
                    wire:model="externalReference"
                    :label="__('exchange.external_reference')"
                    :placeholder="__('exchange.external_reference_placeholder')"
                    maxlength="255"
                />
            </div>

            <flux:textarea wire:model="note" :label="__('exchange.note')" rows="2" />

            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm leading-6 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/60 dark:text-zinc-300">
                {{ __('exchange.quote_lock_help') }}
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submit">
                    {{ __('exchange.submit') }}
                </flux:button>
            </div>
        </form>
    </details>

    <details open class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="font-semibold">{{ __('exchange.my_requests') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('exchange.my_requests_help') }}</div>
                </div>
                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ $ownRequests->count() }}
                </span>
            </div>
        </summary>

        <div class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-800">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-950/60">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('exchange.kind') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('exchange.external_amount') }}</th>
                        <th class="px-4 py-3 text-end">IET</th>
                        <th class="px-4 py-3 text-start">{{ __('exchange.quote') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('exchange.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($ownRequests as $request)
                        <tr>
                            <td class="px-4 py-3">
                                {{ __('exchange.direction.'.$request->direction->value) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums">
                                &#36;{{ \App\Support\MoneyAmount::format($request->external_amount_minor, 2) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums">
                                {{ number_format($request->iet_amount) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-500" dir="ltr">
                                &#36;{{ rtrim(rtrim((string) $request->valuationQuote->usd_per_iet, '0'), '.') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                                    {{ __('exchange.statuses.'.$request->status->value) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-zinc-500">{{ __('exchange.empty_requests') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>

    @if ($canManageExchange)
        <details class="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50/40 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
            <summary class="cursor-pointer list-none px-5 py-4 hover:bg-amber-50 dark:hover:bg-amber-950/30">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold">{{ __('exchange.review_title') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ __('exchange.review_help') }}</div>
                    </div>
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold dark:bg-zinc-900">{{ $pendingReview->count() }}</span>
                </div>
            </summary>

            <div class="space-y-3 border-t border-amber-200 p-5 dark:border-amber-900">
                @forelse ($pendingReview as $request)
                    <article class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="font-semibold" dir="auto">{{ '@'.$request->user->username }}</div>
                                <div class="mt-1 text-sm text-zinc-500">
                                    {{ __('exchange.direction.'.$request->direction->value) }}
                                    · &#36;{{ \App\Support\MoneyAmount::format($request->external_amount_minor, 2) }}
                                    · {{ number_format($request->iet_amount) }} IET
                                </div>
                                @if ($request->external_reference)
                                    <div class="mt-2 text-xs text-zinc-500" dir="auto">
                                        {{ __('exchange.external_reference') }}: {{ $request->external_reference }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <flux:button wire:click="confirmRequest({{ $request->id }})" size="sm" variant="primary">
                                    {{ __('exchange.confirm') }}
                                </flux:button>
                                <flux:button wire:click="rejectRequest({{ $request->id }})" size="sm" variant="ghost">
                                    {{ __('exchange.reject') }}
                                </flux:button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <flux:input wire:model="reviewNotes.{{ $request->id }}" :label="__('exchange.review_note')" />
                        </div>
                    </article>
                @empty
                    <div class="py-6 text-center text-sm text-zinc-500">{{ __('exchange.no_pending') }}</div>
                @endforelse
            </div>
        </details>

        <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
                <div>
                    <div class="font-semibold">{{ __('exchange.valuation_admin_title') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('exchange.valuation_admin_help') }}</div>
                </div>
            </summary>

            <div class="grid gap-5 border-t border-zinc-200 p-5 dark:border-zinc-800 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <form wire:submit="publishQuote" class="space-y-4">
                    <flux:input wire:model="newUsdPerIet" :label="__('exchange.new_quote')" dir="ltr" />
                    <flux:textarea wire:model="valuationRationale" :label="__('exchange.rationale')" rows="3" />
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">{{ __('exchange.publish_quote') }}</flux:button>
                    </div>
                </form>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                    <div class="text-sm font-semibold">{{ __('exchange.signals_title') }}</div>
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach ($valuationSignals as $key => $value)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-zinc-500">{{ __('exchange.signals.'.$key) }}</dt>
                                <dd class="font-semibold tabular-nums">{{ number_format($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </details>
    @endif

    <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
            <div>
                <div class="font-semibold">{{ __('exchange.history_title') }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ __('exchange.history_help') }}</div>
            </div>
        </summary>

        <div class="divide-y divide-zinc-200 border-t border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            @foreach ($quotes as $historyQuote)
                <article class="grid gap-2 px-5 py-4 md:grid-cols-[12rem_minmax(0,1fr)_auto] md:items-center">
                    <div class="font-semibold tabular-nums" dir="ltr">
                        1 IET = &#36;{{ rtrim(rtrim((string) $historyQuote->usd_per_iet, '0'), '.') }}
                        <span class="ms-2 text-xs font-normal text-zinc-500">
                            ({{ $quotePercents[$historyQuote->id] }}%)
                        </span>
                    </div>
                    <div class="min-w-0 text-sm text-zinc-500" dir="auto">
                        {{ $historyQuote->rationale ?: __('exchange.no_rationale') }}
                    </div>
                    <div class="text-xs text-zinc-400">
                        <x-app.local-datetime :value="$historyQuote->effective_at" />
                    </div>
                </article>
            @endforeach
        </div>
    </details>
</section>
