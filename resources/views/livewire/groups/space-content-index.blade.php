<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$space->name">
        <x-slot:actions>
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :current-space="$space" />
    <x-app.space-section-tabs :group="$group" :space="$space" current="content" />

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('ui.content.published_title') }}</flux:heading>
                    <flux:text>{{ __('ui.content.published_help') }}</flux:text>
                </div>

                @forelse ($publishedContents as $item)
                    <a
                        wire:key="published-content-{{ $item->id }}"
                        href="{{ route('groups.spaces.contents.show', [$group, $space, $item]) }}"
                        class="block rounded-lg border border-zinc-200 p-4 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <flux:heading>{{ $item->activeRevision?->title ?? __('ui.content.untitled') }}</flux:heading>
                                <flux:text class="text-sm">
                                    {{ $item->definition->name }} · {{ $item->author->user?->username ?? __('ui.common.unknown_account') }}
                                </flux:text>
                            </div>
                            <flux:badge>{{ __('ui.content.status_published') }}</flux:badge>
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('ui.content.none_published')" :description="__('ui.content.none_published_help')" />
                @endforelse
            </flux:card>

            @if ($draftContents->isNotEmpty())
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('ui.content.my_drafts') }}</flux:heading>
                        <flux:text>{{ __('ui.content.my_drafts_help') }}</flux:text>
                    </div>

                    @foreach ($draftContents as $item)
                        <a
                            wire:key="draft-content-{{ $item->id }}"
                            href="{{ route('groups.spaces.contents.show', [$group, $space, $item]) }}"
                            class="block rounded-lg border border-zinc-200 p-4 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <flux:heading>{{ $item->draftRevision?->title ?? __('ui.content.untitled') }}</flux:heading>
                                    <flux:text class="text-sm">{{ $item->definition->name }}</flux:text>
                                </div>
                                <flux:badge>{{ __('ui.content.status_draft') }}</flux:badge>
                            </div>
                        </a>
                    @endforeach
                </flux:card>
            @endif
        </div>

        <flux:card class="space-y-5 self-start">
            <div>
                <flux:heading size="lg">{{ __('ui.content.create') }}</flux:heading>
                <flux:text>{{ __('ui.content.create_help') }}</flux:text>
            </div>

            @if ($definitions->isEmpty())
                <flux:callout>{{ __('ui.content.no_active_definitions') }}</flux:callout>
            @else
                <form wire:submit="create" class="space-y-4">
                    <flux:select wire:model.live="definitionId" :label="__('ui.content.definition')">
                        <option value="">{{ __('ui.content.choose_definition') }}</option>
                        @foreach ($definitions as $definition)
                            <option value="{{ $definition->id }}">{{ $definition->name }}</option>
                        @endforeach
                    </flux:select>

                    @if ($selectedVersion)
                        <flux:input wire:model="title" :label="__('ui.content.title')" maxlength="255" />

                        @foreach ($selectedVersion->schema['fields'] as $field)
                            @switch($field['type'])
                                @case('short_text')
                                    <x-space-content.fields.short-text :field="$field" :model="'payload.'.$field['key']" />
                                    @break
                                @case('long_text')
                                    <x-space-content.fields.long-text :field="$field" :model="'payload.'.$field['key']" />
                                    @break
                                @case('number')
                                    <x-space-content.fields.number :field="$field" :model="'payload.'.$field['key']" />
                                    @break
                                @case('date')
                                    <x-space-content.fields.date :field="$field" :model="'payload.'.$field['key']" />
                                    @break
                                @case('boolean')
                                    <x-space-content.fields.boolean :field="$field" :model="'payload.'.$field['key']" />
                                    @break
                                @case('select')
                                    <x-space-content.fields.select :field="$field" :model="'payload.'.$field['key']" />
                                    @break
                            @endswitch
                        @endforeach

                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="create">
                            {{ __('ui.content.save_draft') }}
                        </flux:button>
                    @endif
                </form>
            @endif
        </flux:card>
    </div>
</section>
