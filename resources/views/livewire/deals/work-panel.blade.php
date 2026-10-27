@php($tz = \App\Support\TemporalPreferences::timezoneFor($user))
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
                        <div class="text-xs text-zinc-500">{{ __('deals.work.who_for', ['obligor' => $item['obligor_name'], 'beneficiary' => $item['beneficiary_name']]) }} · {{ $item['quantity'] + 0 }} {{ \App\Support\Deals\DealTerms::basisLabel($item['unit']) ?? $item['unit'] }}@if ($item['cycles'] > 1) × {{ $item['cycles'] }}@endif</div>
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
                <div class="text-xs text-zinc-500">{{ __('deals.work.who_for', ['obligor' => $c->obligor->user?->username, 'beneficiary' => $c->beneficiary->user?->username]) }} · {{ __('deals.work.progress', ['done' => $d['accepted'] + 0, 'total' => $c->quantity + 0, 'unit' => $d['unit']]) }}</div>

                {{-- Planned days (an optional schedule): one row per day --}}
                @if ($d['planned'])
                    <ul class="space-y-2">
                        @foreach ($d['rows'] as $row)
                            @php($o = $row['occurrence'])
                            @php($f = $row['fulfillment'])
                            @php($key = $c->id.'-'.$o->uuid)
                            @php($state = \App\Livewire\Deals\WorkPanel::stateOf($f, $d['verifier']))
                            <li wire:key="cycle-{{ $key }}" class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900" data-cycle>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span>@if ($o->scheduled_start_at)<x-app.local-datetime :value="$o->scheduled_start_at" />@endif</span>
                                    <flux:badge size="sm" :color="in_array($state, ['accepted', 'reported_final'], true) ? 'green' : (in_array($state, ['disputed', 'not_accepted'], true) ? 'red' : 'zinc')" data-cycle-state="{{ $state }}">{{ __('deals.work.state.'.$state) }}</flux:badge>
                                </div>
                                @if ($f)
                                    <div class="mt-1 text-xs text-zinc-500" dir="auto">{{ __('deals.work.reported', ['qty' => $f->quantity + 0, 'unit' => $d['unit']]) }}@if ($f->isPartlyAccepted()) · {{ __('deals.work.confirmed_part', ['qty' => $f->confirmedQuantity() + 0]) }}@endif @if ($f->notes) — {{ $f->notes }}@endif @if ($f->review?->note) · {{ $f->review->note }}@endif</div>
                                @endif
                                @if ($f === null && $d['isObligor'])
                                    <div class="mt-2 flex flex-wrap items-end gap-2">
                                        <flux:input size="sm" inputmode="decimal" wire:model="text.qty-{{ $key }}" :label="__('deals.work.quantity')" placeholder="{{ $d['perCycle'] + 0 }}" class="max-w-28" />
                                        <flux:input size="sm" wire:model="text.notes-{{ $key }}" :label="__('deals.work.notes')" maxlength="500" />
                                        <flux:button size="sm" variant="primary" wire:click="report({{ $c->id }}, '{{ $o->uuid }}', $wire.text['qty-{{ $key }}'] || '{{ $d['perCycle'] + 0 }}')" wire:loading.attr="disabled" wire:target="report">{{ __('deals.work.report_day') }}</flux:button>
                                    </div>
                                @endif
                                @if ($f)@include('livewire.deals.partials.report-actions', ['f' => $f, 'verifier' => $d['verifier']])@endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    {{-- The work log: a day-by-day record. History only; nothing here is confirmed or owed --}}
                    @php($lg = $d['log'])
                    <div class="space-y-1 rounded-lg border border-zinc-200 p-2 text-xs dark:border-zinc-700" data-work-log>
                        <div class="font-medium">{{ __('deals.work.log_summary', ['days' => $lg['days'], 'hours' => round($lg['minutes'] / 60, 1)]) }}@if ((float) $lg['estimate'] > 0) · {{ __('deals.work.log_estimate_total', ['qty' => $lg['estimate'] + 0, 'unit' => $d['unit']]) }}@endif</div>
                        @if ($lg['entries']->isNotEmpty())
                            <details>
                                <summary class="cursor-pointer text-zinc-500">{{ __('deals.work.log_entries', ['count' => $lg['entries']->count()]) }}</summary>
                                <ul class="mt-1 space-y-2">
                                    @foreach ($lg['entries'] as $entry)
                                        @php($sAt = \Carbon\CarbonImmutable::parse($entry['started_at']))
                                        @php($eAt = \Carbon\CarbonImmutable::parse($entry['ended_at']))
                                        <li wire:key="log-{{ $entry['id'] }}" class="rounded bg-zinc-50 p-2 dark:bg-zinc-900" data-log-entry>
                                            <div class="font-medium">{{ \App\Support\TemporalCalendar::dateLabel($sAt, $user, $tz) }} · {{ \App\Support\TemporalCalendar::timeLabel($sAt, $user, $tz) }} – {{ \App\Support\TemporalCalendar::timeLabel($eAt, $user, $tz) }}@if ($entry['estimate'] !== null) · {{ __('deals.work.log_about', ['qty' => $entry['estimate'] + 0, 'unit' => $d['unit']]) }}@endif</div>
                                            @if ($entry['note'])<p class="whitespace-pre-wrap" dir="auto">{{ $entry['note'] }}</p>@endif
                                            @if ($entry['files'])<div class="text-zinc-500">{{ implode(', ', $entry['files']) }}</div>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <p class="text-zinc-500">{{ __('deals.work.no_log') }}</p>
                        @endif
                    </div>

                    {{-- Measured work: each report is one entry; the receiver decides each --}}
                    @forelse ($d['reports'] as $f)
                        @php($state = \App\Livewire\Deals\WorkPanel::stateOf($f, $d['verifier']))
                        <div wire:key="report-{{ $f->id }}" class="rounded-lg bg-zinc-50 p-2 dark:bg-zinc-900" data-report>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium">{{ __('deals.work.reported', ['qty' => $f->quantity + 0, 'unit' => $d['unit']]) }}</span>
                                <flux:badge size="sm" :color="in_array($state, ['accepted', 'partly_accepted', 'reported_final'], true) ? 'green' : (in_array($state, ['disputed', 'not_accepted'], true) ? 'red' : 'zinc')" data-cycle-state="{{ $state }}">{{ __('deals.work.state.'.$state) }}</flux:badge>
                            </div>
                            <div class="mt-1 text-xs text-zinc-500" dir="auto">
                                @if ($f->actual_start_at){{ \App\Support\TemporalCalendar::dateLabel($f->actual_start_at, $user, $tz) }}@if ($f->actual_end_at && \App\Support\TemporalCalendar::dateLabel($f->actual_end_at, $user, $tz) !== \App\Support\TemporalCalendar::dateLabel($f->actual_start_at, $user, $tz)) – {{ \App\Support\TemporalCalendar::dateLabel($f->actual_end_at, $user, $tz) }}@endif · @endif
                                @if ($f->isPartlyAccepted()){{ __('deals.work.confirmed_part', ['qty' => $f->confirmedQuantity() + 0]) }} · @endif
                                @if ($f->notes){{ $f->notes }}@endif @if ($f->review?->note) · {{ $f->review->note }}@endif
                                @if ($f->assets->isNotEmpty()) · {{ trans_choice('deals.work.files', $f->assets->count(), ['count' => $f->assets->count()]) }}: {{ $f->assets->pluck('original_filename')->implode(', ') }}@endif
                            </div>
                            @include('livewire.deals.partials.report-actions', ['f' => $f, 'verifier' => $d['verifier']])
                        </div>
                    @empty
                        <p class="text-xs text-zinc-500">{{ __('deals.work.no_reports') }}</p>
                    @endforelse
                @endif
            </div>
        @endforeach

        @if ($duties->isEmpty() && $pending->isEmpty())
            <p class="text-sm text-zinc-500" data-work-empty>{{ $version ? __('deals.work.none') : __('deals.work.need_agreement') }}</p>
        @endif
    </flux:card>

    {{-- Log a work day: when, what happened, a rough progress figure, evidence --}}
    @if ($loggable->isNotEmpty())
        <flux:card class="space-y-3" data-work-log-form>
            <flux:heading size="sm">{{ __('deals.work.log_title') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('deals.work.log_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="log.commitment" :label="__('deals.work.which_duty')" data-log-duty>
                    <option value="">—</option>
                    @foreach ($loggable as $d)<option value="{{ $d['commitment']->id }}">{{ $d['commitment']->title }}</option>@endforeach
                </flux:select>
                <x-app.calendar-date-input model="log.date" :label="__('deals.work.log_date')" />
                <flux:input type="time" wire:model="log.from" :label="__('deals.work.log_from')" data-log-from />
                <flux:input type="time" wire:model="log.to" :label="__('deals.work.log_to')" data-log-to />
            </div>
            <flux:textarea wire:model="log.note" rows="4" :label="__('deals.work.log_note')" :placeholder="__('deals.work.log_note_hint')" />
            <flux:input inputmode="decimal" wire:model="log.estimate" :label="__('deals.work.log_estimate')" :description="__('deals.work.log_estimate_hint')" data-log-estimate />
            <div>
                <flux:input type="file" wire:model="logEvidence" multiple accept="image/*,application/pdf" :label="__('deals.work.evidence')" />
                <div wire:loading wire:target="logEvidence" class="text-xs text-zinc-500">{{ __('deals.work.uploading') }}</div>
                @error('logEvidence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <p class="text-xs text-zinc-500">{{ __('deals.work.evidence_hint') }}</p>
            </div>
            <flux:button variant="primary" wire:click="logDay" wire:loading.attr="disabled" wire:target="logDay,logEvidence" data-log-submit>
                <span wire:loading.remove wire:target="logDay">{{ __('deals.work.log_save') }}</span>
                <span wire:loading wire:target="logDay">{{ __('deals.work.sending') }}</span>
            </flux:button>
        </flux:card>
    @endif

    {{-- Ask for an inspection: finished work at a milestone or at the end. The receiver measures and confirms the exact quantity. --}}
    @if ($reportable->isNotEmpty())
        <flux:card class="space-y-3" data-work-report-form>
            <flux:heading size="sm">{{ __('deals.work.inspection_title') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('deals.work.inspection_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="report.commitment" :label="__('deals.work.which_duty')" data-report-duty>
                    <option value="">—</option>
                    @foreach ($reportable as $d)<option value="{{ $d['commitment']->id }}">{{ $d['commitment']->title }} ({{ __('deals.work.open_quantity', ['qty' => $d['remaining'] + 0, 'unit' => $d['unit']]) }})</option>@endforeach
                </flux:select>
                <flux:input inputmode="decimal" wire:model="report.quantity" :label="__('deals.work.inspection_quantity')" data-report-quantity />
            </div>
            <flux:input wire:model="report.note" :label="__('deals.work.inspection_note')" maxlength="500" />
            <div>
                <flux:input type="file" wire:model="evidence" multiple accept="image/*,application/pdf" :label="__('deals.work.evidence')" />
                <div wire:loading wire:target="evidence" class="text-xs text-zinc-500">{{ __('deals.work.uploading') }}</div>
                @error('evidence')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <flux:button variant="primary" wire:click="submitReport" wire:loading.attr="disabled" wire:target="submitReport,evidence" data-report-submit>
                <span wire:loading.remove wire:target="submitReport">{{ __('deals.work.inspection_send') }}</span>
                <span wire:loading wire:target="submitReport">{{ __('deals.work.sending') }}</span>
            </flux:button>
        </flux:card>
    @endif

    {{-- Set up a duty from the Agreement in force --}}
    @if ($version && count($mine) > 0)
        <flux:card class="space-y-3" data-work-form>
            <flux:heading size="sm">{{ __('deals.work.set_up') }}</flux:heading>
            <flux:input wire:model="form.title" :label="__('deals.work.title')" maxlength="180" />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="form.obligor" :label="__('deals.work.doer')">
                    <option value="">—</option>
                    @foreach ($labels as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </flux:select>
                <flux:input inputmode="decimal" wire:model="form.quantity" :label="__('deals.work.agreed_quantity')" />
                <flux:select wire:model.live="form.unit_choice" :label="__('deals.work.unit')">
                    <option value="">—</option>
                    @foreach ($units as $u)<option value="{{ $u }}">{{ __('deals.bases.'.$u) }}</option>@endforeach
                    <option value="__other">{{ __('deals.bases.other') }}</option>
                </flux:select>
                @if (($form['unit_choice'] ?? '') === '__other')<flux:input wire:model="form.unit_custom" :label="__('deals.work.unit_custom')" maxlength="40" />@endif
            </div>
            <details>
                <summary class="cursor-pointer text-sm text-zinc-500">{{ __('deals.work.schedule') }}</summary>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <flux:input inputmode="numeric" wire:model="form.cycles" :label="__('deals.work.cycles')" />
                    <x-app.calendar-date-input model="form.starts_on" :label="__('deals.work.starts_on')" />
                    <flux:input type="time" wire:model="form.start_time" :label="__('deals.work.start_time')" />
                    <flux:input inputmode="numeric" wire:model="form.duration" :label="__('deals.work.duration')" />
                    <flux:input inputmode="numeric" wire:model="form.every" :label="__('deals.work.every')" />
                    <flux:select wire:model="form.period" :label="__('deals.work.period')">
                        @foreach (['day', 'week', 'month'] as $p)<option value="{{ $p }}">{{ __('deals.work.periods.'.$p) }}</option>@endforeach
                    </flux:select>
                </div>
            </details>
            <flux:button variant="primary" wire:click="propose" wire:loading.attr="disabled" wire:target="propose">{{ __('deals.work.propose') }}</flux:button>
            <p class="text-xs text-zinc-500">{{ __('deals.work.propose_note') }}</p>
        </flux:card>
    @endif
</div>
