<section class="mx-auto max-w-5xl space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div>
        <a href="{{ route('finance.index') }}" class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white">
            ← {{ __('finance_baseline.settlement.back') }}
        </a>
    </div>

    <x-app.page-header :title="__('finance_baseline.settlement.title')" :description="__('finance_baseline.settlement.help')" />

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('financial.obligation.amount') }}</div>
            <div class="mt-2 text-lg font-semibold tabular-nums">
                {{ \App\Support\MoneyAmount::format($obligation->amount_minor, $obligation->monetaryUnit->exponent) }}
                {{ $obligation->monetaryUnit->code }}
            </div>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('finance_baseline.obligations.available') }}</div>
            <div class="mt-2 text-lg font-semibold tabular-nums">
                {{ \App\Support\MoneyAmount::format($obligation->availableToSettleMinor(), $obligation->monetaryUnit->exponent) }}
                {{ $obligation->monetaryUnit->code }}
            </div>
        </div>
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('finance_baseline.settlement.wallet') }}</div>
            <div class="mt-2 text-lg font-semibold tabular-nums">{{ \App\Support\IetValueMath::formatIetMinor($availableIet) }} IET</div>
        </div>
    </div>

    @if ($obligation->monetaryUnit->code !== 'USD')
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
            {{ __('finance_baseline.settlement.non_usd') }}
        </div>
    @endif

    @if ($canPropose)
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <form wire:submit="propose" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model.live.debounce.300ms="settlementAmount"
                        :label="__('finance_baseline.settlement.amount')"
                        inputmode="decimal"
                    />
                    <flux:input wire:model="settlementNote" :label="__('finance_baseline.settlement.note')" />
                </div>

                @if ($quoteIet !== null)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                        <div class="font-semibold tabular-nums">
                            {{ __('finance_baseline.settlement.quote', ['amount' => \App\Support\IetValueMath::formatIetMinor($quoteIet)]) }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">
                            {{ __('finance_baseline.iet.x_percent', ['value' => \App\Support\IetValueMath::xPercent($snapshot->usd_pico_per_iet)]) }}
                        </div>

                        @if ($availableIet < $quoteIet)
                            <div class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-300">
                                {{ __('finance_baseline.settlement.insufficient') }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" :disabled="$quoteIet === null || $availableIet < $quoteIet">
                        {{ __('finance_baseline.settlement.propose') }}
                    </flux:button>
                </div>
            </form>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div class="font-semibold">{{ __('finance_baseline.settlement.title') }}</div>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($obligation->settlements->sortByDesc('id') as $settlement)
                <article class="space-y-3 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-semibold tabular-nums">
                                {{ \App\Support\MoneyAmount::format($settlement->amount_minor, $obligation->monetaryUnit->exponent) }}
                                {{ $obligation->monetaryUnit->code }}
                            </div>
                            @if ($settlement->iet_amount_minor !== null)
                                <div class="mt-1 text-sm text-zinc-500 tabular-nums">
                                    {{ __('finance_baseline.settlement.locked_quote', ['amount' => \App\Support\IetValueMath::formatIetMinor($settlement->iet_amount_minor)]) }}
                                    @if ($settlement->ietValuation)
                                        · {{ __('finance_baseline.settlement.locked_rate', ['value' => \App\Support\IetValueMath::xPercent($settlement->ietValuation->usd_pico_per_iet)]) }}
                                    @endif
                                </div>
                            @endif
                        </div>

                        <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                            {{ __('finance_baseline.settlement.'.$settlement->status->value) }}
                        </span>
                    </div>

                    @if ($settlement->note)
                        <div class="text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ $settlement->note }}</div>
                    @endif

                    @can('respond', $settlement)
                        @if ($settlement->iet_amount_minor !== null)
                            <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-950/60">
                                <flux:input
                                    wire:model="rejectionNotes.{{ $settlement->id }}"
                                    :label="__('finance_baseline.settlement.rejection_note')"
                                />
                                <div class="flex flex-wrap gap-2">
                                    <flux:button wire:click="confirmSettlement({{ $settlement->id }})" size="sm" variant="primary">
                                        {{ __('finance_baseline.settlement.confirm') }}
                                    </flux:button>
                                    <flux:button wire:click="rejectSettlement({{ $settlement->id }})" size="sm" variant="ghost">
                                        {{ __('finance_baseline.settlement.reject') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    @endcan
                </article>
            @empty
                <div class="px-5 py-10 text-center text-sm text-zinc-500">{{ __('finance_baseline.settlement.none') }}</div>
            @endforelse
        </div>
    </section>
</section>