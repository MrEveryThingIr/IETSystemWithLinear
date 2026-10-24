@props(['actorId'])

@php
    $interestCount = \App\Models\Interest::query()->heldBy((int) $actorId)->count();
    $abilityCount = \App\Models\Capability::query()->where('holder_actor_id', $actorId)->active()->count();
@endphp

{{-- Interests and abilities have one home: My items. The profile only points there. --}}
<section {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900') }} data-items-summary>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 space-y-1">
            <h2 class="text-lg font-semibold">{{ __('items.summary.title') }}</h2>
            <p class="text-sm text-zinc-500">{{ __('items.summary.help') }}</p>
            <p class="pt-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                {{ trans_choice('items.summary.interests', $interestCount, ['count' => $interestCount]) }}
                · {{ trans_choice('items.summary.abilities', $abilityCount, ['count' => $abilityCount]) }}
            </p>
        </div>
        <flux:button :href="route('items.index')" wire:navigate variant="primary" icon:trailing="arrow-right" class="shrink-0">{{ __('items.summary.open') }}</flux:button>
    </div>
</section>
