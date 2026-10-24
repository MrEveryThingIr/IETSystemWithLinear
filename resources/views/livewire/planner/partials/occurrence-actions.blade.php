{{-- The buttons that make sense for this occurrence right now (OccurrenceTiming decides). --}}
@php
    $actions = \App\Support\Planner\OccurrenceTiming::actions($occurrence);
    $prerequisitesLeft = $prerequisitesLeft ?? 0;
@endphp
@if ($actions !== [])
    <div class="flex flex-wrap gap-2" data-occurrence-actions="{{ $occurrence->uuid }}">
        @if (in_array('start', $actions, true) && $occurrence->status === \App\PlanOccurrenceStatus::Scheduled && $prerequisitesLeft === 0 && ($showStart ?? true))
            <flux:button wire:click="quickStart('{{ $occurrence->uuid }}')" size="sm" variant="primary" icon="play" data-occurrence-start>{{ __('planner.plan.start') }}</flux:button>
        @endif
        @if (in_array('done', $actions, true))
            <flux:button wire:click="quickDone('{{ $occurrence->uuid }}')" size="sm" :variant="in_array('start', $actions, true) ? 'filled' : 'primary'" icon="check" data-occurrence-done>{{ $occurrence->status === \App\PlanOccurrenceStatus::InProgress ? __('planner.plan.finish') : __('habits.actions.done_now') }}</flux:button>
        @endif
        @if (in_array('report', $actions, true))
            <flux:button wire:click="openReport('{{ $occurrence->uuid }}')" size="sm" :variant="$actions === ['report'] ? 'primary' : 'ghost'" icon="pencil-square" data-occurrence-report>{{ __('habits.actions.tell') }}</flux:button>
        @endif
        @if (in_array('correct', $actions, true))
            <flux:button wire:click="openReport('{{ $occurrence->uuid }}', '{{ match ($occurrence->executionPhase()) { 'partly' => 'partly', 'skipped', 'not_done' => 'not_done', default => 'done' } }}')" size="sm" variant="ghost" icon="pencil" data-occurrence-correct>{{ __('habits.actions.change') }}</flux:button>
        @endif
        @if (in_array('skip', $actions, true))
            <flux:button wire:click="quickSkip('{{ $occurrence->uuid }}')" size="sm" variant="ghost" data-occurrence-skip>{{ $occurrence->executionPhase() === 'upcoming' ? __('habits.actions.skip_this_one') : __('habits.actions.not_today') }}</flux:button>
        @endif
        @if (in_array('cancel', $actions, true) && ($showCancel ?? true))
            <flux:button wire:click="quickCancel('{{ $occurrence->uuid }}')" wire:confirm="{{ __('habits.actions.cancel_confirm') }}" size="sm" variant="ghost" class="text-red-600" data-occurrence-cancel>{{ __('planner.plan.cancel_occurrence') }}</flux:button>
        @endif
    </div>
@endif
@error('execution.'.$occurrence->id)<div class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</div>@enderror
