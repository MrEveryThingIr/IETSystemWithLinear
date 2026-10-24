<div class="min-w-0 space-y-3" data-category-picker>
    @if ($value !== null && ! $open)
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900" data-category-selected>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-1 text-xs text-zinc-500" dir="auto">
                    @foreach (array_slice($selectedPath, 0, -1) as $step)
                        <span class="[overflow-wrap:anywhere]">{{ $step['label'] }}</span><span aria-hidden="true">›</span>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium [overflow-wrap:anywhere]" dir="auto">{{ last($selectedPath)['label'] ?? '' }}</span>
                    @if ($selectedPrivate)
                        <flux:badge size="sm" color="zinc">{{ __('categories.picker.private') }}</flux:badge>
                    @endif
                </div>
            </div>
            <flux:button type="button" size="sm" variant="ghost" wire:click="change" data-category-change>{{ __('categories.picker.change') }}</flux:button>
        </div>
        @if ($status)
            <p class="text-sm text-emerald-700 dark:text-emerald-400" data-category-status>{{ $status }}</p>
        @endif
    @else
        <flux:input
            type="search"
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('categories.picker.search_placeholder')"
            :aria-label="__('categories.picker.search')"
            data-category-search
        />

        @if (! $searching && $browsing !== null)
            <nav class="flex flex-wrap items-center gap-1 text-sm" aria-label="{{ __('categories.picker.path') }}" data-category-path>
                <button type="button" wire:click="browse(null)" class="rounded-md px-2 py-1 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('categories.picker.all_areas') }}</button>
                @foreach ($browsingPath as $step)
                    <span class="text-zinc-400" aria-hidden="true">›</span>
                    <button type="button" wire:click="browse({{ $step['id'] }})" class="rounded-md px-2 py-1 font-medium [overflow-wrap:anywhere] hover:bg-zinc-100 dark:hover:bg-zinc-800" dir="auto">{{ $step['label'] }}</button>
                @endforeach
            </nav>
        @endif

        <div wire:loading.class="opacity-60" wire:target="search,browse" class="transition-opacity">
            @if (! $searching && $browsing === null)
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" data-category-areas>
                    @foreach ($results as $area)
                        <button type="button" wire:click="browse({{ $area['id'] }})" wire:key="area-{{ $area['id'] }}"
                            class="flex min-h-12 items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-start text-sm hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                            data-category-area>
                            <flux:icon :name="$area['icon']" class="size-5 shrink-0 text-zinc-500" />
                            <span class="min-w-0 [overflow-wrap:anywhere]" dir="auto">{{ $area['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            @else
                @if (! $searching && $browsing !== null)
                    <button type="button" wire:click="choose({{ $browsing }})" class="mb-2 flex min-h-11 w-full items-center gap-2 rounded-xl border border-dashed border-zinc-300 px-3 py-2 text-start text-sm hover:border-zinc-500 dark:border-zinc-600" data-category-choose-current>
                        <flux:icon name="check" class="size-4 shrink-0 text-zinc-500" />
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ __('categories.picker.choose_this', ['name' => last($browsingPath)['label'] ?? '']) }}</span>
                    </button>
                @endif
                <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-700 dark:bg-zinc-900" data-category-results>
                    @forelse ($results as $item)
                        <li class="flex items-stretch" wire:key="cat-{{ $item['id'] }}">
                            <button type="button" wire:click="choose({{ $item['id'] }})" class="flex min-h-11 min-w-0 flex-1 flex-col justify-center px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800" data-category-option="{{ $item['id'] }}">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium [overflow-wrap:anywhere]" dir="auto">{{ $item['label'] }}</span>
                                    @if ($item['private'])
                                        <flux:badge size="sm" color="zinc">{{ __('categories.picker.private') }}</flux:badge>
                                    @endif
                                </span>
                                @if (! empty($item['path']))
                                    <span class="text-xs text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ $item['path'] }}</span>
                                @endif
                            </button>
                            @if ($item['has_children'] || ($allowCreate && ! $searching))
                                <button type="button" wire:click="browse({{ $item['id'] }})" class="flex w-11 shrink-0 items-center justify-center border-s border-zinc-100 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800" aria-label="{{ __('categories.picker.open', ['name' => $item['label']]) }}" data-category-open="{{ $item['id'] }}">
                                    <flux:icon name="chevron-right" class="size-4 rtl:rotate-180" />
                                </button>
                            @endif
                        </li>
                    @empty
                        <li class="px-3 py-3 text-sm text-zinc-500">{{ $searching ? __('categories.picker.no_results') : __('categories.picker.empty') }}</li>
                    @endforelse
                </ul>
            @endif
        </div>

        @if ($canCreate)
            <div class="space-y-2 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50" data-category-create>
                <flux:input
                    wire:model="newCategory"
                    maxlength="120"
                    :label="__('categories.picker.add_own', ['name' => last($browsingPath)['label'] ?? ''])"
                    :description="__('categories.picker.add_own_help')"
                    data-category-new
                />
                <flux:checkbox wire:model.live="suggest" :label="__('categories.picker.suggest')" :description="__('categories.picker.suggest_help')" data-category-suggest />
                @if ($suggest)
                    <flux:input wire:model="suggestNote" maxlength="500" :label="__('categories.picker.suggest_note')" />
                @endif
                <div class="flex justify-end">
                    <flux:button type="button" size="sm" variant="primary" wire:click="addCategory" wire:loading.attr="disabled" wire:target="addCategory" data-category-add>{{ __('categories.picker.add') }}</flux:button>
                </div>
            </div>
        @endif

        @if ($value !== null)
            <div class="flex justify-end">
                <flux:button type="button" size="sm" variant="ghost" wire:click="$set('open', false)">{{ __('categories.picker.keep') }}</flux:button>
            </div>
        @endif
    @endif
</div>
