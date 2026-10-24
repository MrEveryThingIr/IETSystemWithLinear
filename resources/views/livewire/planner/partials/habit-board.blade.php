{{--
    The habit side of a plan, only for the person who made it: the habit loop,
    tuning periods ("change one thing at a time"), the expected-versus-actual
    board for each day, and urges for a habit being replaced.
--}}
@php
    $chip = 'inline-flex min-h-10 items-center justify-center rounded-full border px-3 text-sm transition';
    $on = 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900';
    $off = 'border-zinc-300 hover:border-zinc-500 dark:border-zinc-600';
    $tz = $plan->timezone;
    $minutes = fn (?int $value): string => \App\Support\Planner\HabitText::minutes($value);
    $summary = $progress['summary'] ?? [];
    $ideal = $plan->scheduleRules->first()?->start_time ? substr($plan->scheduleRules->first()->start_time, 0, 5) : null;
@endphp

<section class="space-y-4" data-habit-board>
    {{-- The habit loop --}}
    <flux:card class="space-y-4" data-habit-loop>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <flux:heading size="lg">{{ $habit ? __('habits.loop.title_'.$habit->mode) : __('habits.loop.invite_title') }}</flux:heading>
                @unless ($habit)
                    <flux:text class="mt-1 text-sm">{{ __('habits.loop.invite_help') }}</flux:text>
                @endunless
            </div>
            @if ($habit && ! $habitEditing)
                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editHabit">{{ __('habits.edit') }}</flux:button>
            @endif
        </div>

        @if ($habitEditing)
            <form wire:submit="saveHabit" class="space-y-3" data-habit-form>
                <div class="flex flex-wrap gap-2">
                    @foreach (['build', 'replace'] as $mode)
                        <button type="button" wire:click="$set('habitMode', '{{ $mode }}')" class="{{ $chip }} {{ $habitMode === $mode ? $on : $off }}">{{ __('habits.loop.mode_'.$mode) }}</button>
                    @endforeach
                </div>
                @if ($habitMode === 'replace')
                    <flux:input wire:model="habitReplaces" :label="__('habits.loop.replaces')" :placeholder="__('habits.loop.replaces_example')" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" />
                @endif
                <flux:input wire:model="habitCue" :label="__('habits.loop.cue_'.$habitMode)" :placeholder="__('habits.loop.cue_example_'.$habitMode)" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" />
                <flux:input wire:model="habitReward" :label="__('habits.loop.reward')" :placeholder="__('habits.loop.reward_example_'.$habitMode)" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" />
                <flux:input wire:model="habitWhy" :label="__('habits.loop.why')" :placeholder="__('habits.loop.why_example')" maxlength="500" class="[&_input]:text-base sm:[&_input]:text-sm" />
                <flux:text class="text-xs">{{ __('habits.loop.science') }}</flux:text>
                @foreach (['habitReplaces', 'habitCue', 'habitReward', 'habitWhy'] as $field)
                    @error($field)<div class="text-sm text-red-600" role="alert">{{ $message }}</div>@enderror
                @endforeach
                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" data-habit-save>{{ __('habits.save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="$set('habitEditing', false)">{{ __('habits.cancel') }}</flux:button>
                </div>
            </form>
        @elseif ($habit)
            <div class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl bg-sky-50 p-3 dark:bg-sky-950/30">
                    <div class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">{{ __('habits.loop.cue_short') }}</div>
                    <div class="mt-1 text-sm [overflow-wrap:anywhere]" dir="auto">{{ $habit->cue ?: '—' }}</div>
                </div>
                <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30">
                    <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('habits.loop.routine_short') }}</div>
                    <div class="mt-1 text-sm font-medium [overflow-wrap:anywhere]" dir="auto">{{ $replaces ? ($currentPeriod?->focus ?? $plan->title) : $plan->title }}</div>
                    @if ($replaces)<div class="mt-1 text-xs text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ __('habits.loop.instead_of', ['habit' => $habit->replaces]) }}</div>@endif
                </div>
                <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30">
                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('habits.loop.reward_short') }}</div>
                    <div class="mt-1 text-sm [overflow-wrap:anywhere]" dir="auto">{{ $habit->reward ?: '—' }}</div>
                </div>
            </div>
            @if ($habit->why)
                <p class="text-sm text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-300" dir="auto"><span class="font-medium">{{ __('habits.loop.why_short') }}:</span> {{ $habit->why }}</p>
            @endif
        @else
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" icon="sparkles" wire:click="editHabit('build')" data-habit-start>{{ __('habits.loop.make_habit') }}</flux:button>
                @if (! $repeats)
                    <flux:button size="sm" variant="ghost" wire:click="editHabit('replace')">{{ __('habits.loop.mode_replace') }}</flux:button>
                @endif
            </div>
        @endif
    </flux:card>

    {{-- The current period and its board --}}
    @if ($habit || $repeats)
        <flux:card class="space-y-4" data-habit-period>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    @if ($currentPeriod)
                        <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-300">
                            {{ __('habits.periods.number', ['number' => \App\Support\Planner\HabitText::number($currentPeriod->number)]) }}
                            @if ($periodDay) · {{ __('habits.periods.day_of', ['day' => \App\Support\Planner\HabitText::number($periodDay), 'days' => \App\Support\Planner\HabitText::number($currentPeriod->days())]) }}@endif
                        </div>
                        <flux:heading size="lg" class="mt-1 [overflow-wrap:anywhere]" dir="auto">{{ $currentPeriod->focus }}</flux:heading>
                        @if ($currentPeriod->target_time && $ideal)
                            <flux:text class="mt-1 text-sm">{{ __('habits.periods.target_line', ['target' => $currentPeriod->target_time, 'ideal' => $ideal]) }}</flux:text>
                        @endif
                    @else
                        <flux:heading size="lg">{{ __('habits.board.title') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ $habit ? __('habits.periods.none_help') : __('habits.board.last_days') }}</flux:text>
                    @endif
                </div>
                @if ($habit && ! $periodForm)
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" :variant="$currentPeriod ? 'ghost' : 'primary'" icon="adjustments-horizontal" wire:click="openPeriodForm" data-period-change>{{ $currentPeriod ? __('habits.periods.change') : __('habits.periods.start') }}</flux:button>
                    </div>
                @endif
            </div>

            @if ($periodEnded && ! $periodForm)
                <flux:callout icon="flag" color="indigo">
                    {{ __('habits.periods.ended') }}
                    <x-slot:actions><flux:button size="sm" wire:click="openPeriodForm">{{ __('habits.periods.review') }}</flux:button></x-slot:actions>
                </flux:callout>
            @endif

            @if ($periodForm)
                <form wire:submit="savePeriod" class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900 dark:bg-indigo-950/20" data-period-form>
                    @if ($currentPeriod && $currentPeriod->starts_on->toDateString() < $today)
                        <div class="space-y-3">
                            <div class="font-medium">{{ __('habits.periods.how_was', ['focus' => $currentPeriod->focus]) }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Models\Planner\PlanPeriod::VERDICTS as $verdict)
                                    <button type="button" wire:click="$set('periodVerdict', '{{ $verdict }}')" data-verdict="{{ $verdict }}" class="{{ $chip }} {{ $periodVerdict === $verdict ? $on : $off }}">{{ __('habits.periods.verdicts.'.$verdict) }}</button>
                                @endforeach
                            </div>
                            <details class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900" @if (array_filter($periodAutomaticity)) open @endif>
                                <summary class="cursor-pointer text-sm font-medium">{{ __('habits.automaticity.title') }} <span class="font-normal text-zinc-500">({{ __('habits.optional') }})</span></summary>
                                <p class="mt-2 text-xs text-zinc-500">{{ __('habits.automaticity.help', ['behaviour' => $replaces ? $currentPeriod->focus : $plan->title]) }}</p>
                                <div class="mt-3 space-y-3">
                                    @foreach ([0, 1, 2, 3] as $item)
                                        <div>
                                            <div class="text-sm" dir="auto">{{ __('habits.automaticity.items.'.$item) }}</div>
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                @foreach ([1, 2, 3, 4, 5] as $score)
                                                    <button type="button" wire:click="$set('periodAutomaticity.{{ $item }}', {{ $score }})" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-full border px-2 text-xs {{ ($periodAutomaticity[$item] ?? null) === $score ? $on : $off }}" title="{{ __('habits.automaticity.scale.'.$score) }}" aria-label="{{ __('habits.automaticity.scale.'.$score) }}">{{ $score }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="text-xs text-zinc-500">1 = {{ __('habits.automaticity.scale.1') }} · 5 = {{ __('habits.automaticity.scale.5') }}</div>
                                </div>
                            </details>
                            <flux:textarea wire:model="periodReflection" rows="2" :label="__('habits.periods.reflection')" />
                        </div>
                        <hr class="border-indigo-200 dark:border-indigo-900">
                    @endif

                    <div class="space-y-3">
                        <div class="font-medium">{{ $currentPeriod ? __('habits.periods.next_title') : __('habits.periods.first_title') }}</div>
                        <flux:text class="text-sm">{{ $replaces ? __('habits.periods.next_help_replace') : __('habits.periods.next_help_build') }}</flux:text>
                        <flux:input wire:model="nextFocus" :label="$replaces ? __('habits.periods.focus_replace') : __('habits.periods.focus')" :placeholder="$replaces ? __('habits.periods.focus_example_replace') : __('habits.periods.focus_example_build')" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" data-period-focus />
                        @if (! $replaces && $repeats)
                            <div class="max-w-xs">
                                <flux:input type="time" wire:model="nextTarget" :label="__('habits.periods.target')" />
                                <flux:text class="mt-1 text-xs">{{ __('habits.periods.target_help', ['ideal' => $ideal ?? '—']) }}</flux:text>
                            </div>
                        @endif
                        <div>
                            <div class="mb-1.5 text-sm font-medium">{{ __('habits.periods.length') }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Models\Planner\PlanPeriod::LENGTHS as $length)
                                    <button type="button" wire:click="$set('nextDays', {{ $length }})" class="{{ $chip }} {{ (int) $nextDays === $length ? $on : $off }}">{{ trans_choice('habits.periods.days', $length, ['count' => $length]) }}</button>
                                @endforeach
                            </div>
                            <flux:text class="mt-1.5 text-xs">{{ __('habits.periods.length_help') }}</flux:text>
                        </div>
                    </div>

                    @foreach (['nextFocus', 'nextTarget', 'nextDays', 'periodVerdict', 'periodReflection'] as $field)
                        @error($field)<div class="text-sm text-red-600" role="alert">{{ $message }}</div>@enderror
                    @endforeach
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary" data-period-save>{{ $currentPeriod ? __('habits.periods.start_next') : __('habits.periods.start') }}</flux:button>
                        @if ($currentPeriod && $currentPeriod->starts_on->toDateString() < $today)
                            <flux:button type="button" variant="ghost" wire:click="finishPeriod" data-period-finish>{{ __('habits.periods.finish_only') }}</flux:button>
                        @endif
                        <flux:button type="button" variant="ghost" wire:click="closePeriodForm">{{ __('habits.cancel') }}</flux:button>
                    </div>
                </form>
            @endif

            @if ($periods->count() > 1)
                <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('habits.periods.list') }}">
                    @foreach ($periods as $period)
                        <button type="button" role="tab" aria-selected="{{ $boardPeriod?->is($period) ? 'true' : 'false' }}" wire:click="showBoard('{{ $period->uuid }}')" class="rounded-full border px-3 py-1 text-xs {{ $boardPeriod?->is($period) ? $on : $off }}" data-board-period="{{ $period->number }}">{{ __('habits.periods.number', ['number' => \App\Support\Planner\HabitText::number($period->number)]) }}</button>
                    @endforeach
                </div>
                @if ($boardPeriod && ! $boardPeriod->is($currentPeriod))
                    <flux:text class="text-sm [overflow-wrap:anywhere]" dir="auto">{{ $boardPeriod->focus }} · <x-app.local-date :value="$boardPeriod->starts_on" /> – <x-app.local-date :value="$boardPeriod->lastDay()" /></flux:text>
                @endif
            @endif

            {{-- Replacing a habit: the urge buttons and what happened --}}
            @if ($replaces)
                <div class="space-y-3 rounded-2xl border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900 dark:bg-teal-950/20" data-urge-panel>
                    <div class="font-semibold">{{ __('habits.urges.question', ['habit' => $habit->replaces]) }}</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button type="button" wire:click="logUrge('replaced')" class="min-h-14 rounded-xl bg-teal-600 px-4 py-3 text-start font-semibold text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600" data-urge="replaced">
                            {{ __('habits.urges.replaced', ['routine' => $currentPeriod?->focus ?? __('habits.urges.the_new_routine')]) }}
                        </button>
                        <button type="button" wire:click="logUrge('gave_in')" class="min-h-14 rounded-xl border border-zinc-300 bg-white px-4 py-3 text-start font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:hover:bg-zinc-800" data-urge="gave_in">
                            {{ __('habits.urges.gave_in') }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-sm">
                        <span class="text-zinc-600 dark:text-zinc-300">{{ __('habits.urges.strength') }}</span>
                        @foreach ([1, 2, 3, 4, 5] as $level)
                            <button type="button" wire:click="$set('urgeStrength', {{ $urgeStrength === $level ? 'null' : $level }})" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-full border px-2 text-xs {{ $urgeStrength === $level ? $on : $off }}" aria-label="{{ __('habits.urges.strength_level', ['level' => $level]) }}">{{ $level }}</button>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-600 dark:text-zinc-300">
                        <span data-urges-today>{{ __('habits.urges.today', ['replaced' => \App\Support\Planner\HabitText::number($urgesToday['replaced']), 'gave_in' => \App\Support\Planner\HabitText::number($urgesToday['gave_in'])]) }}</span>
                        @if ($lastUrge)
                            <button type="button" wire:click="undoUrge('{{ $lastUrge->uuid }}')" class="text-xs underline underline-offset-2">{{ __('habits.urges.undo') }}</button>
                        @endif
                    </div>
                    <flux:text class="text-xs">{{ __('habits.urges.help') }}</flux:text>
                </div>

                @if ($urgesBoard['total'] > 0)
                    @php
                        $dayList = collect(\Carbon\CarbonPeriod::create($boardFrom, min($boardTo, $today)))->map(fn ($day) => $day->toDateString());
                        $maxDay = max(1, collect($urgesBoard['days'])->map(fn ($d) => $d['replaced'] + $d['gave_in'])->max() ?? 1);
                    @endphp
                    <div class="space-y-2" data-urge-strip>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <span>{{ __('habits.urges.summary', ['total' => \App\Support\Planner\HabitText::number($urgesBoard['total']), 'per_day' => \App\Support\Planner\HabitText::number($urgesBoard['per_day'])]) }}</span>
                            <span class="font-semibold text-teal-700 dark:text-teal-300">{{ __('habits.urges.rate', ['rate' => \App\Support\Planner\HabitText::number($urgesBoard['rate'])]) }}</span>
                            @if ($urgesBoard['strength'])<span class="text-zinc-500">{{ __('habits.urges.avg_strength', ['strength' => \App\Support\Planner\HabitText::number($urgesBoard['strength'])]) }}</span>@endif
                        </div>
                        <div class="flex h-16 items-end gap-0.5 overflow-x-auto" dir="ltr" aria-hidden="true">
                            @foreach ($dayList as $date)
                                @php $d = $urgesBoard['days'][$date] ?? ['replaced' => 0, 'gave_in' => 0]; @endphp
                                <div class="flex w-3 shrink-0 flex-col justify-end" title="{{ $date }}: {{ $d['replaced'] }} / {{ $d['gave_in'] }}">
                                    <div class="rounded-t-sm bg-amber-400" style="height: {{ round($d['gave_in'] * 56 / $maxDay) }}px"></div>
                                    <div class="bg-teal-500" style="height: {{ round($d['replaced'] * 56 / $maxDay) }}px"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex gap-4 text-xs text-zinc-500"><span><span class="me-1 inline-block size-2 rounded-sm bg-teal-500"></span>{{ __('habits.urges.legend_replaced') }}</span><span><span class="me-1 inline-block size-2 rounded-sm bg-amber-400"></span>{{ __('habits.urges.legend_gave_in') }}</span></div>
                    </div>
                @endif
            @endif

            {{-- Building a routine: expected against actual, day by day --}}
            @if ($repeats && ($progress['rows'] ?? []) !== [])
                @php
                    $rows = collect($progress['rows']);
                    $maxAbs = max(30, min(180, (int) $rows->map(fn ($r) => abs((int) ($r['deviation'] ?? 0)))->max()));
                @endphp
                <div class="space-y-2" data-habit-summary>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="rounded-full bg-zinc-100 px-3 py-1 dark:bg-zinc-800">{{ __('habits.board.done_of', ['done' => \App\Support\Planner\HabitText::number($summary['done'] + $summary['partly']), 'past' => \App\Support\Planner\HabitText::number($summary['past'])]) }}</span>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ __('habits.board.on_target', ['count' => \App\Support\Planner\HabitText::number($summary['on_target'])]) }}</span>
                        @if ($summary['average_deviation'] !== null)
                            <span class="rounded-full bg-zinc-100 px-3 py-1 dark:bg-zinc-800">{{ __('habits.board.average', ['diff' => \App\Support\Planner\HabitText::deviation($summary['average_deviation'])]) }}</span>
                        @endif
                        @if ($summary['difficulty'])
                            <span class="rounded-full bg-zinc-100 px-3 py-1 dark:bg-zinc-800">{{ __('habits.board.felt', ['level' => \App\Support\Planner\HabitText::number($summary['difficulty'])]) }}</span>
                        @endif
                    </div>
                    @if ($summary['trend'])
                        <p class="text-sm font-medium {{ $summary['trend'] === 'closer' ? 'text-emerald-700 dark:text-emerald-300' : ($summary['trend'] === 'further' ? 'text-amber-700 dark:text-amber-300' : 'text-zinc-600 dark:text-zinc-300') }}" data-habit-trend="{{ $summary['trend'] }}">
                            {{ __('habits.board.trend.'.$summary['trend'], ['first' => $minutes($summary['first_average']), 'last' => $minutes($summary['last_average']), 'ideal' => $ideal ?? '']) }}
                        </p>
                    @endif
                    @if ($summary['top_reason'])
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('habits.board.top_reason', ['reason' => __('habits.reasons.'.$summary['top_reason']), 'count' => \App\Support\Planner\HabitText::number($summary['top_reason_count'])]) }}</p>
                    @endif
                    @if ($summary['no_report'] > 0)
                        <p class="text-sm text-zinc-500">{{ trans_choice('habits.board.no_report', $summary['no_report'], ['count' => $summary['no_report']]) }}</p>
                    @endif
                </div>

                <div class="space-y-1" data-habit-chart role="img" aria-label="{{ __('habits.board.chart_label') }}">
                    <div class="flex justify-between text-[10px] text-zinc-400"><span>{{ __('habits.board.earlier') }}</span></div>
                    <div class="relative flex h-32 items-stretch gap-1 rounded-lg bg-zinc-50 px-1 dark:bg-zinc-900/60" dir="ltr">
                        <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-zinc-300 dark:border-zinc-600"></div>
                        @foreach ($rows as $row)
                            @php
                                $dev = $row['deviation'];
                                $pct = $dev === null ? 0 : max(4, round(min(abs($dev), $maxAbs) * 48 / $maxAbs));
                                $color = $row['on_target'] ? 'bg-emerald-500' : (($dev ?? 0) > 0 ? 'bg-amber-500' : 'bg-sky-500');
                            @endphp
                            <div class="relative min-w-1 max-w-8 flex-1" title="{{ $row['date'] }} · {{ $dev !== null ? \App\Support\Planner\HabitText::deviation($dev) : __('planner.occurrence_phase.'.$row['phase']) }}" wire:key="bar-{{ $row['date'] }}">
                                @if (in_array($row['phase'], ['done', 'partly'], true) && $dev !== null)
                                    <div class="absolute inset-x-0 rounded-sm {{ $color }} {{ $row['phase'] === 'partly' ? 'opacity-60' : '' }}" style="{{ $dev > 0 ? 'top: 50%' : 'bottom: 50%' }}; height: {{ $pct }}%"></div>
                                @elseif ($row['phase'] === 'not_done')
                                    <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-xs font-bold leading-none text-rose-500">×</div>
                                @elseif (in_array($row['phase'], ['awaiting_report', 'no_report'], true))
                                    <div class="absolute left-1/2 top-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full border border-zinc-400"></div>
                                @elseif ($row['phase'] === 'skipped')
                                    <div class="absolute left-1/2 top-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-400"></div>
                                @else
                                    <div class="absolute left-1/2 top-1/2 size-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-300 dark:bg-zinc-700"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-zinc-400">
                        <span>{{ __('habits.board.later') }}</span>
                        <span class="flex flex-wrap gap-3">
                            <span><span class="me-1 inline-block size-2 rounded-sm bg-emerald-500"></span>{{ __('habits.board.legend.on_target') }}</span>
                            <span><span class="me-1 inline-block size-2 rounded-sm bg-amber-500"></span>{{ __('habits.board.legend.late') }}</span>
                            <span><span class="me-1 inline-block size-2 rounded-sm bg-sky-500"></span>{{ __('habits.board.legend.early') }}</span>
                            <span class="text-rose-500">× {{ __('habits.board.legend.not_done') }}</span>
                        </span>
                    </div>
                </div>

                {{-- One short line per day: expected → actual, the difference and the answer --}}
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800" data-habit-list data-habit-table>
                    @foreach ($rows as $row)
                        @php $occ = $row['occurrence']; $rowActions = \App\Support\Planner\OccurrenceTiming::actions($occ); @endphp
                        <li wire:key="board-m-{{ $occ->uuid }}" class="py-2.5 {{ $row['date'] === $today ? 'bg-indigo-50/60 dark:bg-indigo-950/20' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 text-sm">
                                    <div><span class="font-medium">{{ __('habits.board.day_n', ['day' => \App\Support\Planner\HabitText::number($row['day'])]) }}</span> <span class="text-xs text-zinc-500"><x-app.local-date :value="$occ->local_date" /></span></div>
                                    <div class="mt-0.5 text-zinc-600 tabular-nums dark:text-zinc-300">
                                        {{ \App\Support\TemporalCalendar::timeLabel($row['expected'], auth()->user(), $tz) }}
                                        <span aria-hidden="true" class="inline-block rtl:rotate-180">→</span>
                                        {{ $row['actual'] ? \App\Support\TemporalCalendar::timeLabel($row['actual'], auth()->user(), $tz) : '—' }}
                                        @if ($row['deviation'] !== null)<span class="{{ $row['on_target'] ? 'text-emerald-700 dark:text-emerald-300' : (($row['deviation'] ?? 0) > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-sky-700 dark:text-sky-300') }}"> · {{ \App\Support\Planner\HabitText::deviation($row['deviation']) }}</span>@endif
                                    </div>
                                    <div class="text-xs text-zinc-500">{{ __('planner.occurrence_phase.'.$row['phase']) }}@if ($occ->reason) · {{ __('habits.reasons.'.$occ->reason) }}@endif</div>
                                </div>
                                @if (in_array('report', $rowActions, true) || in_array('correct', $rowActions, true))
                                    <flux:button size="xs" :variant="in_array($row['phase'], ['awaiting_report', 'late', 'now'], true) ? 'primary' : 'ghost'" wire:click="openReport('{{ $occ->uuid }}')" data-board-report>{{ in_array('correct', $rowActions, true) ? __('habits.actions.change') : __('habits.actions.tell') }}</flux:button>
                                @endif
                            </div>
                            @if ($reportUuid === $occ->uuid)
                                <div class="mt-2">@include('livewire.planner.partials.report-form', ['occurrence' => $occ])</div>
                            @endif
                        </li>
                    @endforeach
                </ul>

            @endif
        </flux:card>

        {{-- Periods side by side --}}
        @if (count($comparison) >= 2)
            @php
                $best = collect($comparison)->filter(fn ($c) => $c['period']->days() >= 3)->sortByDesc(fn ($c) => $replaces ? ($c['urges']['rate'] ?? -1) : ($c['summary']['on_target_rate'] ?? -1))->first();
            @endphp
            <flux:card class="space-y-3" data-habit-comparison>
                <flux:heading size="lg">{{ __('habits.compare.title') }}</flux:heading>
                <flux:text class="text-sm">{{ __('habits.compare.help') }}</flux:text>
                <div class="-mx-4 overflow-x-auto sm:mx-0">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead class="text-xs uppercase tracking-wide text-zinc-500">
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.period') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.days') }}</th>
                                @if ($replaces)
                                    <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.urges_per_day') }}</th>
                                    <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.replaced') }}</th>
                                    <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.strength') }}</th>
                                @else
                                    <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.done') }}</th>
                                    <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.on_target') }}</th>
                                    <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.from_ideal') }}</th>
                                @endif
                                <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.automatic') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('habits.compare.verdict') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($comparison as $entry)
                                @php $p = $entry['period']; $s = $entry['summary']; $u = $entry['urges']; @endphp
                                <tr wire:key="compare-{{ $p->uuid }}" data-compare-period="{{ $p->number }}">
                                    <td class="px-3 py-2">
                                        <div class="font-medium [overflow-wrap:anywhere]" dir="auto">{{ $p->number }}. {{ $p->focus }}</div>
                                        @if ($p->target_time)<div class="text-xs text-zinc-500">{{ __('habits.compare.target', ['time' => $p->target_time]) }}</div>@endif
                                        @if ($best && $best['period']->is($p))<flux:badge size="sm" color="emerald" class="mt-1">{{ __('habits.compare.best') }}</flux:badge>@endif
                                    </td>
                                    <td class="px-3 py-2 tabular-nums">{{ \App\Support\Planner\HabitText::number($p->days()) }}</td>
                                    @if ($replaces)
                                        <td class="px-3 py-2 tabular-nums">{{ $u['per_day'] !== null ? \App\Support\Planner\HabitText::number($u['per_day']) : '—' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $u['rate'] !== null ? \App\Support\Planner\HabitText::number($u['rate']).'%' : '—' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $u['strength'] !== null ? \App\Support\Planner\HabitText::number($u['strength']) : '—' }}</td>
                                    @else
                                        <td class="px-3 py-2 tabular-nums">{{ $s['done_rate'] !== null ? \App\Support\Planner\HabitText::number($s['done_rate']).'%' : '—' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $s['on_target_rate'] !== null ? \App\Support\Planner\HabitText::number($s['on_target_rate']).'%' : '—' }}</td>
                                        <td class="px-3 py-2 tabular-nums">{{ $s['average_from_ideal'] !== null ? \App\Support\Planner\HabitText::deviation($s['average_from_ideal']) : '—' }}</td>
                                    @endif
                                    <td class="px-3 py-2 tabular-nums">{{ $p->automaticityScore() !== null ? \App\Support\Planner\HabitText::number($p->automaticityScore()).' / '.\App\Support\Planner\HabitText::number(5) : '—' }}</td>
                                    <td class="px-3 py-2">{{ $p->verdict ? __('habits.periods.verdicts.'.$p->verdict) : __('habits.compare.running') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    @endif
</section>
