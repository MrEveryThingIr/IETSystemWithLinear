<section class="space-y-6">
    <x-app.page-header :title="__('structure.title')" :description="__('structure.subtitle')">
        <x-slot:actions>
            <flux:button :href="route('contexts.contents.studio', [$context, $content])" variant="ghost">
                {{ __('structure.back') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading>{{ __('structure.how_it_works') }}</flux:heading>
                <flux:text>{{ __('structure.help') }}</flux:text>
            </div>
            @if ($hasUnsavedChanges)
                <flux:badge>{{ __('studio.private_draft') }}</flux:badge>
            @endif
        </div>
        <flux:callout>{{ __('structure.publication_notice') }}</flux:callout>
    </flux:card>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <flux:card class="space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('structure.contents') }}</flux:heading>
                    <flux:text>{{ __('structure.contents_help') }}</flux:text>
                </div>
                <flux:badge>{{ count($childIds) }}</flux:badge>
            </div>

            @forelse ($structureItems as $index => $item)
                @php
                    $itemRevision = $item->draftRevision ?? $item->activeRevision;
                @endphp
                <div wire:key="outline-item-{{ $item->uuid }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold tabular-nums text-zinc-400">{{ $index + 1 }}</span>
                            <flux:badge size="sm">{{ $item->definition->name }}</flux:badge>
                            <flux:badge size="sm">{{ __('ui.content.status_'.$item->status) }}</flux:badge>
                        </div>
                        <div class="font-semibold text-zinc-950 dark:text-white" dir="auto">{{ $itemRevision?->title ?? __('ui.content.untitled') }}</div>
                        <div class="mt-1"><x-app.actor-identity :actor="$item->author" size="xs" /></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="moveUp({{ $item->id }})" size="sm" variant="ghost" :disabled="$index === 0">{{ __('structure.move_up') }}</flux:button>
                        <flux:button wire:click="moveDown({{ $item->id }})" size="sm" variant="ghost" :disabled="$index === count($childIds) - 1">{{ __('structure.move_down') }}</flux:button>
                        <flux:button wire:click="removeChild({{ $item->id }})" size="sm" variant="danger">{{ __('structure.remove') }}</flux:button>
                    </div>
                </div>
            @empty
                <x-app.empty-state :title="__('structure.empty')" :description="__('structure.empty_help')" />
            @endforelse
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading>{{ __('structure.add_child') }}</flux:heading>
                    <flux:text>{{ __('structure.add_child_help') }}</flux:text>
                </div>

                <flux:input wire:model.live.debounce.300ms="search" :label="__('structure.child_content')" :placeholder="__('structure.choose_child')" />

                <flux:select wire:model="selectedChildId" :label="__('structure.child_content')">
                    <option value="">{{ __('structure.choose_child') }}</option>
                    @foreach ($candidateContents as $candidate)
                        @continue(in_array($candidate->id, $childIds, true))
                        @php
                            $candidateRevision = $candidate->draftRevision ?? $candidate->activeRevision;
                        @endphp
                        <option value="{{ $candidate->id }}">{{ $candidateRevision?->title ?? __('ui.content.untitled') }} · {{ $candidate->definition->name }}</option>
                    @endforeach
                </flux:select>
                @error('selectedChildId')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror

                <flux:button wire:click="addChild" class="w-full" variant="ghost">{{ __('structure.add') }}</flux:button>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('structure.save_title') }}</flux:heading>
                <flux:text>{{ __('structure.save_help') }}</flux:text>
                <flux:button wire:click="save" wire:loading.attr="disabled" wire:target="save" class="w-full" variant="primary" :disabled="!$hasUnsavedChanges">
                    {{ __('structure.save') }}
                </flux:button>
            </flux:card>
        </div>
    </div>
</section>
