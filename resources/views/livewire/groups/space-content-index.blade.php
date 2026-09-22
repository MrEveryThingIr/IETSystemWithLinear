<section class="space-y-6">
    <x-app.page-header :title="$space->name" :description="$group->name">
        <x-slot:actions>
            @can('manage', $space)
                <flux:button :href="route('groups.spaces.manage', $group)" variant="ghost">{{ __('workflow.content.manage_definitions') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :current-space="$space" />
    <x-app.space-section-tabs :group="$group" :space="$space" current="content" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('workflow.content.library_title') }}</flux:heading>
                    <flux:text>{{ __('workflow.content.library_help') }}</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @forelse ($publishedContents as $item)
                        @php
                            $revision = $item->activeRevision;
                            $cover = $revision?->assets?->first(fn ($asset) => $asset->mediaKind() === 'image');
                            $bodyValue = collect($revision?->payload ?? [])->first(fn ($value) => is_string($value) && trim($value) !== '');
                            $excerpt = is_string($bodyValue) ? \Illuminate\Support\Str::limit(trim($bodyValue), 180) : null;
                        @endphp
                        <article wire:key="published-{{ $item->uuid }}" class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                            @if ($cover)
                                <a href="{{ route('groups.spaces.contents.show', [$group, $space, $item]) }}" class="block bg-zinc-100 dark:bg-zinc-900">
                                    <img src="{{ route('groups.spaces.contents.assets.show', [$group, $space, $item, $cover]) }}" alt="{{ $cover->alt_text ?: $cover->original_filename }}" class="h-44 w-full object-cover" loading="lazy" />
                                </a>
                            @endif

                            <div class="space-y-4 p-4">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                        <x-app.actor-identity :actor="$item->author" size="xs" />
                                        <span>·</span>
                                        <span>{{ $item->published_at?->timezone($group->timezone ?: 'UTC')->format('Y-m-d') }}</span>
                                        <flux:badge size="sm">{{ $item->definition->name }}</flux:badge>
                                    </div>
                                    <a href="{{ route('groups.spaces.contents.show', [$group, $space, $item]) }}" class="block text-lg font-semibold text-zinc-950 hover:underline dark:text-white" dir="auto">{{ $revision?->title ?? __('ui.content.untitled') }}</a>
                                    @if ($excerpt)
                                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400" dir="auto">{{ $excerpt }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <flux:button :href="route('groups.spaces.contents.show', [$group, $space, $item])" size="sm" variant="ghost">{{ __('workflow.content.open_published') }}</flux:button>
                                    @can('update', $item)
                                        <div class="flex gap-1">
                                            <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $item])" size="sm" variant="ghost">{{ __('studio.title') }}</flux:button>
                                            <flux:button :href="route('groups.spaces.contents.outline', [$group, $space, $item])" size="sm" variant="ghost">{{ __('studio.outline') }}</flux:button>
                                        </div>
                                    @endcan
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="md:col-span-2">
                            <x-app.empty-state :title="__('ui.content.none_published')" :description="__('ui.content.none_published_help')" />
                        </div>
                    @endforelse
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('workflow.content.my_drafts_title') }}</flux:heading>
                    <flux:text>{{ __('workflow.content.my_drafts_help') }}</flux:text>
                </div>

                @forelse ($draftContents as $item)
                    <div wire:key="my-draft-{{ $item->uuid }}" class="flex flex-col gap-3 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/60 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="min-w-0">
                            <div class="font-semibold" dir="auto">{{ $item->draftRevision?->title ?? __('ui.content.untitled') }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ $item->definition->name }} · {{ __('ui.content.status_draft') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $item])" size="sm" variant="primary">{{ __('workflow.content.continue_draft') }}</flux:button>
                            <flux:button :href="route('groups.spaces.contents.outline', [$group, $space, $item])" size="sm" variant="ghost">{{ __('studio.outline') }}</flux:button>
                        </div>
                    </div>
                @empty
                    <x-app.empty-state :title="__('workflow.content.no_drafts')" :description="__('workflow.content.no_drafts_help')" />
                @endforelse
            </flux:card>

            @if ($canManageSpace)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('workflow.content.space_drafts_title') }}</flux:heading>
                        <flux:text>{{ __('workflow.content.space_drafts_help') }}</flux:text>
                    </div>

                    @forelse ($spaceDraftContents as $item)
                        <div wire:key="space-draft-{{ $item->uuid }}" class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/60 dark:bg-amber-950/20">
                            <div class="min-w-0">
                                <div class="font-semibold" dir="auto">{{ $item->draftRevision?->title ?? __('ui.content.untitled') }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500"><span dir="auto">{{ $item->definition->name }}</span><span>·</span><x-app.actor-identity :actor="$item->author" size="xs" /></div>
                            </div>
                            <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $item])" size="sm" variant="ghost">{{ __('workflow.content.review_space_draft') }}</flux:button>
                        </div>
                    @empty
                        <x-app.empty-state :title="__('workflow.content.no_space_drafts')" :description="__('workflow.content.no_space_drafts_help')" />
                    @endforelse
                </flux:card>
            @endif

            @if ($archivedContents->isNotEmpty())
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('studio.archive') }}</flux:heading>
                        <flux:text>{{ __('studio.archive_help') }}</flux:text>
                    </div>

                    @foreach ($archivedContents as $item)
                        @php
                            $archivedRevision = $item->draftRevision ?? $item->activeRevision;
                        @endphp
                        <div wire:key="archived-{{ $item->uuid }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 opacity-80 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                            <div class="min-w-0">
                                <div class="font-semibold" dir="auto">{{ $archivedRevision?->title ?? __('ui.content.untitled') }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500"><span dir="auto">{{ $item->definition->name }}</span><span>·</span><x-app.actor-identity :actor="$item->author" size="xs" /></div>
                            </div>
                            <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $item])" size="sm" variant="ghost">{{ __('studio.restore') }}</flux:button>
                        </div>
                    @endforeach
                </flux:card>
            @endif
        </div>

        <flux:card class="space-y-5 self-start xl:sticky xl:top-6">
            <div>
                <flux:heading size="lg">{{ __('workflow.content.start_title') }}</flux:heading>
                <flux:text>{{ __('workflow.content.start_help') }}</flux:text>
            </div>

            @if ($definitions->isEmpty())
                <flux:callout>{{ __('ui.content.no_active_definitions') }}</flux:callout>
                @can('manage', $space)
                    <flux:button :href="route('groups.spaces.manage', $group)" class="w-full" variant="primary">{{ __('workflow.content.manage_definitions') }}</flux:button>
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
                        <flux:button type="submit" class="w-full" variant="primary" wire:loading.attr="disabled" wire:target="create">{{ __('ui.content.save_draft') }}</flux:button>
                    @endif
                </form>
            @endif
        </flux:card>
    </div>
</section>
