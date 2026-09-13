<section class="space-y-6">
    <x-app.page-header :title="__('structure.title')" :description="__('structure.subtitle')">
        <x-slot:actions>
            <flux:button :href="route('groups.spaces.contents.show', [$group, $space, $content])" variant="ghost">
                {{ __('structure.back') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :current-space="$space" />
    <x-app.space-section-tabs :group="$group" :space="$space" current="content" />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('structure.how_it_works') }}</flux:heading>
            <flux:text>{{ __('structure.help') }}</flux:text>
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
                    $title = $item->draftRevision?->title ?? $item->activeRevision?->title ?? __('ui.content.untitled');
                @endphp
                <div wire:key="structure-item-{{ $item->id }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $index + 1 }}</div>
                        <flux:heading>{{ $title }}</flux:heading>
                        <flux:text class="text-sm">{{ $item->author->user?->username ?? __('ui.common.unknown_account') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="moveUp({{ $item->id }})" size="sm" variant="ghost" :disabled="$index === 0">
                            {{ __('structure.move_up') }}
                        </flux:button>
                        <flux:button wire:click="moveDown({{ $item->id }})" size="sm" variant="ghost" :disabled="$index === count($childIds) - 1">
                            {{ __('structure.move_down') }}
                        </flux:button>
                        <flux:button wire:click="removeChild({{ $item->id }})" size="sm" variant="danger">
                            {{ __('structure.remove') }}
                        </flux:button>
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

                <flux:select wire:model="selectedChildId" :label="__('structure.child_content')">
                    <option value="">{{ __('structure.choose_child') }}</option>
                    @foreach ($candidateContents as $candidate)
                        @continue(in_array($candidate->id, $childIds, true))
                        @php
                            $candidateTitle = $candidate->draftRevision?->title ?? $candidate->activeRevision?->title ?? __('ui.content.untitled');
                        @endphp
                        <option value="{{ $candidate->id }}">{{ $candidateTitle }}</option>
                    @endforeach
                </flux:select>
                @error('selectedChildId')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror

                <flux:button wire:click="addChild" class="w-full" variant="ghost">
                    {{ __('structure.add') }}
                </flux:button>
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('structure.save_title') }}</flux:heading>
                <flux:text>{{ __('structure.save_help') }}</flux:text>
                <flux:button wire:click="save" class="w-full" variant="primary">
                    {{ __('structure.save') }}
                </flux:button>
            </flux:card>
        </div>
    </div>
</section>
