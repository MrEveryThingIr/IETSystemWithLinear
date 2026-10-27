<div class="space-y-3" data-work-panel>
    <flux:card class="space-y-3">
        <flux:heading>{{ __('deals.tabs.work') }}</flux:heading>
        <p class="text-sm text-zinc-500">{{ __('deals.work.intro') }}</p>
        @error('work')<p class="text-sm text-red-600" role="alert" data-work-error>{{ $message }}</p>@enderror

        {{-- Waiting for the receiving party. Nothing here is an obligation yet. --}}
        @if ($pending->isNotEmpty())
            <div class="space-y-2" data-work-pending>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.work.pending') }}</div>
                @foreach ($pending as $item)
                    <div wire:key="pending-{{ $item['id'] }}" class="rounded-xl border border-dashed border-zinc-300 p-3 text-sm dark:border-zinc-600">
                        <div class="font-medium" dir="auto">{{ $item['title'] }}</div>
                        <div class="text-xs text-zinc-500" dir="auto">{{ __('deals.work.who_for', ['obligor' => $item['obligor_name'], 'beneficiary' => $item['beneficiary_name']]) }} · {{ $item['quantity'] + 0 }} {{ $item['unit'] }}@if ($item['cycles'] > 1) × {{ $item['cycles'] }}@endif</div>
                        @if ($item['stale'])<p class="mt-1 text-xs text-amber-600">{{ __('deals.work.stale') }}</p>@endif
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($item['can_confirm'])<flux:button size="sm" variant="primary" wire:click="confirm('{{ $item['id'] }}')" wire:loading.attr="disabled" wire:target="confirm">{{ __('deals.work.confirm') }}</flux:button>@endif
                            @if ($item['can_withdraw'])<flux:button size="sm" variant="ghost" wire:click="withdraw('{{ $item['id'] }}')">{{ $item['can_confirm'] ? __('deals.work.decline') : __('deals.work.withdraw') }}</flux:button>@endif
                            @if (! $item['can_confirm'] && ! $item['stale'])<span class="text-xs text-zinc-500">{{ __('deals.work.waiting_receiver', ['name' => $item['beneficiary_name']]) }}</span>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Duties in force --}}
        @foreach ($duties as $d)
            @php($c = $d['commitment'])
            <div wire:key="duty-{{ $c->id }}" class="space-y-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-duty>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <span class="font-medium" dir="auto">{{ $c->title }}</span>
                    <flux:badge size="sm" color="zinc">{{ __('deals.work.confirmed_by.'.$d['verifier']) }}</flux:badge>
                </div>
                <div class="text-xs text-zinc-500" dir="auto">{{ __('deals.work.who_for', ['obligor' => $c->obligor->user?->username, 'beneficiary' => $c->beneficiary->user?->username]) }} · {{ __('deals.work.progress', ['done' => $d['accepted'] + 0, 'total' => $c->quantity + 0, 'unit' => $c->unit]) }}</div>
                <ul class="space-y-2">
                    @foreach ($d['rows'] as $row)
                        @php($o = $row['occurrence'])
                        @php($f = $row['fulfillment'])
                        @php($key = $c->id.'-'.($o?->uuid ?? 'once'))
                        @php($occArg = $o ? "'".$o->uuid."'" : 'null')
                        <li wire:key="cycle-{{ $key }}" class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900" data-cycle>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span>@if ($o && $o->scheduled_start_at)<x-app.local-datetime :value="$o->scheduled_start_at" />@elseif ($o){{ __('deals.work.whole_duty') }}@else{{ __('deals.work.whole_duty') }}@endif</span>
                                @php($state = match (true) {
                                    $f === null => 'open',
                                    $f->dispute && $f->dispute->status === \App\FulfillmentDisputeStatus::Open => 'disputed',
                                    $f->status === \App\FulfillmentStatus::Accepted && \App\Support\QuantityAmount::compare($f->quantity, $d['perCycle']) < 0 && $o => 'partly',
                                    $f->status === \App\FulfillmentStatus::Accepted => 'confirmed',
                                    $f->status === \App\FulfillmentStatus::Submitted && $d['verifier'] === 'none' => 'reported_final',
                                    default => $f->status->value,
                                })
                                <flux:badge size="sm" :color="in_array($state, ['confirmed', 'reported_final'], true) ? 'green' : (in_array($state, ['disputed', 'rejected'], true) ? 'red' : 'zinc')" data-cycle-state="{{ $state }}">{{ __('deals.work.state.'.$state) }}</flux:badge>
                            </div>

                            @if ($f)
                                <div class="mt-1 text-xs text-zinc-500" dir="auto">{{ __('deals.work.reported', ['qty' => $f->quantity + 0, 'unit' => $f->unit]) }}@if ($f->notes) — {{ $f->notes }}@endif @if ($f->review?->note) · {{ $f->review->note }}@endif</div>
                            @endif

                            {{-- The performer reports. Marking it done in the Planner alone never counts. --}}
                            @if (($f === null) && $d['isObligor'])
                                <div class="mt-2 flex flex-wrap items-end gap-2">
                                    <flux:input size="sm" inputmode="decimal" wire:model="text.qty-{{ $key }}" :label="__('deals.work.quantity')" placeholder="{{ $d['perCycle'] + 0 }}" class="max-w-28" />
                                    <flux:input size="sm" wire:model="text.notes-{{ $key }}" :label="__('deals.work.notes')" maxlength="500" />
                                    <flux:button size="sm" variant="primary" wire:click="report({{ $c->id }}, {!! $occArg !!}, $wire.text['qty-{{ $key }}'] || '{{ $d['perCycle'] + 0 }}')" wire:loading.attr="disabled" wire:target="report">{{ __('deals.work.report') }}</flux:button>
                                </div>
                            @endif

                            {{-- The receiver (verifier) answers --}}
                            @if ($f && $f->status === \App\FulfillmentStatus::Submitted && Gate::forUser($user)->allows('review', $f))
                                <div class="mt-2 space-y-2">
                                    <flux:input size="sm" wire:model="text.review-{{ $f->id }}" :label="__('deals.work.review_note')" maxlength="500" />
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="review({{ $f->id }}, 'accepted')">{{ __('deals.work.accept') }}</flux:button>
                                        <flux:button size="sm" wire:click="review({{ $f->id }}, 'clarification_requested')">{{ __('deals.work.ask') }}</flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="review({{ $f->id }}, 'rejected')">{{ __('deals.work.reject') }}</flux:button>
                                    </div>
                                </div>
                            @endif

                            {{-- The performer corrects after a question or a rejection --}}
                            @if ($f && Gate::forUser($user)->allows('correct', $f))
                                <div class="mt-2 flex flex-wrap items-end gap-2">
                                    <flux:input size="sm" inputmode="decimal" wire:model="text.qty-fix-{{ $f->id }}" :label="__('deals.work.quantity')" placeholder="{{ $f->quantity + 0 }}" class="max-w-28" />
                                    <flux:input size="sm" wire:model="text.notes-fix-{{ $f->id }}" :label="__('deals.work.notes')" maxlength="500" />
                                    <flux:button size="sm" wire:click="correct({{ $f->id }}, $wire.text['qty-fix-{{ $f->id }}'] || '{{ $f->quantity + 0 }}')">{{ __('deals.work.send_correction') }}</flux:button>
                                </div>
                            @endif

                            {{-- Either side may dispute a decided report; the other side resolves --}}
                            @if ($f && Gate::forUser($user)->allows('openDispute', $f))
                                <details class="mt-2"><summary class="cursor-pointer text-xs text-zinc-500">{{ __('deals.work.dispute') }}</summary>
                                    <div class="mt-1 flex flex-wrap items-end gap-2">
                                        <flux:input size="sm" wire:model="text.dispute-{{ $f->id }}" :label="__('deals.work.dispute_reason')" maxlength="500" />
                                        <flux:button size="sm" variant="danger" wire:click="dispute({{ $f->id }})">{{ __('deals.work.dispute_open') }}</flux:button>
                                    </div>
                                </details>
                            @endif
                            @if ($f?->dispute && Gate::forUser($user)->allows('resolve', $f->dispute))
                                <div class="mt-2 space-y-2">
                                    <p class="text-xs" dir="auto">{{ $f->dispute->reason }}</p>
                                    <flux:input size="sm" wire:model="text.resolve-{{ $f->dispute->id }}" :label="__('deals.work.review_note')" maxlength="500" />
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button size="sm" wire:click="resolve({{ $f->dispute->id }}, 'accepted')">{{ __('deals.work.resolve_accept') }}</flux:button>
                                        <flux:button size="sm" wire:click="resolve({{ $f->dispute->id }}, 'rejected')">{{ __('deals.work.resolve_reject') }}</flux:button>
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        @if ($duties->isEmpty() && $pending->isEmpty())
            <p class="text-sm text-zinc-500" data-work-empty>{{ $version ? __('deals.work.none') : __('deals.work.need_agreement') }}</p>
        @endif
    </flux:card>

    {{-- Set up a duty from the Agreement in force --}}
    @if ($version && count($mine) > 0)
        <flux:card class="space-y-3" data-work-form>
            <flux:heading size="sm">{{ __('deals.work.set_up') }}</flux:heading>
            <flux:input wire:model="form.title" :label="__('deals.work.title')" maxlength="180" />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="form.obligor" :label="__('deals.work.doer')">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($labels as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:select wire:model="form.beneficiary" :label="__('deals.work.receiver')">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($labels as $id => $name)<flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input inputmode="decimal" wire:model="form.quantity" :label="__('deals.work.quantity_each')" />
                <flux:input wire:model="form.unit" :label="__('deals.work.unit')" maxlength="40" list="deal-price-units" />
                <datalist id="deal-price-units">@foreach (\App\Support\Deals\DealMoney::UNITS as $u)<option value="{{ $u }}">@endforeach</datalist>
                <flux:select wire:model="form.kind" :label="__('deals.work.kind')">
                    @foreach ($kinds as $kind)<flux:select.option value="{{ $kind->value }}">{{ __('commitments.kind.'.$kind->value) }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input type="number" min="1" max="366" wire:model="form.cycles" :label="__('deals.work.cycles')" />
            </div>
            <details>
                <summary class="cursor-pointer text-sm text-zinc-500">{{ __('deals.work.schedule') }}</summary>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <x-app.calendar-date-input model="form.starts_on" :label="__('deals.work.starts_on')" />
                    <flux:input type="time" wire:model="form.start_time" :label="__('deals.work.start_time')" />
                    <flux:input type="number" min="1" wire:model="form.duration" :label="__('deals.work.duration')" />
                    <flux:input type="number" min="1" wire:model="form.every" :label="__('deals.work.every')" />
                    <flux:select wire:model="form.period" :label="__('deals.work.period')">
                        @foreach (['day', 'week', 'month'] as $p)<flux:select.option value="{{ $p }}">{{ __('deals.work.periods.'.$p) }}</flux:select.option>@endforeach
                    </flux:select>
                </div>
            </details>
            <flux:button variant="primary" wire:click="propose" wire:loading.attr="disabled" wire:target="propose">{{ __('deals.work.propose') }}</flux:button>
            <p class="text-xs text-zinc-500">{{ __('deals.work.propose_note') }}</p>
        </flux:card>
    @endif
</div>
