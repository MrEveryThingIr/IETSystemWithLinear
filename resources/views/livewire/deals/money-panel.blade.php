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
            {{-- Where we are, in plain terms; every other figure is one tap away --}}
            <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4" data-money-summary>
                @foreach (['estimated', 'earned', 'on_account', 'received'] as $k)
                    <div class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900"><dt class="text-xs text-zinc-500">{{ __('deals.money.sum.'.$k) }}</dt><dd class="font-medium" data-sum="{{ $k }}">{{ $fmt($summary[$k]) }}</dd></div>
                @endforeach
                <div class="col-span-2 rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800 sm:col-span-4" data-money-position>
                    @if ($summary['paid_ahead'] > 0)
                        <dt class="text-xs text-zinc-500">{{ __('deals.money.sum.paid_ahead') }}</dt><dd class="font-semibold" data-sum="paid_ahead">{{ $fmt($summary['paid_ahead']) }}</dd>
                    @else
                        <dt class="text-xs text-zinc-500">{{ __('deals.money.sum.roughly_owed') }}</dt><dd class="font-semibold" data-sum="roughly_owed">{{ $fmt($summary['roughly_owed']) }}</dd>
                    @endif
                    <p class="text-xs text-zinc-500">{{ __('deals.money.position_hint') }}</p>
                </div>
            </dl>
            <details class="text-xs" data-money-more>
                <summary class="cursor-pointer text-zinc-500">{{ __('deals.money.more_figures') }}</summary>
                <dl class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach (['reported_value', 'payable', 'outstanding_due', 'not_yet_due', 'requested', 'reported', 'settled'] as $k)
                        <div class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900"><dt class="text-xs text-zinc-500">{{ __('deals.money.sum.'.$k) }}</dt><dd class="font-medium" data-sum="{{ $k }}">{{ $fmt($summary[$k]) }}@if ($k === 'settled' && $summary['items'] > 0) <span class="text-xs font-normal text-zinc-500">({{ $summary['settled_count'] }}/{{ $summary['items'] }})</span>@endif</dd></div>
                    @endforeach
                    @if ($summary['unrecorded_earnings'] > 0)
                        <div><dt>{{ __('credit.unrecorded') }}</dt><dd>{{ $fmt($summary['unrecorded_earnings']) }}</dd></div>
                    @endif
                </dl>
            </details>
            <details class="text-xs text-zinc-500" data-money-legend>
                <summary class="cursor-pointer">{{ __('deals.money.legend.title') }}</summary>
                <ul class="mt-1 list-disc space-y-1 ps-5">
                    @foreach (['estimated', 'earned', 'on_account', 'received', 'reported_value', 'payable', 'outstanding_due', 'not_yet_due', 'requested', 'reported', 'settled'] as $k)<li><span class="font-medium">{{ __('deals.money.sum.'.$k) }}:</span> {{ $k === 'on_account' ? __('credit.legend_on_account') : __('deals.money.legend.'.$k) }}</li>@endforeach
                </ul>
                <p class="mt-1 font-medium">{{ __('credit.identity') }}</p>
            </details>
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

                        <details class="mt-1 text-xs text-zinc-600 dark:text-zinc-300" data-item-details>
                            <summary class="cursor-pointer text-zinc-500">{{ __('deals.money.item_details') }}</summary>
                            <dl class="mt-1 space-y-1">
                                <div><dt class="inline text-zinc-500">{{ __('deals.money.d.version') }}:</dt> <dd class="inline">{{ __('deals.agreement_version', ['version' => $c->contractVersion->version]) }}</dd></div>
                                @if ($item['fulfillment'])
                                    <div><dt class="inline text-zinc-500">{{ __('deals.money.d.work') }}:</dt> <dd class="inline" dir="auto">{{ __('deals.money.d.work_line', ['confirmed' => $item['quantity'] + 0, 'reported' => $item['fulfillment']->quantity + 0, 'unit' => \App\Support\Deals\DealTerms::basisLabel($c->unit) ?? $c->unit]) }}@if ($item['fulfillment']->actual_start_at) · {{ \App\Support\TemporalCalendar::dateLabel($item['fulfillment']->actual_start_at, auth()->user()) }}@endif</dd></div>
                                @endif
                                @if ($item['due_on'])<div><dt class="inline text-zinc-500">{{ __('deals.money.d.due') }}:</dt> <dd class="inline">{{ __('deals.money.due.'.$item['due_rule']['rule'].(isset($item['due_rule']['period']) ? '_'.$item['due_rule']['period'] : '')) }} · {{ \App\Support\TemporalCalendar::dateLabel($item['due_on'], auth()->user()) }}</dd></div>@endif
                                <div><dt class="inline text-zinc-500">{{ __('deals.money.d.outstanding') }}:</dt> <dd class="inline">{{ $fmt($item['outstanding']) }}</dd></div>
                                <div><dt class="text-zinc-500">{{ __('deals.money.d.allocations') }}:</dt>
                                    <dd>@forelse ($item['allocations'] as $alloc)<div>{{ $fmt((int) $alloc->amount) }} · {{ __('deals.money.pay_status.'.$alloc->status) }} · <x-app.local-date :value="$alloc->occurred_on" /></div>@empty<span class="text-zinc-500">{{ __('deals.money.d.none') }}</span>@endforelse</dd></div>
                            </dl>
                        </details>

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

    {{-- Money on account: how work is often settled while it goes on. Ask, hand over, confirm; the exact accounts are settled at an inspection or at the end. --}}
    @if ($enabled && ($asProvider || $asPayer))
        <flux:card class="space-y-3" data-money-account>
            <flux:heading size="sm">{{ __('deals.money.account_title') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('deals.money.account_hint') }}</p>
            @foreach ($asks as $a)
                <div wire:key="ask-{{ $a['id'] }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 p-2 text-sm dark:bg-zinc-900" data-money-ask>
                    <span>{{ __('deals.money.asked_line', ['amount' => $fmt((int) $a['amount'])]) }}@if (! empty($a['note'])) — <span dir="auto">{{ $a['note'] }}</span>@endif</span>
                    @if ($asPayer)<flux:button size="sm" wire:click="useAsk('{{ $a['id'] }}')">{{ __('deals.money.pay_this') }}</flux:button>@endif
                </div>
            @endforeach
            @if ($asProvider)
                <div class="grid gap-2 sm:grid-cols-2">
                    <flux:input inputmode="numeric" wire:model="ask.amount" :label="__('deals.money.ask_amount')" data-ask-amount />
                    <flux:input wire:model="ask.note" :label="__('deals.money.note')" maxlength="300" />
                </div>
                <flux:button size="sm" wire:click="askMoney" wire:loading.attr="disabled" wire:target="askMoney" data-ask-send>{{ __('deals.money.ask_send') }}</flux:button>
            @endif
            @if ($asPayer)
                <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <flux:input inputmode="numeric" wire:model="account.amount" :label="__('deals.money.pay_amount')" data-account-amount />
                        <flux:select wire:model="account.purpose" :label="__('deals.money.purpose_label')">
                            @foreach (['advance', 'instalment', 'final'] as $kind)<option value="{{ $kind }}">{{ __('deals.money.purpose.'.$kind) }}</option>@endforeach
                        </flux:select>
                    </div>
                    <flux:input wire:model="text.note" :label="__('deals.money.note')" maxlength="300" />
                    <flux:input type="file" wire:model="receipt" :label="__('deals.money.receipt')" accept="image/*,application/pdf" />
                    <flux:button size="sm" variant="primary" wire:click="payOnAccount" wire:loading.attr="disabled" wire:target="payOnAccount,receipt" data-account-pay>{{ __('deals.money.pay_on_account') }}</flux:button>
                    <p class="text-xs text-zinc-500">{{ __('deals.money.report_note') }}</p>
                </div>
            @endif
        </flux:card>
    @endif

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
            <flux:select wire:model="purpose" :label="__('deals.money.purpose_label')" data-pay-purpose>
                <option value="">{{ __('deals.money.purpose.other') }}</option>
                @foreach (['advance', 'instalment', 'final'] as $kind)<option value="{{ $kind }}">{{ __('deals.money.purpose.'.$kind) }}</option>@endforeach
            </flux:select>
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
                <x-bookkeeping.credit-history :position="$creditSnapshot['payments'][$t->id] ?? null" />
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
