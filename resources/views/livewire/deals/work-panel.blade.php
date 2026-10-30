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
                <div class="text-xs text-zinc-500">{{ __('deals.work.who_for', ['obligor' => $c->obligor->user?->username, 'beneficiary' => $c->beneficiary->user?->username]) }} · @if ($d['share']){{ __('deals.work.progress_share', ['done' => round((float) $d['accepted'] * 100)]) }}@elseif ($d['open_ended'] && (float) $d['estimate'] > 0){{ __('deals.work.progress_estimate', ['done' => $d['accepted_total'] + 0, 'total' => $d['estimate'] + 0, 'unit' => $d['unit'], 'pct' => round((float) $d['accepted_total'] / (float) $d['estimate'] * 100)]) }}@elseif ($d['open_ended']){{ __('deals.work.progress_open', ['done' => $d['accepted_total'] + 0, 'unit' => $d['unit']]) }}@else{{ __('deals.work.progress', ['done' => $d['accepted'] + 0, 'total' => $c->quantity + 0, 'unit' => $d['unit']]) }}@endif</div>

                @if ($c->description)<p class="whitespace-pre-wrap text-sm" dir="auto" data-task-description>{{ $c->description }}</p>@endif
                @if ($d['media'])<div class="grid gap-2 sm:grid-cols-2" data-task-media>@foreach ($d['media'] as $asset)<x-media.asset-preview :asset="$asset" :inline-url="route('deals.assets.show', $asset)" compact />@endforeach</div>@endif
                @if (! $d['planned'] && $d['plans']->isNotEmpty())
                    <div class="flex flex-wrap items-end gap-2" data-task-plan>
                        <flux:select size="sm" wire:model="text.plan-{{ $c->id }}" :label="__('deals.work.use_plan')">
                            <option value="">—</option>
                            @foreach ($d['plans'] as $plan)<option value="{{ $plan->uuid }}">{{ $plan->title }}</option>@endforeach
                        </flux:select>
                        <flux:button size="sm" wire:click="linkPlan({{ $c->id }}, $wire.text['plan-{{ $c->id }}'] || '')">{{ __('deals.work.link_plan') }}</flux:button>
                    </div>
                @endif
                {{-- Schedule from here: the plan is created already linked to this work --}}
                @if (! $d['planned'] && Gate::forUser($user)->allows('manageAsParty', $c))
                    <details class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700" data-task-schedule>
                        <summary class="cursor-pointer text-sm font-medium">{{ __('deals.work.schedule_title') }}</summary>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <x-app.calendar-date-input model="sched.{{ $c->id }}.date" :label="__('deals.work.schedule_date')" />
                            <flux:input type="time" wire:model="sched.{{ $c->id }}.time" :label="__('deals.work.schedule_time')" />
                            <flux:input inputmode="decimal" wire:model="sched.{{ $c->id }}.hours" placeholder="8" :label="__('deals.work.schedule_hours')" />
                            <flux:select wire:model="sched.{{ $c->id }}.repeat" :label="__('deals.work.schedule_repeat')">
                                @foreach (['once', 'daily', 'weekly', 'monthly'] as $r)<option value="{{ $r }}">{{ __('deals.work.repeat_'.$r) }}</option>@endforeach
                            </flux:select>
                            <flux:input inputmode="numeric" wire:model="sched.{{ $c->id }}.times" placeholder="1" :label="__('deals.work.schedule_times')" />
                        </div>
                        @foreach (['date', 'time', 'hours', 'times'] as $f)@error('sched.'.$c->id.'.'.$f)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endforeach
                        <flux:button class="mt-2" size="sm" variant="primary" wire:click="schedule({{ $c->id }})" wire:loading.attr="disabled" wire:target="schedule">{{ __('deals.work.schedule_save') }}</flux:button>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('deals.work.schedule_hint') }}</p>
                    </details>
                @endif
                @if ($d['planned'] && $c->planBinding?->plan && Gate::forUser($user)->allows('view', $c->planBinding->plan))
                    <a class="text-xs underline" href="{{ route('planner.show', $c->planBinding->plan) }}" data-task-plan-link>{{ __('deals.work.open_plan') }}</a>
                @endif

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
                                            @if ($entry['files'])<div class="grid gap-2 sm:grid-cols-2">@foreach ($entry['files'] as $asset)<x-media.asset-preview :asset="$asset" :inline-url="route('deals.assets.show', $asset)" compact />@endforeach</div>@endif
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
                            </div>
                            @if ($f->assets->isNotEmpty())<div class="mt-2 grid gap-2 sm:grid-cols-2" data-report-media>@foreach ($f->assets as $asset)<x-media.asset-preview :asset="$asset" :inline-url="route('deals.assets.show', $asset)" compact />@endforeach</div>@endif
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

    {{-- One daily report: the day, the measured quantity, optional times, a note and media. The receiver confirms the quantity; only that is earned. --}}
    @if ($reportable->isNotEmpty() || $rateOptions->isNotEmpty() && count($mine) > 0)
        <flux:card class="space-y-3" data-work-report-form>
            <flux:heading size="sm">{{ __('deals.work.inspection_title') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('deals.work.inspection_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @php($sel = $reportable->first(fn ($x) => (string) $x['commitment']->id === (string) ($report['commitment'] ?? '')))
                <flux:select wire:model.live="report.commitment" :label="__('deals.work.which_duty')" data-report-duty>
                    <option value="">—</option>
                    @foreach ($rateOptions as $rate)<option value="rate:{{ $rate['key'] }}">{{ $rate['label'] }} ({{ \App\Support\Bookkeeping\Toman::format((int) $rate['rate']) }} / {{ \App\Support\Deals\DealTerms::basisLabel($rate['unit']) ?? $rate['unit'] }})</option>@endforeach
                    @foreach ($reportable as $d)<option value="{{ $d['commitment']->id }}">{{ $d['commitment']->title }}@if ($d['share']) ({{ __('deals.work.open_share', ['pct' => round((float) $d['remaining'] * 100)]) }})@elseif (! $d['open_ended']) ({{ __('deals.work.open_quantity', ['qty' => $d['remaining'] + 0, 'unit' => $d['unit']]) }})@endif</option>@endforeach
                </flux:select>
                <flux:input inputmode="decimal" wire:model="report.quantity" :label="$sel && $sel['share'] ? __('deals.work.inspection_share') : ($sel ? __('deals.work.inspection_quantity_unit', ['unit' => $sel['unit']]) : __('deals.work.inspection_quantity'))" :description="$sel && $sel['share'] ? __('deals.work.inspection_share_hint') : null" data-report-quantity />
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-app.calendar-date-input model="report.date" :label="__('deals.work.log_date')" />
                <flux:input type="time" wire:model="report.from" :label="__('deals.work.log_from')" data-report-from />
                <flux:input type="time" wire:model="report.to" :label="__('deals.work.log_to')" data-report-to />
            </div>
            @error('report.date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('report.from')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
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

    {{-- A new task: written by the person who receives the work. Title, description, optional media. Nothing else. --}}
    @if ($version && count($mine) > 0)
        <flux:card class="space-y-3" data-work-form>
            <flux:heading size="sm">{{ __('deals.work.task_title') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('deals.work.task_hint') }}</p>
            <flux:input wire:model="form.title" :label="__('deals.work.task_name')" :description="__('deals.work.task_name_hint')" maxlength="180" />
            <flux:textarea wire:model="form.description" rows="4" :label="__('deals.work.task_description')" :placeholder="__('deals.work.task_description_hint')" />
            <div>
                <flux:input type="file" wire:model="taskMedia" multiple accept="image/*,video/*,audio/*" :label="__('deals.work.task_media')" />
                <div wire:loading wire:target="taskMedia" class="text-xs text-zinc-500">{{ __('deals.work.uploading') }}</div>
                @error('taskMedia')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <p class="text-xs text-zinc-500">{{ __('deals.work.task_media_hint') }}</p>
            </div>
            <flux:button variant="primary" wire:click="addTask" wire:loading.attr="disabled" wire:target="addTask,taskMedia" data-task-add>{{ __('deals.work.task_add') }}</flux:button>
        </flux:card>
    @endif
</div>
