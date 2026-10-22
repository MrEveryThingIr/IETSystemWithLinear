@php
    $toman = fn (int $value): string => \App\Support\Bookkeeping\Toman::format($value);
    $label = fn ($party): string => $party ? ((int) $party->id === (int) ($me?->id ?? 0) ? __('bookkeeping.you') : $party->label()) : '—';
    $statusColor = fn ($status): string => match ($status) {
        \App\Enums\Bookkeeping\TransactionStatus::Confirmed => 'green',
        \App\Enums\Bookkeeping\TransactionStatus::Pending => 'amber',
        \App\Enums\Bookkeeping\TransactionStatus::Disputed => 'red',
        default => 'zinc',
    };
    $waitingForMe = $entries->filter(fn ($entry) => $me && ($awaiting[$entry->uuid] ?? collect())->contains('id', $me->id))->values();
    $sentence = function ($entry) use ($label, $toman): string {
        $key = match ($entry->type) {
            \App\Enums\Bookkeeping\TransactionType::Obligation => 'bookkeeping.sentences.obligation',
            \App\Enums\Bookkeeping\TransactionType::Payment => 'bookkeeping.sentences.payment',
            \App\Enums\Bookkeeping\TransactionType::Refund => 'bookkeeping.sentences.refund',
            \App\Enums\Bookkeeping\TransactionType::WriteOff => 'bookkeeping.sentences.write_off',
            \App\Enums\Bookkeeping\TransactionType::SettlementInKind => 'bookkeeping.sentences.settlement_in_kind',
            \App\Enums\Bookkeeping\TransactionType::Adjustment => $entry->direction === \App\Enums\Bookkeeping\AdjustmentDirection::Decrease ? 'bookkeeping.sentences.adjust_down' : 'bookkeeping.sentences.adjust_up',
            default => 'bookkeeping.sentences.payment',
        };

        return __($key, ['from' => $label($entry->fromParty), 'to' => $label($entry->toParty), 'amount' => $toman((int) $entry->amount)]);
    };
@endphp

