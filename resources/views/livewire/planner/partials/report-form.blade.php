{{-- "How did it go?" for one occurrence. Needs the ReportsOccurrences trait. --}}
@php
    $tz = $occurrence->timezone;
    $needsDate = \App\Support\Planner\OccurrenceTiming::policyOf($occurrence) === \App\Support\Planner\OccurrenceTiming::OPEN;
    $chip = 'inline-flex min-h-10 items-center justify-center rounded-full border px-3 text-sm transition';
    $on = 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900';
    $off = 'border-zinc-300 hover:border-zinc-500 dark:border-zinc-600';
@endphp
<form wire:submit="saveReport" class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900 dark:bg-indigo-950/20" data-report-form="{{ $occurrence->uuid }}">
    <div class="text-sm">
        <span class="font-semibold" dir="auto">{{ $occurrence->plan->title }}</span>
        <span class="text-zinc-500">· <x-app.local-date :value="$occurrence->local_date" /> · {{ __('habits.report.expected') }} {{ \App\Support\TemporalCalendar::timeLabel($occurrence->scheduled_start_at, auth()->user(), $tz) }}</span>
    </div>

    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('habits.report.title') }}">
        @foreach (['done', 'partly', 'not_done'] as $option)
            <button type="button" role="radio" aria-checked="{{ $reportOutcome === $option ? 'true' : 'false' }}" wire:click="$set('reportOutcome', '{{ $option }}')" class="{{ $chip }} {{ $reportOutcome === $option ? $on : $off }}" data-report-outcome="{{ $option }}">{{ __('habits.report.outcomes.'.$option) }}</button>
        @endforeach
    </div>

    @if ($reportOutcome !== 'not_done')
        <div class="grid gap-3 sm:grid-cols-3">
            @if ($needsDate)
                <div class="text-sm"><x-app.calendar-date-input model="reportDate" :label="__('habits.report.date')" /></div>
            @endif
            <label class="block text-sm"><span class="mb-1 block font-medium">{{ __('habits.report.started_at') }}</span>
                <input type="time" wire:model="reportTime" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-600 dark:bg-zinc-900" data-report-time></label>
            <label class="block text-sm"><span class="mb-1 block font-medium">{{ __('habits.report.minutes') }}</span>
                <input type="number" min="0" max="1440" inputmode="numeric" wire:model="reportMinutes" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-600 dark:bg-zinc-900"></label>
        </div>
        @if ($reportOutcome === 'partly')
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-medium">{{ __('habits.report.how_much') }}</span>
                @foreach ([25, 50, 75] as $percent)
                    <button type="button" wire:click="$set('reportPercent', {{ $percent }})" class="{{ $chip }} {{ $reportPercent === $percent ? $on : $off }}">{{ $percent }}%</button>
                @endforeach
            </div>
        @endif
        <div class="space-y-1.5">
            <div class="text-sm font-medium">{{ __('habits.report.difficulty') }} <span class="font-normal text-zinc-500">({{ __('habits.optional') }})</span></div>
            <div class="flex flex-wrap gap-2">
                @foreach ([1, 2, 3, 4, 5] as $level)
                    <button type="button" data-difficulty="{{ $level }}" wire:click="$set('reportDifficulty', {{ $reportDifficulty === $level ? 'null' : $level }})" class="{{ $chip }} {{ $reportDifficulty === $level ? $on : $off }}" title="{{ __('habits.report.difficulty_levels.'.$level) }}">{{ __('habits.report.difficulty_levels.'.$level) }}</button>
                @endforeach
            </div>
        </div>
    @else
        <div class="space-y-1.5">
            <div class="text-sm font-medium">{{ __('habits.report.why_not') }} <span class="font-normal text-zinc-500">({{ __('habits.optional') }})</span></div>
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Actions\Planner\TransitionPlanOccurrence::REASONS as $reason)
                    <button type="button" wire:click="$set('reportReason', '{{ $reportReason === $reason ? '' : $reason }}')" class="{{ $chip }} {{ $reportReason === $reason ? $on : $off }}" data-report-reason="{{ $reason }}">{{ __('habits.reasons.'.$reason) }}</button>
                @endforeach
            </div>
            <p class="text-xs text-zinc-500">{{ __('habits.report.why_not_help') }}</p>
        </div>
    @endif

    <label class="block text-sm"><span class="mb-1 block font-medium">{{ __('habits.report.note') }} <span class="font-normal text-zinc-500">({{ __('habits.optional') }})</span></span>
        <textarea wire:model="reportNote" rows="2" maxlength="1000" dir="auto" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-600 dark:bg-zinc-900"></textarea></label>

    @error('report')<div class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</div>@enderror
    @foreach (['reportTime', 'reportDate', 'reportMinutes', 'reportReason', 'reportNote'] as $field)
        @error($field)<div class="text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</div>@enderror
    @endforeach

    <div class="flex flex-wrap gap-2">
        <flux:button type="submit" variant="primary" data-report-save>{{ __('habits.report.save') }}</flux:button>
        <flux:button type="button" variant="ghost" wire:click="closeReport">{{ __('habits.cancel') }}</flux:button>
    </div>
</form>
