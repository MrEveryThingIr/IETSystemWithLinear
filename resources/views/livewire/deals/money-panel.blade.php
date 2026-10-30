@php
    $fmt = fn (int $n) => \App\Support\Bookkeeping\Toman::format($n).' '.__('deals.money.toman');
    // The same records, in each person's own words: the one who is paid for work, the one who pays, or (both ways) neutral.
    $role = $asProvider && ! $asPayer ? 'payee_' : ($asPayer && ! $asProvider ? 'payer_' : '');
    $paid = (int) $summary['received'] + (int) $summary['noncash_settled'];
    $remaining = max(0, (int) $summary['earned'] - $paid);
    $waiting = $items->filter(fn ($i) => $i['obligation'] && $i['obligation']->status === \App\Enums\Bookkeeping\TransactionStatus::Pending && (int) $i['obligation']->recorded_by_actor_id !== $me && Gate::forUser(auth()->user())->allows('confirm', $i['obligation']));
    $askable = $items->filter(fn ($i) => in_array($i['state'], ['eligible', 'disagreed'], true) && (int) $i['commitment']->obligor_actor_id === $me);
    $openAsks = collect($asks)->map(fn ($a) => $a + ['left' => max(0, (int) $a['amount'] - (int) ($a['paid'] ?? 0))]);
