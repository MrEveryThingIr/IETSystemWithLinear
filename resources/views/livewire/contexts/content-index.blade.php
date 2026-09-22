<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header
        :title="__('ui.context_content.title')"
        :description="__('ui.context_content.help')"
    >
        <x-slot:actions>
            @if ($canCreate && ! $creatorOpen)
                <flux:button wire:click="openCreator" variant="primary" class="w-full sm:w-auto">
                    {{ __('ui.context_content.create_with_blueprint') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>

    @unless ($canCreate)
        <flux:callout>{{ __('ui.context_content.read_only') }}</flux:callout>
    @endunless

    @if ($canCreate && $creatorOpen)
        <flux:card class="space-y-5">
            @if (! $selectedBlueprintVersion)
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('ui.context_content.blueprints_title') }}</flux:heading>
                        <flux:text>{{ __('ui.context_content.blueprints_help') }}</flux:text>
                    </div>
                    <flux:button wire:click="cancelCreator" variant="ghost">{{ __('studio.cancel') }}</flux:button>
                </div>

                <flux:input
                    wire:model.live.debounce.300ms="blueprintSearch"
                    :label="__('ui.context_content.blueprint_search')"
                    :placeholder="__('ui.context_content.blueprint_search_placeholder')"
                />

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($blueprints as $blueprint)
                        <button
                            type="button"
                            wire:key="blueprint-{{ $blueprint->uuid }}"
                            wire:click="selectBlueprint({{ $blueprint->active_version_id }})"
                            class="min-w-0 rounded-xl border border-zinc-200 p-4 text-start transition hover:border-zinc-400 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-500 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-900"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold" dir="auto">{{ $blueprint->name }}</span>
                                <flux:badge size="sm">{{ $blueprint->category }}</flux:badge>
                            </div>
                            @if ($blueprint->description)
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400" dir="auto">{{ $blueprint->description }}</p>
                            @endif
                        </button>
                    @empty
                        <div class="sm:col-span-2 lg:col-span-3">
                            <x-app.empty-state :title="__('ui.context_content.no_blueprints')" />
                        </div>
                    @endforelse
                </div>
            @else
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg" dir="auto">{{ $selectedBlueprint->name }}</flux:heading>
                            <flux:badge>{{ __('ui.context_content.blueprint_version', ['version' => $selectedBlueprintVersion->version]) }}</flux:badge>
                        </div>
                        <flux:text dir="auto">{{ $selectedBlueprint->description }}</flux:text>
                    </div>
                    <flux:button wire:click="backToBlueprints" variant="ghost">
                        {{ __('ui.context_content.change_blueprint') }}
                    </flux:button>
                </div>

                <form wire:submit="createFromBlueprint" class="space-y-4">
                    <flux:input wire:model="title" :label="__('ui.content.title')" maxlength="255" />

                    @foreach ($selectedBlueprintVersion->definition_schema['fields'] as $field)
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

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:button type="button" wire:click="cancelCreator" variant="ghost" class="w-full sm:w-auto">
                            {{ __('studio.cancel') }}
                        </flux:button>
                        <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="createFromBlueprint">
                            {{ __('ui.context_content.create_and_open_studio') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('ui.context_content.contents') }}</flux:heading>
            <flux:text>{{ __('ui.context_content.contents_help') }}</flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($contents as $content)
                @php
                    $revision = $content->draftRevision ?? $content->activeRevision;
                    $cover = $revision?->assets?->first(fn ($asset) => $asset->mediaKind() === 'image');
                    $blueprint = $content->blueprintVersion?->blueprint;
                @endphp
                <article
                    wire:key="content-{{ $content->uuid }}"
                    class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-950"
                >
                    @if ($cover)
                        <a href="{{ route('contexts.contents.show', [$context, $content]) }}" class="block bg-zinc-100 dark:bg-zinc-900">
                            <img
                                src="{{ route('contexts.contents.assets.show', [$context, $content, $cover]) }}"
                                alt="{{ $cover->alt_text ?: $cover->original_filename }}"
                                class="h-40 w-full object-cover"
                                loading="lazy"
                            />
                        </a>
                    @endif

                    <div class="space-y-4 p-4">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                @if ($blueprint)
                                    <flux:badge size="sm">{{ $blueprint->name }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ $content->definition->name }}</flux:badge>
                                @endif
                                <span>·</span>
                                <span>{{ __('ui.content.status_'.$content->status) }}</span>
                            </div>
                            <a
                                href="{{ route('contexts.contents.show', [$context, $content]) }}"
                                class="block break-words text-lg font-semibold hover:underline"
                                dir="auto"
                            >
                                {{ $revision?->title ?: __('ui.content.untitled') }}
                            </a>
                            <x-app.actor-identity :actor="$content->author" size="xs" />
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:justify-between">
                            <flux:button :href="route('contexts.contents.show', [$context, $content])" size="sm" variant="ghost">
                                {{ __('ui.context_content.open') }}
                            </flux:button>
                            @can('update', $content)
                                <flux:button :href="route('contexts.contents.studio', [$context, $content])" size="sm" variant="primary">
                                    {{ __('studio.title') }}
                                </flux:button>
                            @endcan
                        </div>
                    </div>
                </article>
            @empty
                <div class="md:col-span-2">
                    <x-app.empty-state :title="__('ui.context_content.none')" />
                </div>
            @endforelse
        </div>
    </flux:card>

    @if ($canManageDefinitions || ($canCreate && $definitions->isNotEmpty()))
        <details class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
            <summary class="cursor-pointer font-semibold">{{ __('ui.context_content.advanced_authoring') }}</summary>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                @if ($canManageDefinitions)
                    <div class="space-y-4">
                        <div>
                            <flux:heading>{{ __('ui.context_content.local_definition') }}</flux:heading>
                            <flux:text>{{ __('ui.context_content.local_definition_help_advanced') }}</flux:text>
                        </div>
                        <form wire:submit="createDefinition" class="space-y-3">
                            <flux:input wire:model="definitionName" :label="__('ui.content.definition_name')" maxlength="120" />
                            <flux:button type="submit" variant="ghost" class="w-full sm:w-auto">
                                {{ __('ui.context_content.create_definition') }}
                            </flux:button>
                        </form>
                    </div>
                @endif

                @if ($canCreate && $definitions->isNotEmpty())
                    <div class="space-y-4">
                        <div>
                            <flux:heading>{{ __('ui.context_content.create_from_definition') }}</flux:heading>
                            <flux:text>{{ __('ui.context_content.create_from_definition_help') }}</flux:text>
                        </div>

                        <form wire:submit="createFromDefinition" class="space-y-4">
                            <flux:select wire:model.live="definitionId" :label="__('ui.content.definition')">
                                <option value="">{{ __('ui.content.choose_definition') }}</option>
                                @foreach ($definitions as $definition)
                                    <option value="{{ $definition->id }}">{{ $definition->name }}</option>
                                @endforeach
                            </flux:select>

                            @if ($selectedDefinitionVersion)
                                <flux:input wire:model="definitionTitle" :label="__('ui.content.title')" maxlength="255" />
                                @foreach ($selectedDefinitionVersion->schema['fields'] as $field)
                                    @switch($field['type'])
                                        @case('short_text')
                                            <x-space-content.fields.short-text :field="$field" :model="'definitionPayload.'.$field['key']" />
                                            @break
                                        @case('long_text')
                                            <x-space-content.fields.long-text :field="$field" :model="'definitionPayload.'.$field['key']" />
                                            @break
                                        @case('number')
                                            <x-space-content.fields.number :field="$field" :model="'definitionPayload.'.$field['key']" />
                                            @break
                                        @case('date')
                                            <x-space-content.fields.date :field="$field" :model="'definitionPayload.'.$field['key']" />
                                            @break
                                        @case('boolean')
                                            <x-space-content.fields.boolean :field="$field" :model="'definitionPayload.'.$field['key']" />
                                            @break
                                        @case('select')
                                            <x-space-content.fields.select :field="$field" :model="'definitionPayload.'.$field['key']" />
                                            @break
                                    @endswitch
                                @endforeach
                                <flux:button type="submit" variant="ghost" class="w-full sm:w-auto">
                                    {{ __('ui.content.save_draft') }}
                                </flux:button>
                            @endif
                        </form>
                    </div>
                @endif
            </div>
        </details>
    @endif
</section>
