<details class="rounded-xl border p-4" @if($revisionAdjustment !== '') open @endif>
    <summary class="cursor-pointer font-semibold">{{ __('living_planner.revise') }}</summary>
    <p class="my-3 text-sm text-zinc-500">{{ __('living_planner.revision_help') }}</p>
    @if($revisionAdjustment !== '')<p role="status">{{ __('living_planner.applying_adjustment') }}</p>@endif
    <form wire:submit="revise" class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="revisionScope" :label="__('living_planner.scope')"><option value="future">{{ __('living_planner.future') }}</option><option value="one">{{ __('living_planner.one') }}</option></flux:select>
        @if($revisionScope === 'one')
            <flux:select wire:model="revisionOccurrence" :label="__('living_planner.select')"><option value="">{{ __('living_planner.select') }}</option>@foreach($plans->firstWhere('uuid', $planUuid)?->occurrences()->where('status', 'scheduled')->where('scheduled_start_at', '>', now())->orderBy('scheduled_start_at')->get() ?? [] as $item)<option value="{{ $item->uuid }}">{{ $item->plan->title }} · {{ \App\Support\TemporalCalendar::format($item->scheduled_start_at, auth()->user(), $timezone, 'yyyy-MM-dd HH:mm') }}</option>@endforeach</flux:select>
        @endif
        <x-app.calendar-date-input model="revisionDate" :label="__('living_planner.starts_on')" />
        <flux:input type="time" wire:model="revisionTime" :label="__('living_planner.start_time')" />
        <flux:input type="number" min="1" max="10080" wire:model="revisionMinutes" :label="__('living_planner.duration')" />
        <flux:button type="submit" variant="primary">{{ __('living_planner.apply_future') }}</flux:button>
    </form>
</details>
