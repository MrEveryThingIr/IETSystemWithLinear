<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('financial.obligation.title')" :description="__('financial.obligation.help')">
        <x-slot:actions>
            <flux:badge>{{ $obligation->monetaryUnit->code }}</flux:badge>
        </x-slot:actions>
    </x-app.page-header>

    @if ($obligation->isDisputed())
        <flux:callout variant="danger">{{ __('financial.obligation.frozen') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('financial.obligation.amount') }}</div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ AppSupportMoneyAmount::format($obligation->amount_minor, $obligation->monetaryUnit->exponent) }}
                            {{ $obligation->monetaryUnit->code }}
                        </div>
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('financial.paid') }}</div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ AppSupportMoneyAmount::format($obligation->confirmedPaidMinor(), $obligation->monetaryUnit->exponent) }}
                        </div>
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">
                            {{ $obligation->isDisputed() ? __('financial.disputed') : __('financial.outstanding') }}
                        </div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ AppSupportMoneyAmount::format($obligation->outstandingMinor(), $obligation->monetaryUnit->exponent) }}
                        </div>
                    </div>
                </div>

                @if ($obligation->description)
                    <div class="whitespace-pre-wrap" dir="auto">{{ $obligation->description }}</div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('financial.obligation.debtor') }}</div>
                        <x-app.actor-identity :actor="$obligation->debtor" size="sm" class="mt-2" />
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('financial.obligation.creditor') }}</div>
                        <x-app.actor-identity :actor="$obligation->creditor" size="sm" class="mt-2" />
                    </div>
                </div>

                <div class="text-sm text-zinc-500">
                    {{ __('financial.obligation.recognized', [
                        'time' => $obligation->recognized_at->setTimezone($timezone)->format('Y-m-d H:i'),
                    ]) }}
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('financial.obligation.accounting') }}</flux:heading>
                    <flux:text>{{ __('financial.obligation.accounting_help') }}</flux:text>
                </div>

                @if ($obligationAccountingPosted)
                    <flux:badge>{{ __('financial.obligation.accounting_posted') }}</flux:badge>
                @elseif ($canPostObligationAccounting)
                    <flux:button wire:click="postObligationAccounting" variant="primary">
                        {{ __('financial.obligation.post_accounting') }}
                    </flux:button>
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('financial.settlement.title') }}</flux:heading>
                    <flux:text>{{ __('financial.settlement.help') }}</flux:text>
                </div>

                @if ($canProposeSettlement)
                    <form wire:submit="proposeSettlement" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="settlementAmount" :label="__('financial.settlement.amount')" />
                            <flux:input wire:model="settlementPaidAt" type="datetime-local" :label="__('financial.settlement.paid_at')" />
                            <flux:input wire:model="settlementMethod" :label="__('financial.settlement.method')" maxlength="80" />
                            <flux:input wire:model="settlementReference" :label="__('financial.settlement.reference')" maxlength="255" />
                        </div>
                        <flux:textarea wire:model="settlementNote" :label="__('financial.settlement.note')" rows="2" />
                        <flux:button type="submit" variant="primary">{{ __('financial.settlement.propose') }}</flux:button>
                    </form>
                @endif

                <div class="space-y-4">
                    @forelse ($obligation->settlements->sortByDesc('id') as $settlement)
                        <article wire:key="settlement-{{ $settlement->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold">
                                        {{ AppSupportMoneyAmount::format($settlement->amount_minor, $obligation->monetaryUnit->exponent) }}
                                        {{ $obligation->monetaryUnit->code }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $settlement->paid_at->setTimezone($timezone)->format('Y-m-d H:i') }}
                                        · {{ $settlement->proposedBy->user?->username }}
                                    </div>
                                </div>
                                <flux:badge>{{ __('financial.settlement.'.$settlement->status->value) }}</flux:badge>
                            </div>

                            @if ($settlement->method || $settlement->reference)
                                <div class="text-sm text-zinc-500">
                                    {{ $settlement->method }}
                                    @if ($settlement->reference)
                                        · {{ $settlement->reference }}
                                    @endif
                                </div>
                            @endif

                            @if ($settlement->note)
                                <div class="whitespace-pre-wrap text-sm" dir="auto">{{ $settlement->note }}</div>
                            @endif

                            @can('respond', $settlement)
                                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                    <flux:input wire:model="rejectionNotes.{{ $settlement->id }}" :label="__('financial.settlement.rejection_reason')" />
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="confirmSettlement({{ $settlement->id }})" size="sm" variant="primary">
                                            {{ __('financial.settlement.confirm') }}
                                        </flux:button>
                                        <flux:button wire:click="rejectSettlement({{ $settlement->id }})" size="sm" variant="danger">
                                            {{ __('financial.settlement.reject') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endcan

                            @if ($settlement->status === AppSettlementStatus::Confirmed)
                                @if ($settlementAccounting->has($settlement->uuid))
                                    <flux:badge color="zinc">{{ __('financial.settlement.accounting_posted') }}</flux:badge>
                                @else
                                    @can('postAccounting', $settlement)
                                        <flux:button wire:click="postSettlementAccounting({{ $settlement->id }})" size="sm" variant="ghost">
                                            {{ __('financial.settlement.post_accounting') }}
                                        </flux:button>
                                    @endcan
                                @endif
                            @endif
                        </article>
                    @empty
                        <x-app.empty-state :title="__('financial.settlement.none')" />
                    @endforelse
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading>{{ __('financial.obligation.source') }}</flux:heading>
                <flux:button
                    :href="route('commitments.show', $obligation->fulfillment->commitment).'#fulfillment-'.$obligation->fulfillment->uuid"
                    variant="ghost"
                    class="w-full"
                >
                    {{ $obligation->fulfillment->commitment->title }}
                </flux:button>
                <flux:button
                    :href="route('contracts.show', $obligation->fulfillment->commitment->contractVersion->contract)"
                    variant="ghost"
                    class="w-full"
                >
                    {{ $obligation->fulfillment->commitment->contractVersion->contract->title }}
                </flux:button>
            </flux:card>

            <flux:callout>{{ __('financial.boundary') }}</flux:callout>
        </div>
    </div>
</section>
