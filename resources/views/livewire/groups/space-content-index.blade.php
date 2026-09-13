<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$space->name">
        <x-slot:actions>
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :current-space="$space" />
    <x-app.space-section-tabs :group="$group" :space="$space" current="content" />

    @php
        $nextStep = match (true) {
            $definitions->isEmpty() => __('workflow.content.next_define'),
            $draftContents->isNotEmpty() => __('workflow.content.next_review'),
            $publishedContents->isEmpty() => __('workflow.content.next_create'),
            default => __('workflow.content.next_continue'),
        };
    @endphp

    <flux:card class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl space-y-1">
                <flux:heading size="lg">{{ __('workflow.content.title') }}</flux:heading>
                <flux:text>{{ __('workflow.content.subtitle') }}</flux:text>
            </div>
            @can('manage', $space)
                <flux:button :href="route('groups.spaces.manage', $group)" size="sm" variant="ghost">
                    {{ __('workflow.content.manage_definitions') }}
                </flux:button>
            @endcan
        </div>

        <x-app.workflow-pipeline :steps="[
            ['label' => __('workflow.content.definition'), 'description' => __('workflow.content.definition_help')],
            ['label' => __('workflow.content.draft'), 'description' => __('workflow.content.draft_help')],
            ['label' => __('workflow.content.review'), 'description' => __('workflow.content.review_help')],
            ['label' => __('workflow.content.publish'), 'description' => __('workflow.content.publish_help')],
        ]" />

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.content.next_step') }}</div>
            <div class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $nextStep }}</div>
        </div>
    </flux:card>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('workflow.content.library_title') }}</flux:heading>
                    <flux:text>{{ __('workflow.content.library_help') }}</flux:text>
                </div>

                @forelse ($publishedContents as $item)
                    <a
                        wire:key="published-content-{{ $item->id }}"
                        href="{{ route('groups.spaces.contents.show', [$group, $space, $item]) }}"
                        class="block rounded-xl border border-zinc-200 p-4 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <flux:heading>{{ $item->activeRevision?->title ?? __('ui.content.untitled') }}</flux:heading>
                                <flux:text class="text-sm">
                                    {{ $item->definition->name }} · {{ $item->author->user?->username ?? __('ui.common.unknown_account') }}
                                </flux:text>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <flux:badge>{{ __('ui.content.status_published') }}</flux:badge>
                                <flux:text class="text-xs">{{ __('workflow.content.open_published') }} →</flux:text>
                            </div>
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('ui.content.none_published')" :description="__('ui.content.none_published_help')" />
                @endforelse
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('workflow.content.workspace_title') }}</flux:heading>
                    <flux:text>{{ __('workflow.content.workspace_help') }}</flux:text>
                </div>

                @forelse ($draftContents as $item)
                    <a
                        wire:key="draft-content-{{ $item->id }}"
                        href="{{ route('groups.spaces.contents.show', [$group, $space, $item]) }}"
                        class="block rounded-xl border border-dashed border-zinc-300 bg-zinc-50/70 p-4 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <flux:heading>{{ $item->draftRevision?->title ?? __('ui.content.untitled') }}</flux:heading>
                                <flux:text class="text-sm">{{ $item->definition->name }}</flux:text>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <flux:badge>{{ __('ui.content.status_draft') }}</flux:badge>
                                <flux:text class="text-xs">{{ __('workflow.content.continue_draft') }} →</flux:text>
                            </div>
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('workflow.content.no_drafts')" :description="__('workflow.content.no_drafts_help')" />
                @endforelse
            </flux:card>
        </div>

        <flux:card class="space-y-5 self-start xl:sticky xl:top-6">
            <div class="space-y-1">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.content.draft') }}</div>
                <flux:heading size="lg">{{ __('workflow.content.start_title') }}</flux:heading>
                <flux:text>{{ __('workflow.content.start_help') }}</flux:text>
            </div>

            @if ($definitions->isEmpty())
                <flux:callout>{{ __('ui.content.no_active_definitions') }}</flux:callout>
                @can('manage', $space)
                    <flux:button :href="route('groups.spaces.manage', $group)" class="w-full" variant="primary">
                        {{ __('workflow.content.manage_definitions') }}
                    </flux:button>
                @endcan
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

                        <flux:button type="submit" class="w-full" variant="primary" wire:loading.attr="disabled" wire:target="create">
                            {{ __('ui.content.save_draft') }}
                        </flux:button>
                    @endif
                </form>
            @endif
        </flux:card>
    </div>
</section>
