<section class="mx-auto max-w-7xl space-y-5" data-content-workspace>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('content_workspace.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('content_workspace.help') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('content.library')" variant="ghost" icon="magnifying-glass">{{ __('content_workspace.browse_library') }}</flux:button>
            <flux:button wire:click="$dispatch('open-content-create')" variant="primary" icon="plus" data-content-create>{{ __('content_workspace.create_button') }}</flux:button>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('content_workspace.search_placeholder')" :aria-label="__('content_workspace.search_placeholder')" />
        </div>
        <div class="sm:w-72">
            <flux:select wire:model.live="place" :aria-label="__('content_workspace.place_filter')">
                <option value="">{{ __('content_workspace.all_places') }}</option>
                @foreach ($placeOptions as $uuid => $label)
                    <option value="{{ $uuid }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($placeContext)
        <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
            <span class="font-medium" dir="auto">{{ $placeOptions[$placeContext->uuid] }}</span>
            <span>·</span>
            <span>{{ $placeAudience }}</span>
            <a href="{{ route('contexts.contents.index', $placeContext) }}" class="ms-auto text-xs underline-offset-4 hover:underline">{{ __('content_workspace.place_tools') }}</a>
        </div>
    @endif

    <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="{{ __('content_workspace.views_label') }}">
        @foreach (['all', 'drafts', 'published', 'shared', 'learning'] as $tab)
            <button
                type="button"
                wire:click="setView('{{ $tab }}')"
                @class([
                    'inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $view === $tab,
                    'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $view !== $tab,
                ])
                aria-pressed="{{ $view === $tab ? 'true' : 'false' }}"
                data-content-view="{{ $tab }}"
            >
                {{ __('content_workspace.views.'.$tab) }}
                <span class="text-xs opacity-70">{{ $counts[$tab] }}</span>
            </button>
        @endforeach
    </nav>

    @if ($items->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
            @if ($search !== '' || $place !== '' || $view !== 'all')
                <p class="font-medium">{{ __('content_workspace.empty_filtered') }}</p>
                <flux:button wire:click="clearFilters" variant="ghost" class="mt-3">{{ __('content_workspace.clear_filters') }}</flux:button>
            @else
                <p class="font-medium">{{ __('content_workspace.empty_title') }}</p>
                <p class="mt-1 text-sm text-zinc-500">{{ __('content_workspace.empty_help') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    @foreach (array_keys(\App\Support\ContentKinds::KINDS) as $kind)
                        <flux:button wire:click="$dispatch('open-content-create', { kind: '{{ $kind }}' })" size="sm" variant="ghost">+ {{ __('content_workspace.kinds.'.$kind.'.name') }}</flux:button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-content-cards>
            @foreach ($items as $item)
                <article wire:key="content-card-{{ $item['uuid'] }}" class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-content-card>
                    <a href="{{ $item['url'] }}" class="block">
                        @if ($item['cover_url'])
                            <img src="{{ $item['cover_url'] }}" alt="" class="h-36 w-full object-cover" loading="lazy" />
                        @else
                            <div class="flex h-20 items-end bg-gradient-to-br from-zinc-100 to-zinc-50 px-4 pb-2 dark:from-zinc-800 dark:to-zinc-900">
                                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $item['type'] }}</span>
                            </div>
                        @endif
                    </a>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @php
                                $stateColor = match ($item['state']) {
                                    'published' => 'green',
                                    'published_changes' => 'amber',
                                    'archived' => 'zinc',
                                    default => 'blue',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$stateColor">{{ __('content_workspace.state.'.$item['state']) }}</flux:badge>
                            @if ($item['is_public'] ?? false)
                                <flux:badge size="sm" color="green" icon="globe-alt" data-workspace-public>{{ __('public_content.workspace_badge') }}</flux:badge>
                            @endif
                            @if ($item['cover_url'])
                                <span class="text-zinc-500">{{ $item['type'] }}</span>
                            @endif
                        </div>
                        <a href="{{ $item['url'] }}" class="line-clamp-2 font-semibold leading-snug hover:underline" dir="auto">{{ $item['title'] }}</a>
                        <div class="mt-auto space-y-1 text-xs text-zinc-500">
                            @if ($item['place'])
                                <div class="truncate" dir="auto">{{ $item['place'] }}</div>
                            @endif
                            <div class="flex flex-wrap items-center gap-1">
                                @if ($item['author'])
                                    <span dir="auto">{{ $item['author']->profile?->display_name ?: $item['author']->user?->username }}</span>
                                    <span>·</span>
                                @endif
                                <span>{{ __('content_workspace.edited') }}</span>
                                <x-app.local-date :value="$item['updated_at']" />
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <flux:button :href="$item['url']" size="sm" :variant="$item['can_edit'] ? 'primary' : 'ghost'">
                                {{ $item['can_edit'] ? __('content_workspace.open_editor') : __('content_workspace.read') }}
                            </flux:button>
                            @if ($item['can_edit'] && $item['read_url'])
                                <flux:button :href="$item['read_url']" size="sm" variant="ghost">{{ __('content_workspace.read') }}</flux:button>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($counts['archived'] > 0 && $view !== 'archived')
        <div class="text-center">
            <button type="button" wire:click="setView('archived')" class="text-sm text-zinc-500 underline-offset-4 hover:underline">{{ __('content_workspace.show_archived', ['count' => $counts['archived']]) }}</button>
        </div>
    @elseif ($view === 'archived')
        <div class="text-center">
            <button type="button" wire:click="setView('all')" class="text-sm text-zinc-500 underline-offset-4 hover:underline">{{ __('content_workspace.back_to_all') }}</button>
        </div>
    @endif

    <livewire:content.create-content />
</section>