@endphp
<div class="space-y-3" data-money-panel>
    <flux:card class="space-y-3">
        <flux:heading>{{ __('deals.tabs.money') }}</flux:heading>
        @error('money')<p class="text-sm text-red-600" role="alert" data-money-error>{{ $message }}</p>@enderror

        @if (! $enabled)
            <p class="text-sm text-zinc-500" data-money-none>{{ __('deals.money.none') }}</p>
        @else
            {{-- Three figures: what the accepted work comes to, what has been paid (and confirmed), what is left --}}
            <dl class="grid grid-cols-3 gap-2 text-sm" data-money-summary>
                <div class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900"><dt class="text-xs text-zinc-500">{{ __('deals.money.simple_'.$role.'owed') }}</dt><dd class="font-medium" data-sum="owed">{{ $fmt((int) $summary['earned']) }}</dd></div>
                <div class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900"><dt class="text-xs text-zinc-500">{{ __('deals.money.simple_'.$role.'paid') }}</dt><dd class="font-medium" data-sum="paid">{{ $fmt($paid) }}</dd></div>
                <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800"><dt class="text-xs text-zinc-500">{{ __('deals.money.simple_'.$role.'remaining') }}</dt><dd class="font-semibold" data-sum="remaining">{{ $fmt($remaining) }}</dd></div>
            </dl>
            @if ((int) $summary['outstanding_due'] > 0)<p class="text-xs text-zinc-500" data-money-due>{{ __('deals.money.simple_due_now', ['amount' => $fmt((int) $summary['outstanding_due'])]) }}</p>@endif
            @if ((int) $summary['on_account'] > 0)<p class="text-xs text-zinc-500" data-money-ahead>{{ __('deals.money.simple_paid_ahead', ['amount' => $fmt((int) $summary['on_account'])]) }}</p>@endif
            @if ($summary['attention'] > 0)<p class="text-sm text-amber-600" data-money-attention>{{ __('deals.money.attention', ['count' => $summary['attention']]) }}</p>@endif
            @if ($items->isEmpty())<p class="text-sm text-zinc-500">{{ __('deals.money.nothing_yet') }}</p>@endif

            {{-- Amounts waiting for this person to confirm --}}
            @foreach ($waiting as $item)
                @php($o = $item['obligation'])
                <div wire:key="wait-{{ $o->uuid }}" class="space-y-2 rounded-xl border border-amber-300 p-3 text-sm dark:border-amber-700" data-money-waiting>
                    <div class="font-medium" dir="auto">{{ $item['commitment']->title }} · {{ $fmt((int) $o->amount) }}</div>
                    <flux:input size="sm" wire:model="text.no-{{ $o->uuid }}" :label="__('deals.money.reason')" maxlength="300" />
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" wire:click="agree('{{ $o->uuid }}')">{{ __('deals.money.agree') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="disagree('{{ $o->uuid }}')">{{ __('deals.money.disagree') }}</flux:button>
                    </div>
                </div>
            @endforeach

            {{-- Accepted work whose amount has not been recorded yet: the doer can still ask --}}
            @if ($askable->isNotEmpty())
                @foreach ($askable as $item)
                    <div wire:key="ask-item-{{ $item['key'] }}" class="flex flex-wrap items-end gap-3 text-sm">
                        <flux:checkbox wire:model="selected.{{ $item['key'] }}" :label="$item['commitment']->title" />
                        @if ($item['derived'] === null)<flux:input size="sm" inputmode="numeric" wire:model="typed.{{ $item['key'] }}" :label="__('deals.money.amount')" class="max-w-40" />@endif
                    </div>
                @endforeach
                <flux:button size="sm" variant="primary" wire:click="request" wire:loading.attr="disabled" wire:target="request" data-money-request>{{ __('deals.money.request') }}</flux:button>
            @endif
        @endif
    </flux:card>

    {{-- Payment requests and paying: ask for a share or an amount; the payer pays, the recipient confirms --}}
    @if ($enabled && ($asProvider || $asPayer))
        <flux:card class="space-y-3" data-money-account>
            <flux:heading size="sm">{{ __('deals.money.account_title') }}</flux:heading>
            @foreach ($openAsks as $a)
                <div wire:key="ask-{{ $a['id'] }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-zinc-50 p-2 text-sm dark:bg-zinc-900" data-money-ask>
                    <span>{{ __('deals.money.simple_request_left', ['left' => $fmt($a['left']), 'amount' => $fmt((int) $a['amount'])]) }}@if (! empty($a['note'])) — <span dir="auto">{{ $a['note'] }}</span>@endif</span>
                    @if ($asPayer)<flux:button size="sm" wire:click="useAsk('{{ $a['id'] }}')">{{ __('deals.money.pay_this') }}</flux:button>@endif
                </div>
            @endforeach
            @if ($asProvider)
                <div class="flex flex-wrap gap-2" data-ask-percent>
                    @foreach ([25, 50, 75, 100] as $pct)<flux:button size="sm" wire:click="askPercent({{ $pct }})" wire:loading.attr="disabled" wire:target="askPercent">{{ __('deals.money.simple_ask_percent', ['pct' => $pct]) }}</flux:button>@endforeach
                </div>
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

    {{-- Payment history: who paid whom, how much, and whether it is confirmed --}}
    @if ($payments->isNotEmpty())
        <flux:card class="space-y-2" data-money-payments>
            <flux:heading size="sm">{{ __('deals.money.simple_history_title') }}</flux:heading>
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

    {{-- Everything else lives in one collapsed section: per-work breakdown, how payments were applied, the shared book --}}
    @if ($ledger || $items->isNotEmpty())
        <details class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-money-details>
            <summary class="cursor-pointer font-medium">{{ __('deals.money.details') }}</summary>
            <ul class="mt-2 space-y-2" data-money-items>
                @foreach ($items as $item)
                    @php($c = $item['commitment'])
                    <li wire:key="item-{{ $item['key'] }}" class="text-xs" data-money-item data-state="{{ $item['state'] }}">
                        <div class="font-medium" dir="auto">{{ $c->title }} · {{ $fmt((int) $item['earned']) }} · {{ __('deals.money.state.'.$item['state']) }}</div>
                        <div class="text-zinc-500">{{ __('deals.money.who_pays', ['payer' => $c->beneficiary->user?->username, 'payee' => $c->obligor->user?->username]) }}
                            · {{ __('deals.money.d.outstanding') }}: {{ $fmt((int) $item['outstanding']) }}
                            @if ($item['due_on']) · {{ __('deals.money.d.due') }}: {{ \App\Support\TemporalCalendar::dateLabel($item['due_on'], auth()->user()) }}@endif
                            · {{ __('deals.agreement_version', ['version' => $c->contractVersion->version]) }}</div>
                    </li>
                @endforeach
            </ul>
            @if ($ledger)
                <p class="mt-2 text-xs text-zinc-500">{{ __('deals.money.details_note') }}</p>
                <a class="mt-2 inline-block text-sm underline" href="{{ route('bookkeeping.books.show', $ledger) }}">{{ __('deals.money.view_book') }}</a>
            @endif
        </details>
    @endif
</div>
