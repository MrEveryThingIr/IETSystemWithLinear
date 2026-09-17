<section class="mx-auto w-full max-w-6xl space-y-6 pb-12">
    <x-app.page-header :title="__('blocks.title')" :description="$revision->title">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $content])" variant="ghost">{{ __('blocks.back_to_studio') }}</flux:button>
                @if ($content->status === 'published')
                    <flux:button :href="route('groups.spaces.contents.show', [$group, $space, $content])" variant="ghost">{{ __('studio.back_to_reader') }}</flux:button>
                @endif
            </div>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('blocks.mode_title') }}</flux:heading>
            <flux:text>{{ __('blocks.mode_help') }}</flux:text>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            <button type="button" wire:click="useStructuredFields" class="rounded-xl border p-4 text-start {{ $compositionMode === 'fields' ? 'border-[var(--color-accent)] ring-1 ring-[var(--color-accent)]' : 'border-zinc-200 dark:border-zinc-800' }}">
                <div class="font-semibold">{{ __('blocks.fields_mode') }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ __('blocks.fields_mode_help') }}</div>
            </button>
            <button type="button" wire:click="useBlockDocument" class="rounded-xl border p-4 text-start {{ $compositionMode === 'blocks' ? 'border-[var(--color-accent)] ring-1 ring-[var(--color-accent)]' : 'border-zinc-200 dark:border-zinc-800' }}">
                <div class="font-semibold">{{ __('blocks.blocks_mode') }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ __('blocks.blocks_mode_help') }}</div>
            </button>
        </div>
    </flux:card>

    @if ($compositionMode === 'blocks')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="space-y-4">
                @forelse ($blocks as $index => $block)
                    <flux:card wire:key="block-{{ $block['logical_uuid'] }}" class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <flux:badge>{{ __('blocks.type.'.$block['type']) }}</flux:badge>
                                <span class="text-xs text-zinc-500">#{{ $index + 1 }}</span>
                            </div>
                            <div class="flex gap-1">
                                <flux:button wire:click="moveUp({{ $index }})" size="sm" variant="ghost" :disabled="$index === 0">↑</flux:button>
                                <flux:button wire:click="moveDown({{ $index }})" size="sm" variant="ghost" :disabled="$index === count($blocks) - 1">↓</flux:button>
                                <flux:button wire:click="removeBlock({{ $index }})" size="sm" variant="danger">{{ __('blocks.remove') }}</flux:button>
                            </div>
                        </div>

                        @switch($block['type'])
                            @case('paragraph')
                                <flux:textarea wire:model="blocks.{{ $index }}.data.text" :label="__('blocks.text')" rows="5" />
                                @break
                            @case('heading')
                                <div class="grid gap-3 md:grid-cols-[1fr_10rem]">
                                    <flux:input wire:model="blocks.{{ $index }}.data.text" :label="__('blocks.heading_text')" />
                                    <flux:select wire:model="blocks.{{ $index }}.data.level" :label="__('blocks.heading_level')">
                                        <option value="2">H2</option>
                                        <option value="3">H3</option>
                                        <option value="4">H4</option>
                                    </flux:select>
                                </div>
                                @break
                            @case('quote')
                                <flux:textarea wire:model="blocks.{{ $index }}.data.text" :label="__('blocks.quote_text')" rows="4" />
                                <flux:input wire:model="blocks.{{ $index }}.data.attribution" :label="__('blocks.attribution')" />
                                @break
                            @case('list')
                                <flux:textarea wire:model="blocks.{{ $index }}.data.items_text" :label="__('blocks.list_items')" :description="__('blocks.list_items_help')" rows="6" />
                                <flux:checkbox wire:model="blocks.{{ $index }}.data.ordered" :label="__('blocks.ordered')" />
                                @break
                            @case('callout')
                                <flux:textarea wire:model="blocks.{{ $index }}.data.text" :label="__('blocks.callout_text')" rows="4" />
                                <flux:select wire:model="blocks.{{ $index }}.data.tone" :label="__('blocks.tone')">
                                    <option value="info">{{ __('blocks.tone_info') }}</option>
                                    <option value="success">{{ __('blocks.tone_success') }}</option>
                                    <option value="warning">{{ __('blocks.tone_warning') }}</option>
                                    <option value="danger">{{ __('blocks.tone_danger') }}</option>
                                </flux:select>
                                @break
                            @case('field')
                                <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900">
                                    {{ __('blocks.field_reference') }}: <span class="font-medium" dir="auto">{{ $block['data']['label'] ?? $block['data']['field_key'] ?? '—' }}</span>
                                </div>
                                @break
                            @case('image')
                            @case('audio')
                            @case('video')
                            @case('file')
                                <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900" dir="auto">
                                    {{ $block['data']['filename'] ?? $block['data']['asset_placement_uuid'] ?? '—' }}
                                </div>
                                <flux:input wire:model="blocks.{{ $index }}.data.caption" :label="__('media.caption')" />
                                @break
                            @case('divider')
                                <div class="border-t border-zinc-300 dark:border-zinc-700"></div>
                                @break
                        @endswitch

                        <details class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <summary class="cursor-pointer text-sm font-medium">{{ __('blocks.style_this_block') }}</summary>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="space-y-1 text-sm">
                                    <span>{{ __('blocks.text_color') }}</span>
                                    <input type="color" wire:model="blocks.{{ $index }}.style.text_color" class="h-10 w-full rounded border" />
                                </label>
                                <label class="space-y-1 text-sm">
                                    <span>{{ __('blocks.background_color') }}</span>
                                    <input type="color" wire:model="blocks.{{ $index }}.style.background_color" class="h-10 w-full rounded border" />
                                </label>
                                <flux:select wire:model="blocks.{{ $index }}.style.alignment" :label="__('blocks.alignment')">
                                    <option value="">{{ __('blocks.inherit') }}</option>
                                    <option value="start">{{ __('blocks.align_start') }}</option>
                                    <option value="center">{{ __('blocks.align_center') }}</option>
                                    <option value="end">{{ __('blocks.align_end') }}</option>
                                </flux:select>
                                <flux:select wire:model="blocks.{{ $index }}.style.emphasis" :label="__('blocks.emphasis')">
                                    <option value="normal">{{ __('blocks.emphasis_normal') }}</option>
                                    <option value="muted">{{ __('blocks.emphasis_muted') }}</option>
                                    <option value="strong">{{ __('blocks.emphasis_strong') }}</option>
                                    <option value="callout">{{ __('blocks.emphasis_callout') }}</option>
                                </flux:select>
                            </div>
                        </details>
                    </flux:card>
                @empty
                    <x-app.empty-state :title="__('blocks.no_blocks')" :description="__('blocks.no_blocks_help')" />
                @endforelse
            </div>

            <aside class="space-y-4 self-start xl:sticky xl:top-6">
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('blocks.add_block') }}</flux:heading>
                    @foreach (['paragraph', 'heading', 'quote', 'list', 'callout', 'divider'] as $type)
                        <flux:button wire:click="addBlock('{{ $type }}')" class="w-full" variant="ghost">+ {{ __('blocks.type.'.$type) }}</flux:button>
                    @endforeach
                </flux:card>

                <flux:card class="space-y-3">
                    <flux:heading>{{ __('blocks.add_field') }}</flux:heading>
                    @foreach ($fields as $field)
                        <flux:button wire:click="addFieldBlock(@js($field['key']))" class="w-full" variant="ghost">
                            <span dir="auto">+ {{ $field['label'] }}</span>
                        </flux:button>
                    @endforeach
                </flux:card>

                @if ($media->isNotEmpty())
                    <flux:card class="space-y-3">
                        <flux:heading>{{ __('blocks.add_media') }}</flux:heading>
                        @foreach ($media as $item)
                            @php
                                $mediaType = str_starts_with($item->mime_type, 'image/') ? 'image' : (str_starts_with($item->mime_type, 'audio/') ? 'audio' : (str_starts_with($item->mime_type, 'video/') ? 'video' : 'file'));
                            @endphp
                            <flux:button wire:click="addMediaBlock('{{ $item->placement_uuid }}', '{{ $mediaType }}')" class="w-full" variant="ghost">
                                <span class="truncate" dir="auto">+ {{ $item->caption ?: $item->original_filename }}</span>
                            </flux:button>
                        @endforeach
                    </flux:card>
                @endif
            </aside>
        </div>
    @else
        <flux:callout>{{ __('blocks.fields_active_help') }}</flux:callout>
    @endif

    <div class="sticky bottom-4 flex justify-end">
        <flux:button wire:click="save" wire:loading.attr="disabled" wire:target="save" variant="primary">
            {{ __('blocks.save') }}
        </flux:button>
    </div>
</section>
