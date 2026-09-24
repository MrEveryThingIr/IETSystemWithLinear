<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('library.title')" :description="__('library.help')" />

    <flux:card class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-3">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('library.filters.search')" :placeholder="__('library.filters.search_placeholder')" />
            <flux:select wire:model.live="blueprint" :label="__('library.filters.type')">
                <option value="">{{ __('library.filters.all_types') }}</option>
                @foreach ($blueprintOptions as $option)
                    <option value="{{ $option['slug'] }}">{{ $option['name'] }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="concept" :label="__('library.filters.concept')" :placeholder="__('library.filters.concept_placeholder')" />
        </div>
        @if ($search !== '' || $blueprint !== '' || $concept !== '')
            <flux:button wire:click="clearFilters" variant="ghost" size="sm">{{ __('library.filters.clear') }}</flux:button>
        @endif
    </flux:card>

    @if ($placingContentUuid !== '')
        @php($placingContent = $contents->firstWhere('uuid', $placingContentUuid))
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('library.place.title') }}</flux:heading>
                <flux:text>{{ __('library.place.help', ['title' => $placingContent?->activeRevision?->title ?? __('ui.content.untitled')]) }}</flux:text>
            </div>
            <form wire:submit="place" class="flex flex-col gap-3 md:flex-row md:items-end">
                <div class="min-w-0 flex-1">
                    <flux:select wire:model="targetContextUuid" :label="__('library.place.target')">
                        <option value="">{{ __('library.place.choose_target') }}</option>
                        @foreach ($targetContexts as $target)
                            @if (! $placingContent || (int) $target->id !== (int) $placingContent->context_id)
                                <option value="{{ $target->uuid }}">{{ $targetContextLabels[$target->uuid] }}</option>
                            @endif
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex gap-2">
                    <flux:button type="button" wire:click="cancelPlacement" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('library.place.action') }}</flux:button>
                </div>
            </form>
            <flux:callout>{{ __('library.place.boundary') }}</flux:callout>
        </flux:card>
    @endif

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($contents as $content)
            @php
                $revision = $content->activeRevision;
                $blueprintModel = $content->blueprintVersion?->blueprint;
                $activePlacements = $content->placements->where('status', \App\Models\ContentPlacement::STATUS_ACTIVE);
                $cover = $revision?->assets?->first(fn ($asset) => $asset->mediaKind() === 'image');
            @endphp
            <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                @if ($cover)
                    <a href="{{ route('contexts.contents.show', [$content->context, $content]) }}" class="block bg-zinc-100 dark:bg-zinc-900">
                        <img src="{{ route('contexts.contents.assets.show', [$content->context, $content, $cover]) }}" alt="{{ $cover->alt_text ?: $cover->original_filename }}" class="h-44 w-full object-cover" loading="lazy" />
                    </a>
                @endif

                <div class="space-y-4 p-5">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            @if ($blueprintModel)
                                <flux:badge size="sm">{{ $blueprintModel->name }}</flux:badge>
                            @endif
                            <span>{{ $sourceContextLabels[$content->context->uuid] ?? __('library.context.unknown') }}</span>
                        </div>
                        <a href="{{ route('contexts.contents.show', [$content->context, $content]) }}" class="block text-lg font-semibold hover:underline" dir="auto">
                            {{ $revision?->title ?: __('ui.content.untitled') }}
                        </a>
                        <x-app.actor-identity :actor="$content->author" size="xs" />
                    </div>

                    @if (! empty($conceptLabels[$content->id]))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($conceptLabels[$content->id] as $label)
                                <flux:badge size="sm">{{ $label }}</flux:badge>
                            @endforeach
                        </div>
                    @endif

                    @if ($activePlacements->isNotEmpty())
                        <div class="space-y-2 rounded-xl bg-zinc-50 p-3 text-sm dark:bg-zinc-900">
                            <div class="font-medium">{{ __('library.presented_in') }}</div>
                            @foreach ($activePlacements as $placement)
                                <div class="flex items-center justify-between gap-2">
                                    <span>{{ $targetContextLabels[$placement->context->uuid] ?? $sourceContextLabels[$placement->context->uuid] ?? __('library.context.unknown') }}</span>
                                    @if (auth()->user()?->can('manageContent', $placement->context))
                                        <button type="button" wire:click="removePlacement('{{ $placement->uuid }}')" class="text-xs underline">{{ __('library.remove') }}</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <flux:button :href="route('contexts.contents.show', [$content->context, $content])" variant="ghost" size="sm">{{ __('library.open') }}</flux:button>
                        @if (in_array($content->id, $placeableContentIds, true))
                            <flux:button wire:click="startPlacement('{{ $content->uuid }}')" variant="ghost" size="sm">{{ __('library.place.short') }}</flux:button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-app.empty-state :title="__('library.none')" :description="__('library.none_help')" />
            </div>
        @endforelse
    </div>

    <flux:callout>{{ __('library.boundary') }}</flux:callout>
</section>