<section class="mx-auto max-w-6xl space-y-6" data-book-show>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:button :href="route('bookkeeping.index')" variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ __('bookkeeping.title') }}</flux:button>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('contexts.conversation', $ledger->context)" variant="ghost" size="sm" icon="chat-bubble-left-right">{{ __('bookkeeping.book_show.chat') }}</flux:button>
            <flux:button x-on:click="$dispatch('open-drawer', 'people')" variant="ghost" size="sm" icon="users" data-open-people>{{ trans_choice('bookkeeping.index.people', $activeParties->count(), ['count' => $activeParties->count()]) }}</flux:button>
        </div>
    </div>

    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="xl" level="1" dir="auto">{{ $ledger->name }}</flux:heading>
                <flux:badge size="sm">{{ __('bookkeeping.kinds.'.$ledger->kind->value) }}</flux:badge>
                @if ($ledger->status === \App\Enums\Bookkeeping\LedgerStatus::Archived)
                    <flux:badge size="sm" color="zinc">{{ __('bookkeeping.archived') }}</flux:badge>
                @endif
            </div>
            @if ($ledger->description)
                <flux:text dir="auto">{{ $ledger->description }}</flux:text>
            @endif
            <div class="text-xs text-zinc-500">{{ __('bookkeeping.currency_note') }}</div>
        </div>
        @if ($canRecord)
            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="openRecord('obligation')" variant="primary" icon="document-plus" data-record-debt>{{ __('bookkeeping.actions.record_debt') }}</flux:button>
                <flux:button wire:click="openRecord('payment')" variant="primary" icon="banknotes" data-record-payment>{{ __('bookkeeping.actions.record_payment') }}</flux:button>
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="ellipsis-horizontal" :aria-label="__('bookkeeping.actions.more')" />
                    <flux:menu>
                        <flux:menu.item wire:click="openRecord('settlement_in_kind')" icon="gift">{{ __('bookkeeping.types.settlement_in_kind') }}</flux:menu.item>
                        <flux:menu.item wire:click="openRecord('write_off')" icon="hand-raised">{{ __('bookkeeping.types.write_off') }}</flux:menu.item>
                        <flux:menu.item wire:click="openRecord('refund')" icon="arrow-uturn-left">{{ __('bookkeeping.types.refund') }}</flux:menu.item>
                        @if ($canArchive)
                            <flux:menu.separator />
                            <flux:menu.item wire:click="archiveBook" wire:confirm="{{ __('bookkeeping.book_show.archive_confirm') }}" icon="archive-box">
                                {{ $ledger->status === \App\Enums\Bookkeeping\LedgerStatus::Active ? __('bookkeeping.book_show.archive') : __('bookkeeping.book_show.reopen') }}
                            </flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        @endif
    </header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    {{-- Balances --}}
    <div class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
        @if ($myPosition)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-my-position>
                @if ($myPosition['net'] > 0)
                    <div class="text-sm text-emerald-700 dark:text-emerald-400">{{ __('bookkeeping.balances.they_owe_you') }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums" data-position-amount>{{ $toman($myPosition['net']) }}</div>
                @elseif ($myPosition['net'] < 0)
                    <div class="text-sm text-red-700 dark:text-red-400">{{ __('bookkeeping.balances.you_owe') }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums" data-position-amount>{{ $toman(-$myPosition['net']) }}</div>
                @else
                    <div class="text-sm text-zinc-500">{{ __('bookkeeping.balances.settled') }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums" data-position-amount>0</div>
                @endif
                <div class="text-xs text-zinc-500">{{ __('bookkeeping.toman') }} · {{ __('bookkeeping.balances.confirmed_only') }}</div>
                @if ($myPosition['pending_change'] !== 0)
                    <div class="mt-3 rounded-lg bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200" data-pending-change>
                        {{ __('bookkeeping.balances.after_confirmation', ['amount' => $toman(abs($myPosition['net'] + $myPosition['pending_change']))]) }}
                        ({{ $myPosition['net'] + $myPosition['pending_change'] >= 0 ? __('bookkeeping.balances.they_owe_you') : __('bookkeeping.balances.you_owe') }})
                    </div>
                @endif
                @if ($myPosition['disputed'] > 0)
                    <div class="mt-2 rounded-lg bg-red-50 p-2 text-sm text-red-900 dark:bg-red-950/30 dark:text-red-200">{{ __('bookkeeping.balances.disputed_amount', ['amount' => $toman($myPosition['disputed'])]) }}</div>
                @endif
            </div>
        @endif

        <div class="space-y-2 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-pairs>
            <div class="text-sm font-medium">{{ __('bookkeeping.book_show.who_owes_whom') }}</div>
            @forelse ($pairs as $pair)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 py-2 text-sm last:border-0 dark:border-zinc-800">
                    <span dir="auto">{{ __('bookkeeping.book_show.owes', ['debtor' => $label($pair['debtor']), 'creditor' => $label($pair['creditor'])]) }}</span>
                    <span class="flex items-center gap-2">
                        <strong class="tabular-nums">{{ $toman(max(0, $pair['confirmed'])) }}</strong>
                        @if ($pair['pending'] !== 0)
                            <flux:badge size="sm" color="amber">{{ __('bookkeeping.balances.waiting_short', ['amount' => ($pair['pending'] > 0 ? '+' : '−').$toman(abs($pair['pending']))]) }}</flux:badge>
                        @endif
                        @if ($pair['disputed'] !== 0)
                            <flux:badge size="sm" color="red">{{ __('bookkeeping.balances.disputed_short', ['amount' => $toman(abs($pair['disputed']))]) }}</flux:badge>
                        @endif
                    </span>
                </div>
            @empty
                <flux:text>{{ __('bookkeeping.book_show.nothing_owed') }}</flux:text>
            @endforelse
        </div>
    </div>

    {{-- Waiting for me --}}
    @if ($waitingForMe->isNotEmpty())
        <flux:card class="space-y-3 border-amber-300 dark:border-amber-800" data-book-waiting>
            <flux:heading size="lg">{{ __('bookkeeping.index.please_confirm') }}</flux:heading>
            @foreach ($waitingForMe as $entry)
                <div wire:key="wait-{{ $entry->uuid }}" class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                    <div class="font-medium" dir="auto">{{ $sentence($entry) }}</div>
                    <div class="text-xs text-zinc-500" dir="auto">
                        {{ __('bookkeeping.book_show.recorded_by', ['who' => $entry->recorder?->user?->username ?? '']) }} · <x-app.local-date :value="$entry->occurred_on" />
                        @if ($entry->description) · {{ $entry->description }} @endif
                    </div>
                    @foreach ($entry->assets as $asset)
                        <a href="{{ route('bookkeeping.books.evidence', [$ledger, $entry, $asset]) }}" target="_blank" class="inline-flex items-center gap-1 text-sm underline-offset-4 hover:underline">📎 {{ $asset->original_filename }}</a>
                    @endforeach
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <input type="text" wire:model="notes.{{ $entry->uuid }}" placeholder="{{ __('bookkeeping.book_show.note_placeholder') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" maxlength="1000" />
                        <div class="flex gap-2">
                            <flux:button wire:click="approve('{{ $entry->uuid }}')" size="sm" variant="primary" icon="check" data-approve>{{ __('bookkeeping.actions.confirm') }}</flux:button>
                            <flux:button wire:click="reject('{{ $entry->uuid }}')" size="sm" variant="danger" data-reject>{{ __('bookkeeping.actions.reject') }}</flux:button>
                        </div>
                    </div>
                    @error('rejectNote')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </flux:card>
    @endif

    {{-- Debts with progress --}}
    @if ($debts->isNotEmpty())
        <flux:card class="space-y-3" data-debts>
            <flux:heading size="lg">{{ __('bookkeeping.book_show.debts') }}</flux:heading>
            @foreach ($debts as $row)
                @php
                    $debt = $row['debt'];
                    $progress = $row['progress'];
                    $paidPercent = $progress['amount'] > 0 ? min(100, (int) floor($progress['paid'] * 100 / $progress['amount'])) : 100;
                    $pendingPercent = $progress['amount'] > 0 ? min(100 - $paidPercent, (int) floor(max(0, $progress['pending']) * 100 / $progress['amount'])) : 0;
                @endphp
                <div wire:key="debt-{{ $debt->uuid }}" class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800" data-debt="{{ $progress['state'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-medium" dir="auto">{{ $debt->description ?: $sentence($debt) }}</div>
                            <div class="text-xs text-zinc-500" dir="auto">
                                {{ __('bookkeeping.book_show.owes', ['debtor' => $label($debt->fromParty), 'creditor' => $label($debt->toParty)]) }}
                                · <x-app.local-date :value="$debt->occurred_on" />
                                @if ($debt->due_on) · {{ __('bookkeeping.book_show.due') }} <x-app.local-date :value="$debt->due_on" /> @endif
                            </div>
                        </div>
                        <flux:badge size="sm" :color="match ($progress['state']) { 'paid' => 'green', 'partially_paid' => 'blue', 'unpaid' => 'zinc', 'pending' => 'amber', default => 'red' }">{{ __('bookkeeping.progress.'.$progress['state']) }}</flux:badge>
                    </div>
                    <div class="flex h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" aria-hidden="true">
                        <div class="bg-emerald-500" style="width: {{ $paidPercent }}%"></div>
                        <div class="bg-amber-300" style="width: {{ $pendingPercent }}%"></div>
                    </div>
                    <div class="text-sm" data-debt-progress>
                        {{ __('bookkeeping.book_show.paid_of', ['paid' => $toman($progress['paid']), 'amount' => $toman($progress['amount']), 'left' => $toman($progress['outstanding'])]) }}
                        @if ($progress['pending'] > 0)
                            <span class="text-amber-700 dark:text-amber-300">· {{ __('bookkeeping.book_show.waiting_payment', ['amount' => $toman($progress['pending'])]) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endif

    {{-- History --}}
    <flux:card class="space-y-4" data-history>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('bookkeeping.book_show.history') }}</flux:heading>
            <div class="inline-flex max-w-full flex-wrap rounded-lg bg-zinc-100 p-1 text-sm dark:bg-zinc-800">
                @foreach (['all', 'waiting', 'debts', 'payments'] as $option)
                    <button type="button" wire:click="$set('filter', '{{ $option }}')" class="rounded-md px-3 py-1 {{ $filter === $option ? 'bg-white shadow dark:bg-zinc-900' : 'text-zinc-600 dark:text-zinc-300' }}">{{ __('bookkeeping.filters.'.$option) }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm" data-origin-filter>
            <span class="text-zinc-500">{{ __('bookkeeping.origin.filter') }}</span>
            @foreach (['all', 'manual', 'iet_workflow', 'import', 'correction'] as $option)
                <button type="button" wire:click="$set('origin', '{{ $option }}')" aria-pressed="{{ $origin === $option ? 'true' : 'false' }}" class="min-h-9 rounded-full border px-3 py-1 {{ $origin === $option ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-700' }}">{{ __('bookkeeping.origin.'.$option) }}</button>
            @endforeach
        </div>

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($entries as $entry)
                @php
                    $waiting = $awaiting[$entry->uuid] ?? collect();
                    $dispute = $openDisputes->get($entry->id);
                    $oneSided = $rules->oneSided($entry);
                @endphp
                <article wire:key="entry-{{ $entry->uuid }}" id="entry-{{ $entry->uuid }}" class="scroll-mt-24 space-y-2 py-3" data-entry="{{ $entry->status->value }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" color="zinc">{{ __('bookkeeping.types.'.$entry->type->value) }}</flux:badge>
                                @if ($entry->origin !== \App\Enums\Bookkeeping\EntryOrigin::Manual)
                                    <flux:badge size="sm" :color="$entry->origin === \App\Enums\Bookkeeping\EntryOrigin::IetWorkflow ? 'indigo' : 'zinc'" data-entry-origin="{{ $entry->origin->value }}">{{ __('bookkeeping.origin.'.$entry->origin->value) }}</flux:badge>
                                @endif
                                <span class="font-medium" dir="auto">{{ $sentence($entry) }}</span>
                            </div>
                            <div class="mt-1 text-xs text-zinc-500" dir="auto">
                                <x-app.local-date :value="$entry->occurred_on" />
                                · {{ __('bookkeeping.book_show.recorded_by', ['who' => $entry->recorder?->user?->username ?? '']) }}
                                @if ($entry->description) · {{ $entry->description }} @endif
                                @if ($entry->source_type === 'fulfillment' && ($source = $workSources->get($entry->source_uuid)) && $source->commitment)
                                    · <a href="{{ route('commitments.show', $source->commitment) }}#fulfillment-{{ $entry->source_uuid }}" class="inline-block py-1 underline">{{ __('bookkeeping.origin.open_work') }}</a>
                                @endif
                                @if ($entry->wallet && (int) $entry->wallet->ledger->owner_actor_id === (int) auth()->user()->actor?->id)
                                    · {{ __('bookkeeping.book_show.from_my_wallet', ['wallet' => $entry->wallet->name]) }}
                                @endif
                            </div>
                            @if ($entry->allocations->isNotEmpty())
                                <div class="mt-1 text-xs text-zinc-500" dir="auto">
                                    {{ __('bookkeeping.book_show.applied_to') }}
                                    @foreach ($entry->allocations as $allocation)
                                        {{ $allocation->obligation?->description ?: __('bookkeeping.types.obligation') }} ({{ $toman((int) $allocation->amount) }})@if (! $loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($entry->assets->isNotEmpty())
                                <flux:badge size="sm" color="blue" icon="paper-clip">{{ __('bookkeeping.evidence.has') }}</flux:badge>
                            @endif
                            @if ($oneSided)
                                <flux:badge size="sm" color="zinc">{{ __('bookkeeping.book_show.one_sided') }}</flux:badge>
                            @endif
                            <flux:badge size="sm" :color="$statusColor($entry->status)">{{ __('bookkeeping.status.'.$entry->status->value) }}</flux:badge>
                        </div>
                    </div>

                    @if ($entry->assets->isNotEmpty())
                        <div class="flex flex-wrap gap-3 text-sm">
                            @foreach ($entry->assets as $asset)
                                <a href="{{ route('bookkeeping.books.evidence', [$ledger, $entry, $asset]) }}" target="_blank" class="inline-flex items-center gap-1 underline-offset-4 hover:underline" data-evidence-link>📎 {{ $asset->original_filename }}</a>
                            @endforeach
                        </div>
                    @endif

                    @if ($entry->status === \App\Enums\Bookkeeping\TransactionStatus::Pending && $waiting->isNotEmpty())
                        <div class="text-xs text-amber-700 dark:text-amber-300">{{ __('bookkeeping.book_show.waiting_for', ['names' => $waiting->map(fn ($party) => $label($party))->implode('، ')]) }}</div>
                    @endif

                    @foreach ($entry->confirmations as $confirmation)
                        @if ($confirmation->note)
                            <div class="text-xs text-zinc-500" dir="auto">{{ $confirmation->actor?->user?->username }}: “{{ $confirmation->note }}”</div>
                        @endif
                    @endforeach

                    @if ($dispute)
                        <div class="space-y-2 rounded-lg bg-red-50 p-3 text-sm dark:bg-red-950/30" data-open-dispute>
                            <div dir="auto"><strong>{{ __('bookkeeping.book_show.disputed_by', ['who' => $dispute->raisedBy?->user?->username ?? '']) }}</strong> {{ $dispute->reason }}</div>
                            @can('resolve', $dispute)
                                <div class="flex flex-wrap gap-2">
                                    @if ((int) $dispute->raised_by_actor_id === (int) auth()->user()->actor?->id || $canManage)
                                        <flux:button wire:click="resolveDispute('{{ $dispute->uuid }}', 'upheld')" size="sm" variant="ghost">{{ __('bookkeeping.actions.keep_entry') }}</flux:button>
                                    @endif
                                    @if ((int) $dispute->raised_by_actor_id !== (int) auth()->user()->actor?->id)
                                        <flux:button wire:click="resolveDispute('{{ $dispute->uuid }}', 'voided')" wire:confirm="{{ __('bookkeeping.book_show.void_confirm') }}" size="sm" variant="danger">{{ __('bookkeeping.actions.void_entry') }}</flux:button>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3 text-xs">
                        @can('withdraw', $entry)
                            <button type="button" wire:click="withdraw('{{ $entry->uuid }}')" wire:confirm="{{ __('bookkeeping.book_show.withdraw_confirm') }}" class="text-zinc-500 underline-offset-4 hover:underline">{{ __('bookkeeping.actions.withdraw') }}</button>
                        @endcan
                        @can('dispute', $entry)
                            <button type="button" wire:click="openDispute('{{ $entry->uuid }}')" class="text-zinc-500 underline-offset-4 hover:underline" data-open-dispute-form>{{ __('bookkeeping.actions.dispute') }}</button>
                        @endcan
                        @can('adjust', $entry)
                            <button type="button" wire:click="openAdjust('{{ $entry->uuid }}')" class="text-zinc-500 underline-offset-4 hover:underline">{{ __('bookkeeping.actions.adjust') }}</button>
                        @endcan
                        @can('attachEvidence', $entry)
                            <button type="button" wire:click="openEvidence('{{ $entry->uuid }}')" class="text-zinc-500 underline-offset-4 hover:underline">{{ __('bookkeeping.actions.add_evidence') }}</button>
                        @endcan
                    </div>
                </article>
            @empty
                <x-app.empty-state :title="__('bookkeeping.book_show.empty_title')" :description="__('bookkeeping.book_show.empty_help')" />
            @endforelse
        </div>
    </flux:card>

    {{-- Record dialog --}}
    <x-app.dialog :show="$recordOpen" close="closeRecord" :title="__('bookkeeping.types.'.$entryType)" size="xl">
        <form wire:submit="record" class="space-y-4" data-record-form>
            <flux:text>{{ __('bookkeeping.help_by_type.'.$entryType) }}</flux:text>

            <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                <flux:select wire:model.live="fromParty" :label="__('bookkeeping.from_label.'.$entryType)" data-from-party>
                    <option value="">—</option>
                    @foreach ($activeParties as $party)
                        <option value="{{ $party->uuid }}">{{ $label($party) }}</option>
                    @endforeach
                </flux:select>
                <flux:button type="button" wire:click="swapParties" variant="ghost" icon="arrows-right-left" :aria-label="__('bookkeeping.actions.swap')" />
                <flux:select wire:model.live="toParty" :label="__('bookkeeping.to_label.'.$entryType)" data-to-party>
                    <option value="">—</option>
                    @foreach ($activeParties as $party)
                        <option value="{{ $party->uuid }}">{{ $label($party) }}</option>
                    @endforeach
                </flux:select>
            </div>
            @error('fromParty')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('toParty')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model.live.debounce.400ms="amount" :label="__('bookkeeping.fields.amount_toman')" inputmode="numeric" autocomplete="off" required data-record-amount />
                    @if ($amountPreview)
                        <div class="mt-1 text-xs text-zinc-500">{{ $amountPreview }} {{ __('bookkeeping.toman') }}</div>
                    @endif
                    @error('amount')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <x-app.calendar-date-input model="occurredOn" :label="__('bookkeeping.fields.date')" />
            </div>
            @error('occurredOn')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            @if ($entryType === 'obligation')
                <x-app.calendar-date-input model="dueOn" :label="__('bookkeeping.fields.due_date')" />
            @endif

            <flux:input wire:model="description" :label="$entryType === 'settlement_in_kind' ? __('bookkeeping.fields.what_was_given') : __('bookkeeping.fields.description')" maxlength="500" :required="$entryType === 'settlement_in_kind'" />

            @if (in_array($entryType, ['payment', 'write_off', 'settlement_in_kind'], true) && $openForAllocation->isNotEmpty())
                <div class="space-y-2 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60" data-allocation>
                    <flux:checkbox wire:model.live="chooseAllocations" :label="__('bookkeeping.allocation.choose')" />
                    @if (! $chooseAllocations)
                        <flux:text class="text-sm">{{ __('bookkeeping.allocation.automatic') }}</flux:text>
                    @else
                        @foreach ($openForAllocation as $open)
                            <div wire:key="alloc-{{ $open['obligation']->uuid }}" class="grid grid-cols-[minmax(0,1fr)_10rem] items-center gap-2 text-sm">
                                <div class="min-w-0">
                                    <div class="truncate" dir="auto">{{ $open['obligation']->description ?: __('bookkeeping.types.obligation') }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('bookkeeping.allocation.open', ['amount' => $toman($open['open'])]) }}</div>
                                </div>
                                <input type="text" inputmode="numeric" wire:model="allocations.{{ $open['obligation']->uuid }}" class="rounded-lg border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            </div>
                            @error('allocations.'.$open['obligation']->uuid)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        @endforeach
                        @error('allocations')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>
            @endif

            @if ($myWallets->isNotEmpty())
                <flux:select wire:model="walletUuid" :label="__('bookkeeping.fields.my_wallet')" :description="__('bookkeeping.fields.my_wallet_help')">
                    <option value="">—</option>
                    @foreach ($myWallets as $wallet)
                        <option value="{{ $wallet->uuid }}">{{ $wallet->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            <div>
                <label class="text-sm font-medium">{{ __('bookkeeping.fields.evidence') }}</label>
                <input type="file" wire:model="evidence" accept="image/*,application/pdf" class="mt-1 block w-full text-sm" />
                <div class="mt-1 text-xs text-zinc-500">{{ __('bookkeeping.evidence.help') }}</div>
                @error('evidence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <flux:callout icon="information-circle" class="text-sm">{{ __('bookkeeping.book_show.confirmation_note') }}</flux:callout>

            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="open = false; $wire.closeRecord()" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="record,evidence" data-record-submit>{{ __('bookkeeping.actions.save') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>

    <x-app.dialog :show="$disputeFor !== null" close="closeDispute" :title="__('bookkeeping.actions.dispute')" size="sm">
        <form wire:submit="raiseDispute" class="space-y-4">
            <flux:text>{{ __('bookkeeping.book_show.dispute_help') }}</flux:text>
            <flux:textarea wire:model="disputeReason" :label="__('bookkeeping.fields.reason')" rows="3" maxlength="1000" required />
            @error('disputeReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="open = false; $wire.closeDispute()" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="danger">{{ __('bookkeeping.actions.dispute') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>

    <x-app.dialog :show="$adjustFor !== null" close="closeAdjust" :title="__('bookkeeping.actions.adjust')" size="sm">
        <form wire:submit="adjust" class="space-y-4">
            <flux:text>{{ __('bookkeeping.book_show.adjust_help') }}</flux:text>
            <flux:select wire:model="adjustDirection" :label="__('bookkeeping.fields.direction')">
                <option value="increase">{{ __('bookkeeping.adjust.increase') }}</option>
                <option value="decrease">{{ __('bookkeeping.adjust.decrease') }}</option>
            </flux:select>
            <flux:input wire:model="adjustAmount" :label="__('bookkeeping.fields.amount_toman')" inputmode="numeric" required />
            @error('amount')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('adjustAmount')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <flux:input wire:model="adjustReason" :label="__('bookkeeping.fields.reason')" maxlength="500" required />
            @error('adjustReason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="open = false; $wire.closeAdjust()" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('bookkeeping.actions.save') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>

    <x-app.dialog :show="$evidenceFor !== null" close="closeEvidence" :title="__('bookkeeping.actions.add_evidence')" size="sm">
        <form wire:submit="addEvidence" class="space-y-4">
            <flux:text>{{ __('bookkeeping.evidence.help') }}</flux:text>
            <input type="file" wire:model="extraEvidence" accept="image/*,application/pdf" class="block w-full text-sm" />
            @error('extraEvidence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2">
                <flux:button type="button" x-on:click="open = false; $wire.closeEvidence()" variant="ghost">{{ __('ui.common.close') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addEvidence,extraEvidence">{{ __('bookkeeping.actions.save') }}</flux:button>
            </div>
        </form>
    </x-app.dialog>

    {{-- People --}}
    <x-app.drawer name="people" :title="__('bookkeeping.people.title')" width="sm:max-w-lg">
        <div class="space-y-5" data-people>
            <div class="space-y-2">
                @foreach ($parties as $party)
                    <div wire:key="party-{{ $party->uuid }}" class="flex items-center justify-between gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800 {{ $party->status === \App\Enums\Bookkeeping\PartyStatus::Left ? 'opacity-60' : '' }}">
                        <div class="min-w-0">
                            <div class="truncate font-medium" dir="auto">{{ $label($party) }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ __('bookkeeping.roles.'.$party->role->value) }}
                                @if (! $party->actor->user) · {{ __('bookkeeping.people.no_account') }} @endif
                                @if ($party->contact_hint) · <span dir="ltr">{{ $party->contact_hint }}</span> @endif
                                @if ($party->status === \App\Enums\Bookkeeping\PartyStatus::Left) · {{ __('bookkeeping.people.left') }} @endif
                            </div>
                        </div>
                        @if ($party->status === \App\Enums\Bookkeeping\PartyStatus::Active && $party->role !== \App\Enums\Bookkeeping\PartyRole::Owner && ($canManage || (int) $party->id === (int) ($me?->id ?? 0)))
                            <flux:button wire:click="removeParty('{{ $party->uuid }}')" wire:confirm="{{ __('bookkeeping.people.remove_confirm') }}" size="sm" variant="ghost">
                                {{ (int) $party->id === (int) ($me?->id ?? 0) ? __('bookkeeping.people.leave') : __('bookkeeping.people.remove') }}
                            </flux:button>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($canManage)
                <form wire:submit="addParty" class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60" data-add-person>
                    <div class="font-medium">{{ __('bookkeeping.people.add') }}</div>
                    <flux:input wire:model="partyUsername" :label="__('bookkeeping.people.username')" :description="__('bookkeeping.people.username_help')" />
                    @error('partyUsername')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="text-center text-xs text-zinc-500">{{ __('bookkeeping.people.or') }}</div>
                    <flux:input wire:model="partyName" :label="__('bookkeeping.people.name_only')" :description="__('bookkeeping.people.name_only_help')" maxlength="120" />
                    <flux:input wire:model="partyContact" :label="__('bookkeeping.people.contact_hint')" maxlength="120" />
                    @error('partyName')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <flux:select wire:model="partyRole" :label="__('bookkeeping.people.role')">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ __('bookkeeping.roles.'.$role->value) }} — {{ __('bookkeeping.role_help.'.$role->value) }}</option>
                        @endforeach
                    </flux:select>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" size="sm">{{ __('bookkeeping.people.add') }}</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </x-app.drawer>
</section>
