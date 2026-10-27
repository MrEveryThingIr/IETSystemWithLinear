@php
    $fmt = fn (int $n) => \App\Support\Bookkeeping\Toman::format($n).' '.__('deals.money.toman');
    $payable = $items->filter(fn ($i) => $i['payable'] && (int) $i['commitment']->beneficiary_actor_id === $me);
@endphp
<div class="space-y-3" data-money-panel>
    <flux:card class="space-y-3">
        <flux:heading>{{ __('deals.tabs.money') }}</flux:heading>
        @error('money')<p class="text-sm text-red-600" role="alert" data-money-error>{{ $message }}</p>@enderror

        @if (! $enabled)
            <p class="text-sm text-zinc-500" data-money-none>{{ __('deals.money.none') }}</p>
        @else
            <p class="text-sm text-zinc-500">{{ __('deals.money.intro') }}</p>

            {{-- One summary; every figure comes from the accepted terms, confirmed work or the shared book --}}
            <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3" data-money-summary>
                @foreach (['owed', 'unrequested', 'requested', 'reported', 'received', 'outstanding'] as $k)
                    <div class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900"><dt class="text-xs text-zinc-500">{{ __('deals.money.sum.'.$k) }}</dt><dd class="font-medium" data-sum="{{ $k }}">{{ $fmt($summary[$k]) }}</dd></div>
                @endforeach
            </dl>
            @if ($summary['attention'] > 0)<p class="text-sm text-amber-600" data-money-attention>{{ __('deals.money.attention', ['count' => $summary['attention']]) }}</p>@endif

            @if ($items->isEmpty())
                <p class="text-sm text-zinc-500">{{ __('deals.money.nothing_yet') }}</p>
            @endif

            @php($askable = $items->filter(fn ($i) => in_array($i['state'], ['eligible', 'disagreed'], true) && (int) $i['commitment']->obligor_actor_id === $me))
            <ul class="space-y-2">
                @foreach ($items as $item)
                    @php($c = $item['commitment'])
                    @php($o = $item['obligation'])
                    @php($mine = (int) $c->beneficiary_actor_id === $me)
                    <li wire:key="item-{{ $item['key'] }}" class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-money-item data-state="{{ $item['state'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <span class="font-medium" dir="auto">{{ $c->title }}@if ($item['fulfillment']?->occurrence?->scheduled_start_at) · <x-app.local-datetime :value="$item['fulfillment']->occurrence->scheduled_start_at" />@endif</span>
                            <flux:badge size="sm" :color="in_array($item['state'], ['settled'], true) ? 'green' : (in_array($item['state'], ['disputed', 'disagreed'], true) ? 'red' : 'zinc')">{{ __('deals.money.state.'.$item['state']) }}</flux:badge>
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">
                            {{ __('deals.money.who_pays', ['payer' => $c->beneficiary->user?->username, 'payee' => $c->obligor->user?->username]) }}
                            · @if ($item['derived'] !== null){{ __('deals.money.from_agreement', ['amount' => $fmt($item['derived'])]) }}@elseif ($o){{ __('deals.money.asked', ['amount' => $fmt($item['requested'])]) }}@else{{ __('deals.money.no_amount') }}@endif
                            @if ($item['received'] > 0 || $item['reported'] > 0) · {{ __('deals.money.progress', ['received' => $fmt($item['received']), 'reported' => $fmt($item['reported'])]) }}@endif
                        </div>

                        {{-- The doer asks; the amount is fixed by the terms, or typed and then agreed by the payer --}}
                        @if ($askable->contains(fn ($a) => $a['key'] === $item['key']))
                            <div class="mt-2 flex flex-wrap items-end gap-3">
                                <flux:checkbox wire:model="selected.{{ $item['key'] }}" :label="__('deals.money.ask_for_this')" />
                                @if ($item['derived'] === null)<flux:input size="sm" inputmode="numeric" wire:model="typed.{{ $item['key'] }}" :label="__('deals.money.amount')" class="max-w-40" />@endif
                            </div>
                        @endif

                        {{-- The payer answers an amount that terms cannot settle (or disagrees with any) --}}
                        @if ($o && $mine && $o->status === \App\Enums\Bookkeeping\TransactionStatus::Pending && Gate::forUser(auth()->user())->allows('confirm', $o))
                            <div class="mt-2 space-y-2">
                                <flux:input size="sm" wire:model="text.no-{{ $o->uuid }}" :label="__('deals.money.reason')" maxlength="300" />
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" variant="primary" wire:click="agree('{{ $o->uuid }}')">{{ __('deals.money.agree') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="disagree('{{ $o->uuid }}')">{{ __('deals.money.disagree') }}</flux:button>
                                </div>
                            </div>
                        @endif
                        @if ($o && $item['reported'] === 0 && $item['received'] === 0 && Gate::forUser(auth()->user())->allows('withdraw', $o))
                            <flux:button class="mt-2" size="sm" variant="ghost" wire:click="takeBack('{{ $o->uuid }}')">{{ __('deals.money.take_back') }}</flux:button>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($askable->isNotEmpty())
                <flux:button variant="primary" wire:click="request" wire:loading.attr="disabled" wire:target="request" data-money-request>{{ __('deals.money.request') }}</flux:button>
                <p class="text-xs text-zinc-500">{{ __('deals.money.request_note') }}</p>
            @endif
        @endif
    </flux:card>

    {{-- Reporting a payment that was really made. It proves nothing until the recipient confirms. --}}
    @if ($payable->isNotEmpty())
        <flux:card class="space-y-3" data-money-pay>
            <flux:heading size="sm">{{ __('deals.money.report_title') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('deals.money.report_note') }}</p>
            @foreach ($payable as $item)
                @php($left = max(0, $item['outstanding'] - $item['reported']))
                <div wire:key="pay-{{ $item['obligation']->uuid }}-{{ $payToken }}" class="flex flex-wrap items-end gap-3">
                    <span class="min-w-0 flex-1 text-sm" dir="auto">{{ $item['commitment']->title }} <span class="text-xs text-zinc-500">({{ __('deals.money.open', ['amount' => $fmt($left)]) }})</span></span>
                    <flux:input size="sm" inputmode="numeric" wire:model="pay.{{ $item['obligation']->uuid }}" :placeholder="(string) $left" :label="__('deals.money.amount')" class="max-w-40" />
                </div>
            @endforeach
            <flux:input type="file" wire:model="receipt" :label="__('deals.money.receipt')" accept="image/*,application/pdf" />
            <flux:input wire:model="text.note" :label="__('deals.money.note')" maxlength="300" />
            <flux:button variant="primary" wire:click="report" wire:loading.attr="disabled" wire:target="report,receipt" data-money-report>{{ __('deals.money.report') }}</flux:button>
        </flux:card>
    @endif

    @if ($payments->isNotEmpty())
        <flux:card class="space-y-2" data-money-payments>
            <flux:heading size="sm">{{ __('deals.money.payments') }}</flux:heading>
            @foreach ($payments as $row)
                @php($t = $row['entry'])
                <div wire:key="payment-{{ $t->uuid }}" class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-money-payment data-status="{{ $t->status->value }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <span class="font-medium">{{ __('deals.money.paid_by', ['payer' => $t->fromParty->label(), 'payee' => $t->toParty->label(), 'amount' => $fmt((int) $t->amount)]) }}</span>
                        <flux:badge size="sm" :color="$t->status === \App\Enums\Bookkeeping\TransactionStatus::Confirmed ? 'green' : (in_array($t->status, [\App\Enums\Bookkeeping\TransactionStatus::Rejected, \App\Enums\Bookkeeping\TransactionStatus::Disputed], true) ? 'red' : 'zinc')">{{ __('deals.money.pay_status.'.$t->status->value) }}</flux:badge>
                    </div>
                    <div class="text-xs text-zinc-500" dir="auto"><x-app.local-date :value="$t->occurred_on" />@if ($t->description) · {{ $t->description }}@endif</div>
                    @if ($row['can_confirm'])
                        <div class="mt-2 space-y-2">
                            <flux:input size="sm" wire:model="text.no-{{ $t->uuid }}" :label="__('deals.money.reason')" maxlength="300" />
                            <div class="flex flex-wrap gap-2">
                                <flux:button size="sm" variant="primary" wire:click="received('{{ $t->uuid }}')">{{ __('deals.money.confirm_received') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="notReceived('{{ $t->uuid }}')">{{ __('deals.money.not_received') }}</flux:button>
                            </div>
                        </div>
                    @endif
                    @if ($row['can_take_back'])<flux:button class="mt-2" size="sm" variant="ghost" wire:click="takeBack('{{ $t->uuid }}')">{{ __('deals.money.take_back') }}</flux:button>@endif
                    @if ($row['can_dispute'])
                        <details class="mt-2"><summary class="cursor-pointer text-xs text-zinc-500">{{ __('deals.money.dispute') }}</summary>
                            <div class="mt-1 flex flex-wrap items-end gap-2">
                                <flux:input size="sm" wire:model="text.dispute-{{ $t->uuid }}" :label="__('deals.money.reason')" maxlength="300" />
                                <flux:button size="sm" variant="danger" wire:click="dispute('{{ $t->uuid }}')">{{ __('deals.money.dispute_open') }}</flux:button>
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </flux:card>
    @endif

    @if ($ledger)
        <details class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-money-details>
            <summary class="cursor-pointer font-medium">{{ __('deals.money.details') }}</summary>
            <p class="mt-2 text-xs text-zinc-500">{{ __('deals.money.details_note') }}</p>
            <a class="mt-2 inline-block text-sm underline" href="{{ route('bookkeeping.books.show', $ledger) }}">{{ __('deals.money.view_book') }}</a>
        </details>
    @endif
</div>
