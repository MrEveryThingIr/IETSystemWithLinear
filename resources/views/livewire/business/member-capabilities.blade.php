<div class="mt-2 space-y-2" data-member-capabilities>
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($capabilities as $capability)
            <span wire:key="member-capability-{{ $capability->uuid }}" class="inline-flex max-w-full items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-800 dark:bg-violet-950/40 dark:text-violet-200">
                <span class="min-w-0 [overflow-wrap:anywhere]" dir="auto">{{ filled($capability->title) ? $capability->title : ($labels[$capability->concept_id] ?? '') }}</span>
                @if ($canEdit)
                    <button type="button" wire:click="remove('{{ $capability->uuid }}')" class="-me-1 inline-flex size-6 shrink-0 items-center justify-center rounded-full hover:bg-violet-100 dark:hover:bg-violet-900" aria-label="{{ __('capabilities.remove_named', ['name' => $labels[$capability->concept_id] ?? '']) }}">×</button>
                @endif
            </span>
        @endforeach
        @if ($canEdit && ! $adding)
            <button type="button" wire:click="$set('adding', true)" class="inline-flex min-h-7 items-center rounded-full border border-dashed border-zinc-300 px-2.5 text-xs text-zinc-600 hover:border-zinc-500 dark:border-zinc-600 dark:text-zinc-300" data-member-capability-add>+ {{ __('capabilities.add_member_skill') }}</button>
        @endif
    </div>
    <flux:error name="conceptId" />
    @if ($canEdit && $adding)
        <div class="rounded-xl border border-zinc-200 p-2 dark:border-zinc-700">
            <livewire:categories.picker wire:model.live="conceptId" :owner="$businessOwner" :kinds="['skill', 'service']" :key="'member-picker-'.$membershipId" />
            <div class="mt-2 flex justify-end">
                <flux:button size="sm" variant="ghost" wire:click="$set('adding', false)">{{ __('ui.common.cancel') }}</flux:button>
            </div>
        </div>
    @endif
</div>
