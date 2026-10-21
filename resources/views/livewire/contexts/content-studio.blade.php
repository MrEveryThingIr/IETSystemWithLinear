<section class="mx-auto max-w-7xl" data-content-studio>
    @php
        $isArchived = $content->status === 'archived';
        $stateKey = match (true) {
            $isArchived => 'archived',
            $content->status === 'published' && $content->draft_revision_id !== null => 'published_changes',
            $content->status === 'published' => 'published',
            default => 'draft',
        };
        $stateColor = match ($stateKey) {
            'published' => 'green',
            'published_changes' => 'amber',
            'archived' => 'zinc',
            default => 'blue',
        };
        $isBlocks = $compositionMode === \App\Models\SpaceContentRevision::COMPOSITION_BLOCKS;
        $assetUrl = fn ($asset) => route('contexts.contents.assets.show', [$context, $content, $asset]);
        $assetDownloadUrl = fn ($asset) => route('contexts.contents.assets.download', [$context, $content, $asset]);
    @endphp

    {{-- Top bar: title, where it lives, and the few actions an author needs. --}}
    <header class="sticky top-0 z-20 -mx-4 mb-5 border-b border-zinc-200 bg-zinc-50/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 dark:border-zinc-800 dark:bg-zinc-950/95" data-studio-topbar>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('experience.content') }}" wire:navigate class="inline-flex shrink-0 items-center text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200" aria-label="{{ __('studio_ui.back_to_content') }}">
                <flux:icon name="arrow-left" class="size-5 rtl:rotate-180" />
            </a>

            <div class="min-w-0 flex-1">
                @if ($canUpdate)
                    <input
                        type="text"
                        wire:model.live.debounce.500ms="title"
                        maxlength="255"
                        dir="auto"
                        class="w-full truncate border-0 bg-transparent p-0 text-lg font-semibold focus:ring-0 sm:text-xl"
                        aria-label="{{ __('ui.content.title') }}"
                        placeholder="{{ __('studio_ui.title_placeholder') }}"
                        data-studio-title
                    />
                @else
                    <h1 class="truncate text-lg font-semibold sm:text-xl" dir="auto">{{ $currentRevision->title }}</h1>
                @endif
                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                    <flux:badge size="sm" :color="$stateColor">{{ __('content_workspace.state.'.$stateKey) }}</flux:badge>
                    <span>{{ $kindLabel }}</span>
                    <span aria-hidden="true">·</span>
                    <span dir="auto" data-studio-place>{{ $placeLabel }}</span>
                    <span class="hidden sm:inline" aria-hidden="true">·</span>
                    <span class="hidden sm:inline">{{ $content->status === 'published' ? $placeAudience : __('studio_ui.draft_audience') }}</span>
                    @if ($dirty)
                        <span class="font-medium text-amber-600 dark:text-amber-400" data-studio-unsaved>● {{ __('studio_ui.unsaved') }}</span>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($canUpdate)
                    <flux:button wire:click="saveRevision" size="sm" :variant="$dirty ? 'filled' : 'ghost'" :disabled="! $dirty" wire:loading.attr="disabled" wire:target="saveRevision" data-studio-save>
                        <span wire:loading.remove wire:target="saveRevision">{{ $dirty ? __('studio_ui.save') : __('studio_ui.saved') }}</span>
                        <span wire:loading wire:target="saveRevision">{{ __('studio_ui.saving') }}</span>
                    </flux:button>
                    <flux:button wire:click="preview" size="sm" variant="ghost" icon="eye" class="hidden sm:inline-flex">{{ __('studio_ui.preview') }}</flux:button>
                    <flux:button x-on:click="$dispatch('open-drawer', 'ai')" size="sm" variant="ghost" icon="sparkles" data-studio-ask-ai>{{ __('studio_ui.ask_ai') }}</flux:button>
                @elseif ($canOpenReader)
                    <flux:button :href="route('contexts.contents.show', [$context, $content])" size="sm" variant="ghost">{{ __('ui.context_content.open_reader') }}</flux:button>
                @endif

                @if ($canPublish)
                    <flux:button wire:click="openPublishPanel" size="sm" variant="primary" data-studio-publish>{{ __('ui.content.publish') }}</flux:button>
                @endif

                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('studio_ui.more')" data-studio-more />
                    <flux:menu class="min-w-56">
                        @if ($canUpdate)
                            <flux:menu.item icon="eye" wire:click="preview" class="sm:hidden">{{ __('studio_ui.preview') }}</flux:menu.item>
                            <flux:menu.item icon="list-bullet" x-on:click="$dispatch('open-drawer', 'outline')" class="2xl:hidden">{{ __('studio_ui.outline') }}</flux:menu.item>
                            <flux:menu.item icon="swatch" x-on:click="$dispatch('open-drawer', 'appearance')">{{ __('studio_ui.appearance') }}</flux:menu.item>
                            @if ($hasOutline)
                                <flux:menu.item icon="queue-list" x-on:click="$dispatch('open-drawer', 'structure')">{{ __('studio_ui.structure') }}</flux:menu.item>
                            @endif
                        @endif
                        @if ($canOpenReader)
                            <flux:menu.item icon="book-open" :href="route('contexts.contents.show', [$context, $content])">{{ __('ui.context_content.open_reader') }}</flux:menu.item>
                        @endif
                        @if ($canShareOutside)
                            <flux:menu.item icon="share" x-on:click="$dispatch('open-drawer', 'share')" data-studio-share>{{ __('public_content.share.button') }}</flux:menu.item>
                        @endif
                        @if ($canViewRevisions)
                            <flux:menu.item icon="clock" x-on:click="$dispatch('open-drawer', 'history')">{{ __('studio_ui.version_history') }}</flux:menu.item>
                        @endif
                        @if ($canUpdate && ! $dirty)
                            <flux:menu.item icon="pencil-square" wire:click="openRename('{{ $content->uuid }}')" data-studio-rename>{{ __('studio.rename.title') }}</flux:menu.item>
                        @endif
                        @if ($canArchive || $canRestore || $canDelete)
                            <flux:menu.separator />
                            @if ($canArchive)
                                <flux:menu.item icon="archive-box" wire:click="beginArchive" variant="danger">{{ __('studio.archive') }}</flux:menu.item>
                            @endif
                            @if ($canRestore)
                                <flux:menu.item icon="arrow-uturn-left" x-on:click="$dispatch('open-drawer', 'restore')">{{ __('studio.restore') }}</flux:menu.item>
                            @endif
                            @if ($canDelete)
                                <flux:menu.item icon="trash" wire:click="openDelete('{{ $content->uuid }}')" variant="danger" data-studio-delete>{{ __('studio.delete.title') }}</flux:menu.item>
                            @endif
                        @endif
                        @if ($canUpdate)
                            <flux:menu.separator />
                            <flux:menu.item icon="squares-2x2" :href="route('contexts.contents.blocks', [$context, $content])">{{ __('studio_ui.advanced_layout') }}</flux:menu.item>
                            <flux:menu.item icon="rectangle-stack" :href="route('contexts.contents.index', $context)">{{ __('studio_ui.templates_and_schemas') }}</flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
    </header>

    <x-app.flash-message />

    @error('studio')
        <flux:callout variant="danger" class="mb-4" data-studio-error>{{ $message }}</flux:callout>
    @enderror
    @if ($errors->has('title') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'payload.')))
        <flux:callout variant="danger" class="mb-4">{{ $errors->first('title') ?: collect($errors->getMessages())->first(fn ($messages, $key) => str_starts_with($key, 'payload.'))[0] }}</flux:callout>
    @endif

    @if ($draftChangedElsewhere)
        <flux:callout variant="warning" class="mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('studio_ui.changed_elsewhere') }}</span>
                <flux:button wire:click="discardAndReload" size="sm">{{ __('studio_ui.load_latest') }}</flux:button>
            </div>
        </flux:callout>
    @endif

    @if ($isArchived)
        <flux:callout class="mb-4">{{ __('studio_ui.archived_notice') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_16rem] 2xl:grid-cols-[12rem_minmax(0,1fr)_17rem]">
        {{-- Outline rail (wide screens; a drawer elsewhere) --}}
        <nav class="hidden self-start 2xl:sticky 2xl:top-24 2xl:block" aria-label="{{ __('studio_ui.outline') }}" data-studio-outline>
            @include('livewire.contexts.partials.studio-outline')
        </nav>

        {{-- Canvas --}}
        <div class="min-w-0 space-y-4" data-studio-canvas>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                @if (! $canUpdate)
                    <div class="space-y-4">
                        @foreach ($fields as $field)
                            @php
                                $value = $currentRevision->payload[$field['key']] ?? null;
                                if ($field['type'] === 'boolean') {
                                    $value = $value ? __('ui.content.yes') : __('ui.content.no');
                                } elseif ($field['type'] === 'select' && $value !== null) {
                                    $value = collect($field['options'])->firstWhere('value', $value)['label'] ?? $value;
                                } elseif ($value === null || $value === '') {
                                    $value = '—';
                                }
                            @endphp
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500" dir="auto">{{ $field['label'] }}</div>
                                <div class="mt-1 whitespace-pre-wrap break-words text-sm" dir="auto">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                @elseif (! $isBlocks)
                    <div class="space-y-5">
                        @foreach ($fields as $key => $field)
                            <div id="field-{{ $key }}" class="scroll-mt-28">
                                @include('livewire.contexts.partials.studio-field', ['field' => $field])
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="space-y-3" data-studio-blocks>
                        @foreach ($blocks as $index => $block)
                            <div id="block-{{ $block['logical_uuid'] }}" wire:key="studio-block-{{ $block['logical_uuid'] }}" class="group relative scroll-mt-28 rounded-xl border border-transparent p-2 hover:border-zinc-200 focus-within:border-zinc-200 dark:hover:border-zinc-800 dark:focus-within:border-zinc-800" data-studio-block="{{ $block['type'] }}">
                                <div class="mb-1 flex items-center justify-between gap-2 opacity-60 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                    @php
                                        $sectionRole = (string) ($block['data']['semantics']['role'] ?? '');
                                    @endphp
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="text-[0.7rem] font-medium uppercase tracking-wide text-zinc-400">{{ __('blocks.type.'.$block['type']) }}</span>
                                        @if (\App\Support\Content\SectionSemantics::supports($block['type']))
                                            <flux:dropdown position="bottom" align="start">
                                                @if ($sectionRole !== '')
                                                    <flux:badge as="button" size="sm" color="violet" :icon="\App\Support\Content\SectionSemantics::ROLES[$sectionRole]['icon'] ?? null" data-studio-section-role="{{ $sectionRole }}">{{ __('sections.roles.'.$sectionRole.'.name') }}</flux:badge>
                                                @else
                                                    <button type="button" class="rounded-full border border-dashed border-zinc-300 px-2 py-0.5 text-[0.7rem] text-zinc-500 hover:border-zinc-400 hover:text-zinc-700 dark:border-zinc-700" data-studio-section-role-add>+ {{ __('sections.set_role') }}</button>
                                                @endif
                                                <flux:menu class="min-w-64">
                                                    @foreach (\App\Support\Content\SectionSemantics::ROLES as $roleKey => $roleConfig)
                                                        <flux:menu.item :icon="$roleConfig['icon']" wire:click="setSectionRole({{ $index }}, '{{ $roleKey }}')">
                                                            <div>
                                                                <div>{{ __('sections.roles.'.$roleKey.'.name') }}</div>
                                                                <div class="text-xs font-normal text-zinc-500">{{ __('sections.roles.'.$roleKey.'.help') }}</div>
                                                            </div>
                                                        </flux:menu.item>
                                                    @endforeach
                                                    @if ($sectionRole !== '')
                                                        <flux:menu.separator />
                                                        <flux:menu.item icon="x-mark" wire:click="setSectionRole({{ $index }}, '')">{{ __('sections.clear_role') }}</flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    </div>
                                    <div class="flex gap-0.5">
                                        <flux:button wire:click="moveUp({{ $index }})" size="xs" variant="ghost" icon="chevron-up" :disabled="$index === 0" :aria-label="__('structure.move_up')" />
                                        <flux:button wire:click="moveDown({{ $index }})" size="xs" variant="ghost" icon="chevron-down" :disabled="$index === count($blocks) - 1" :aria-label="__('structure.move_down')" />
                                        <flux:button wire:click="removeBlock({{ $index }})" size="xs" variant="ghost" icon="trash" :aria-label="__('blocks.remove')" />
                                    </div>
                                </div>

                                @switch($block['type'])
                                    @case('paragraph')
                                        <flux:textarea wire:model.live.debounce.500ms="blocks.{{ $index }}.data.text" dir="auto" rows="4" :placeholder="__('studio_ui.paragraph_placeholder')" :aria-label="__('blocks.text')" />
                                        @break
                                    @case('heading')
                                        <div class="flex gap-2">
                                            <div class="min-w-0 flex-1">
                                                <flux:input wire:model.live.debounce.500ms="blocks.{{ $index }}.data.text" dir="auto" :placeholder="__('blocks.heading_text')" :aria-label="__('blocks.heading_text')" class="font-semibold" />
                                            </div>
                                            <div class="w-20">
                                                <flux:select wire:model.live="blocks.{{ $index }}.data.level" :aria-label="__('blocks.heading_level')">
                                                    <option value="2">H2</option>
                                                    <option value="3">H3</option>
                                                    <option value="4">H4</option>
                                                </flux:select>
                                            </div>
                                        </div>
                                        @break
                                    @case('quote')
                                        <flux:textarea wire:model.live.debounce.500ms="blocks.{{ $index }}.data.text" dir="auto" rows="3" :aria-label="__('blocks.quote_text')" :placeholder="__('blocks.quote_text')" />
                                        <flux:input wire:model.live.debounce.500ms="blocks.{{ $index }}.data.attribution" dir="auto" :placeholder="__('blocks.attribution')" class="mt-2" :aria-label="__('blocks.attribution')" />
                                        @break
                                    @case('list')
                                        <flux:textarea wire:model.live.debounce.500ms="blocks.{{ $index }}.data.items_text" dir="auto" rows="4" :description="__('blocks.list_items_help')" :aria-label="__('blocks.list_items')" />
                                        <flux:checkbox wire:model.live="blocks.{{ $index }}.data.ordered" :label="__('blocks.ordered')" class="mt-2" />
                                        @break
                                    @case('callout')
                                        <flux:textarea wire:model.live.debounce.500ms="blocks.{{ $index }}.data.text" dir="auto" rows="3" :aria-label="__('blocks.callout_text')" :placeholder="__('blocks.callout_text')" />
                                        <div class="mt-2 w-40">
                                            <flux:select wire:model.live="blocks.{{ $index }}.data.tone" :aria-label="__('blocks.tone')">
                                                <option value="info">{{ __('blocks.tone_info') }}</option>
                                                <option value="success">{{ __('blocks.tone_success') }}</option>
                                                <option value="warning">{{ __('blocks.tone_warning') }}</option>
                                                <option value="danger">{{ __('blocks.tone_danger') }}</option>
                                            </flux:select>
                                        </div>
                                        @break
                                    @case('divider')
                                        <hr class="my-3 border-zinc-300 dark:border-zinc-700" />
                                        @break
                                    @case('field')
                                        @php
                                            $blockField = $fields->get($block['data']['field_key'] ?? '');
                                        @endphp
                                        @if ($blockField)
                                            @include('livewire.contexts.partials.studio-field', ['field' => $blockField])
                                        @else
                                            <div class="text-sm text-zinc-500">{{ __('studio_ui.missing_field') }}</div>
                                        @endif
                                        @break
                                    @case('image')
                                    @case('audio')
                                    @case('video')
                                    @case('file')
                                        @php
                                            $blockAsset = $mediaByUuid->get($block['data']['asset_uuid'] ?? '');
                                        @endphp
                                        @if ($blockAsset)
                                            <x-media.asset-preview :asset="$blockAsset" :inline-url="$assetUrl($blockAsset)" :download-url="$assetDownloadUrl($blockAsset)" :poster="$mediaExtras[$blockAsset->id]['poster'] ?? null" :tracks="$mediaExtras[$blockAsset->id]['tracks'] ?? []" :duration-ms="$mediaExtras[$blockAsset->id]['duration_ms'] ?? null" />
                                        @else
                                            <div class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-500 dark:bg-zinc-800" dir="auto">{{ $block['data']['filename'] ?? __('studio_ui.missing_media') }}</div>
                                        @endif
                                        <flux:input wire:model.live.debounce.500ms="blocks.{{ $index }}.data.caption" dir="auto" :placeholder="__('media.caption')" class="mt-2" :aria-label="__('media.caption')" />
                                        @break
                                @endswitch

                                @if (($block['data']['semantics']['role'] ?? '') !== '')
                                    <div class="mt-2 flex items-center gap-2">
                                        <flux:icon name="tag" class="size-4 shrink-0 text-zinc-400" />
                                        <input type="text" wire:model.live.debounce.500ms="blocks.{{ $index }}.data.semantics.tags_text" dir="auto" maxlength="400" placeholder="{{ __('sections.tags_placeholder') }}" aria-label="{{ __('sections.tags') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-transparent px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:border-zinc-700" data-studio-section-tags />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($canUpdate)
                    {{-- Insert bar: write or add media directly into the document. --}}
                    <div class="mt-5 flex flex-wrap items-center gap-1 border-t border-zinc-100 pt-4 dark:border-zinc-800" data-studio-insert>
                        <span class="me-1 text-xs font-medium text-zinc-500">{{ __('studio_ui.insert') }}</span>
                        <flux:button wire:click="addBlock('paragraph')" size="sm" variant="ghost" icon="bars-3-bottom-left">{{ __('studio_ui.insert_text') }}</flux:button>
                        <flux:button wire:click="addBlock('heading')" size="sm" variant="ghost" icon="hashtag">{{ __('studio_ui.insert_heading') }}</flux:button>
                        <flux:button wire:click="openMediaDialog('image')" size="sm" variant="ghost" icon="photo">{{ __('studio_ui.insert_image') }}</flux:button>
                        <flux:button wire:click="openMediaDialog('video')" size="sm" variant="ghost" icon="film">{{ __('studio_ui.insert_video') }}</flux:button>
                        <flux:button wire:click="openMediaDialog('audio')" size="sm" variant="ghost" icon="musical-note">{{ __('studio_ui.insert_audio') }}</flux:button>
                        <flux:button wire:click="openMediaDialog('file')" size="sm" variant="ghost" icon="paper-clip">{{ __('studio_ui.insert_file') }}</flux:button>
                        <flux:dropdown position="bottom" align="start">
                            <flux:button size="sm" variant="ghost" icon="plus">{{ __('studio_ui.insert_more') }}</flux:button>
                            <flux:menu>
                                @foreach (['list', 'quote', 'callout', 'divider'] as $more)
                                    <flux:menu.item wire:click="addBlock('{{ $more }}')">{{ __('blocks.type.'.$more) }}</flux:menu.item>
                                @endforeach
                                @if ($isBlocks)
                                    @php
                                        $placedFields = collect($blocks)->where('type', 'field')->pluck('data.field_key')->all();
                                    @endphp
                                    @foreach ($fields as $key => $field)
                                        @unless (in_array($key, $placedFields, true))
                                            <flux:menu.item wire:click="addFieldBlock(@js($key))"><span dir="auto">{{ $field['label'] }}</span></flux:menu.item>
                                        @endunless
                                    @endforeach
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @endif
            </div>

            @if ($canUpdate && $isBlocks)
                <div class="text-end">
                    <button type="button" wire:click="useSimpleDocument" wire:confirm="{{ __('studio_ui.simple_document_confirm') }}" class="text-xs text-zinc-500 underline-offset-4 hover:underline">{{ __('studio_ui.simple_document') }}</button>
                </div>
            @endif
        </div>

        {{-- Inspector --}}
        <aside class="space-y-4 self-start lg:sticky lg:top-24" data-studio-inspector>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('studio_ui.details') }}</div>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('studio_ui.where') }}</dt>
                        <dd dir="auto">{{ $placeLabel }}</dd>
                        <dd class="text-xs text-zinc-500">{{ $placeAudience }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('studio_ui.type') }}</dt>
                        <dd>{{ $kindLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-zinc-500">{{ __('studio_ui.version') }}</dt>
                        <dd>{{ __('ui.content.revision_number', ['revision' => $currentRevision->revision]) }}</dd>
                    </div>
                </dl>
                @if ($canUpdate)
                    <flux:button x-on:click="$dispatch('open-drawer', 'appearance')" size="sm" variant="ghost" icon="swatch" class="mt-3 w-full">{{ __('studio_ui.appearance') }}</flux:button>
                @endif
            </div>

            <div id="studio-media" class="scroll-mt-28 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" data-studio-media-library>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('studio.media') }}</div>
                    @if ($canUpdate)
                        <flux:button wire:click="openMediaDialog('')" size="xs" variant="ghost" icon="plus">{{ __('studio_ui.add_media') }}</flux:button>
                    @endif
                </div>

                <div class="space-y-3">
                    @forelse ($mediaAssets as $asset)
                        @php
                            $issue = $publicationIssues->firstWhere('asset_id', $asset->id);
                            $canRetry = in_array($asset->scan_status, ['failed', 'quarantined'], true)
                                || in_array($asset->processing_status, ['failed', 'pending'], true);
                            $placedInDocument = $isBlocks && collect($blocks)->contains(fn ($block) => ($block['data']['asset_uuid'] ?? null) === $asset->uuid);
                        @endphp
                        <div wire:key="studio-media-{{ $asset->uuid }}" class="space-y-2 rounded-xl border border-zinc-200 p-2 dark:border-zinc-800">
                            <x-media.asset-preview :asset="$asset" :inline-url="$assetUrl($asset)" :download-url="$assetDownloadUrl($asset)" :poster="$mediaExtras[$asset->id]['poster'] ?? null" compact />
                            <div class="truncate text-xs font-medium" dir="auto" title="{{ $asset->original_filename }}">{{ $asset->original_filename }}</div>
                            @if (isset($mediaExtras[$asset->id]))
                                <div class="flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                                    @if ($mediaExtras[$asset->id]['duration_ms'])
                                        <span>{{ gmdate($mediaExtras[$asset->id]['duration_ms'] >= 3600000 ? 'G:i:s' : 'i:s', intdiv($mediaExtras[$asset->id]['duration_ms'], 1000)) }}</span>
                                    @endif
                                    <span>{{ trans_choice('studio_ui.captions.count', count($mediaExtras[$asset->id]['tracks']), ['count' => count($mediaExtras[$asset->id]['tracks'])]) }}</span>
                                </div>
                            @endif
                            @if ($issue)
                                <div class="text-xs text-amber-700 dark:text-amber-300">{{ __('studio.issue.'.$issue['code']) }}</div>
                            @endif
                            @if ($canUpdate)
                                <flux:select wire:change="updateAssetRights({{ $asset->id }}, $event.target.value)" size="sm" :aria-label="__('media.rights_status')">
                                    @foreach ($rightsStatuses as $status)
                                        <option value="{{ $status }}" @selected($asset->rights_status === $status)>{{ __('media.rights.'.$status) }}</option>
                                    @endforeach
                                </flux:select>
                                @error('assetRights.'.$asset->id)<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                                <div class="flex flex-wrap gap-1">
                                    @if (isset($mediaExtras[$asset->id]))
                                        <flux:button wire:click="openCaptions({{ $asset->id }})" size="xs" variant="ghost" icon="language" data-studio-captions>{{ __('studio_ui.captions.manage') }}</flux:button>
                                    @endif
                                    @unless ($placedInDocument)
                                        <flux:button wire:click="addExistingMediaBlock('{{ $asset->uuid }}')" size="xs" variant="ghost">{{ __('studio_ui.place_in_document') }}</flux:button>
                                    @endunless
                                    @if ($canRetry && ! app()->environment(['local', 'testing']))
                                        <flux:button wire:click="retryAssetProcessing({{ $asset->id }})" size="xs" variant="ghost">{{ __('studio.media_retry') }}</flux:button>
                                    @endif
                                    <flux:button wire:click="removeAsset({{ $asset->id }})" wire:confirm="{{ __('studio_ui.remove_media_confirm') }}" size="xs" variant="ghost" class="text-red-600">{{ __('media.remove') }}</flux:button>
                                </div>
                            @else
                                <div class="text-xs text-zinc-500">{{ __('media.rights.'.$asset->rights_status) }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">{{ __('media.none') }}</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>

    {{-- Add media --}}
    @if ($canUpdate)
        <x-app.dialog :show="$mediaDialogOpen" close="closeMediaDialog" :title="__('studio_ui.media_dialog.'.($insertKind !== '' ? $insertKind : 'any'))">
            @include('livewire.contexts.partials.studio-upload', ['insertKind' => $insertKind, 'uploadLimits' => $uploadLimits, 'rightsStatuses' => $rightsStatuses, 'resumable' => $resumable])
        </x-app.dialog>
    @endif

    {{-- Captions for a video or audio --}}
    @if ($canUpdate && $captionAsset)
        <x-app.dialog :show="true" close="closeCaptions" :title="__('studio_ui.captions.title')" size="sm">
            <div class="space-y-4" data-studio-captions-dialog>
                <p class="truncate text-sm font-medium" dir="auto">{{ $captionAsset->original_filename }}</p>
                @php
                    $captionTracks = $mediaExtras[$captionAsset->id]['tracks'] ?? [];
                @endphp
                @if ($captionTracks !== [])
                    <ul class="space-y-2 text-sm">
                        @foreach ($captionTracks as $track)
                            <li class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <span dir="auto">{{ $track['label'] }} <span class="text-xs text-zinc-500">({{ $track['language'] }})</span></span>
                                <flux:button wire:click="removeCaptions('{{ $track['uuid'] }}')" wire:confirm="{{ __('studio_ui.captions.remove_confirm') }}" size="xs" variant="ghost" class="text-red-600">{{ __('media.remove') }}</flux:button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <p class="text-xs text-zinc-500">{{ __('studio_ui.captions.help') }}</p>
                    <input type="file" wire:model="captionUpload" accept=".vtt,.srt,text/vtt" class="block w-full text-sm" data-caption-input />
                    @error('captionUpload')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="captionLanguage" :label="__('studio_ui.captions.language')" maxlength="16" dir="ltr" />
                        <flux:input wire:model="captionLabel" :label="__('studio_ui.captions.label')" maxlength="120" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:button x-on:click="open = false; $wire.closeCaptions()" variant="ghost">{{ __('studio.cancel') }}</flux:button>
                        <flux:button wire:click="addCaptions" variant="primary" wire:loading.attr="disabled" wire:target="captionUpload,addCaptions">{{ __('studio_ui.captions.add') }}</flux:button>
                    </div>
                </div>
            </div>
        </x-app.dialog>
    @endif

    {{-- Publish readiness --}}
    @if ($canPublish)
        <x-app.dialog :show="$publishPanelOpen" close="closePublishPanel" :title="__('studio_ui.publish.title')" size="sm">
            <div class="space-y-4" data-studio-publish-panel>
                @if ($publishBlocked)
                    <div>
                        <p class="font-medium">{{ trans_choice('studio_ui.publish.issues', $publicationIssues->count(), ['count' => $publicationIssues->count()]) }}</p>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($publicationIssues as $issue)
                                <li class="rounded-lg bg-amber-50 p-2 dark:bg-amber-950/30">
                                    <span class="font-medium" dir="auto">{{ $issue['filename'] }}</span>
                                    <span class="block text-amber-800 dark:text-amber-200">{{ __('studio.issue.'.$issue['code']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-zinc-500">{{ __('studio_ui.publish.fix_in_media') }}</p>
                    </div>
                    <div class="flex justify-end">
                        <flux:button x-on:click="open = false; $wire.closePublishPanel()" variant="ghost">{{ __('studio_ui.publish.back_to_editing') }}</flux:button>
                    </div>
                @else
                    <p class="text-sm">{{ __('studio_ui.publish.ready', ['audience' => $placeAudience]) }}</p>
                    <p class="text-xs text-zinc-500">{{ __('studio_ui.publish.sealed_note') }}</p>
                    @error('publish')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:button x-on:click="open = false; $wire.closePublishPanel()" variant="ghost">{{ __('studio.cancel') }}</flux:button>
                        <flux:button wire:click="publish" variant="primary" wire:loading.attr="disabled" wire:target="publish" data-studio-confirm-publish>{{ __('ui.content.publish') }}</flux:button>
                    </div>
                @endif
            </div>
        </x-app.dialog>
    @endif

    {{-- Archive confirmation --}}
    @if ($canArchive)
        <x-app.dialog :show="$showArchiveConfirmation" close="cancelArchive" :title="__('studio.archive')" size="sm">
            <div class="space-y-3">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('studio_ui.archive_help') }}</p>
                <flux:textarea wire:model="archiveReason" :label="__('studio.archive_reason')" rows="3" maxlength="1000" />
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:button x-on:click="open = false; $wire.cancelArchive()" variant="ghost">{{ __('studio.cancel') }}</flux:button>
                    <flux:button wire:click="archive" variant="danger">{{ __('studio.confirm_archive') }}</flux:button>
                </div>
            </div>
        </x-app.dialog>
    @endif

    {{-- Secondary tools as drawers; each keeps its own authority and saves. --}}
    @if ($canUpdate)
        <x-app.drawer name="ai" :title="__('ai.title')" width="sm:max-w-xl">
            <livewire:contexts.content-ai-assistant :context="$context" :content="$content" :embedded="true" :key="'studio-ai-'.$content->uuid" />
        </x-app.drawer>
        <x-app.drawer name="appearance" :title="__('studio_ui.appearance')" width="sm:max-w-lg">
            <livewire:contexts.content-appearance :context="$context" :content="$content" :embedded="true" :key="'studio-appearance-'.$content->uuid" />
        </x-app.drawer>
        @if ($hasOutline)
            <x-app.drawer name="structure" :title="__('studio_ui.structure')" width="sm:max-w-2xl">
                <livewire:contexts.content-outline :context="$context" :content="$content" :embedded="true" :key="'studio-outline-'.$content->uuid" />
            </x-app.drawer>
        @endif
    @endif

    <x-app.drawer name="outline" :title="__('studio_ui.outline')" side="start">
        <div x-on:click="if ($event.target.closest('a')) $dispatch('close-drawer', 'outline')">
            @include('livewire.contexts.partials.studio-outline')
        </div>
    </x-app.drawer>

    @if ($canViewRevisions)
        <x-app.drawer name="history" :title="__('studio_ui.version_history')">
            <p class="mb-3 text-xs text-zinc-500">{{ __('ui.content.history_help') }}</p>
            <div class="space-y-2">
                @foreach ($revisions as $revision)
                    <details wire:key="studio-history-{{ $revision->uuid }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <summary class="cursor-pointer text-sm font-medium" dir="auto">
                            {{ __('ui.content.revision_number', ['revision' => $revision->revision]) }} · {{ $revision->title }}
                            @if ((int) $content->active_revision_id === (int) $revision->id)
                                <flux:badge size="sm" color="green" class="ms-1">{{ __('studio_ui.live') }}</flux:badge>
                            @endif
                        </summary>
                        <div class="mt-2 space-y-1 text-xs text-zinc-500">
                            <div class="flex flex-wrap items-center gap-1">
                                <x-app.actor-identity :actor="$revision->createdBy" size="xs" />
                                <span>·</span>
                                <x-app.local-datetime :value="$revision->created_at" />
                            </div>
                            <div class="break-all font-mono text-[11px]">content: {{ $revision->content_hash }}</div>
                            @if ($revision->manifest_hash)
                                <div class="break-all font-mono text-[11px]">manifest v{{ $revision->manifest_version ?? 0 }}: {{ $revision->manifest_hash }}</div>
                            @endif
                            <flux:badge size="sm">{{ $revision->evidence_status }}</flux:badge>
                        </div>
                    </details>
                @endforeach
            </div>

            @if ($lifecycleEvents->isNotEmpty())
                <div class="mt-6 mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('studio.lifecycle') }}</div>
                <div class="space-y-2">
                    @foreach ($lifecycleEvents->take(10) as $event)
                        <div class="rounded-lg border border-zinc-200 p-2 text-xs dark:border-zinc-800">
                            <div class="font-medium">{{ __('studio.event_'.$event->event_type) }}</div>
                            <div class="mt-1 text-zinc-500" dir="auto">{{ $event->reason }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-app.drawer>
    @endif

    @if ($canShareOutside)
        <x-app.drawer name="share" :title="__('public_content.share.title')" width="sm:max-w-lg">
            <livewire:content.share-panel :content="$content" :key="'studio-share-'.$content->uuid" />
        </x-app.drawer>
    @endif

    @if ($canRestore)
        <x-app.drawer name="restore" :title="__('studio.restore')">
            <div class="space-y-3">
                <flux:textarea wire:model="restoreReason" :label="__('studio.restore_reason')" rows="3" maxlength="1000" />
                <flux:button wire:click="restore" variant="primary" class="w-full">{{ __('studio.restore') }}</flux:button>
            </div>
        </x-app.drawer>
    @endif

    @include('livewire.content.partials.lifecycle-dialogs')
</section>
