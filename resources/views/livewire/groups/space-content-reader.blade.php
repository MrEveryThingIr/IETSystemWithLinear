@php
    $presentation = is_array($revision->presentation) ? $revision->presentation : [];
    $readerBackground = $presentation['background'] ?? '#fafafa';
    $readerSurface = $presentation['surface'] ?? '#ffffff';
    $readerText = $presentation['text'] ?? '#18181b';
    $readerMuted = $presentation['muted'] ?? '#71717a';
    $readerAccent = $presentation['accent'] ?? '#2563eb';
    $readerBorder = $presentation['border'] ?? '#e4e4e7';
    $contentWidthClass = match ($presentation['content_width'] ?? 'reading') {
        'narrow' => 'max-w-2xl',
        'wide' => 'max-w-5xl',
        'full' => 'max-w-none',
        default => 'max-w-3xl',
    };
    $fontClass = match ($presentation['font_scale'] ?? 'comfortable') {
        'compact' => 'text-[0.98rem] leading-7',
        'large' => 'text-[1.12rem] leading-9',
        default => 'text-[1.05rem] leading-8',
    };
    $radiusClass = match ($presentation['radius'] ?? 'rounded') {
        'none' => 'rounded-none',
        'soft' => 'rounded-lg',
        default => 'rounded-2xl',
    };
    $headingClass = match ($presentation['heading_style'] ?? 'plain') {
        'serif' => 'font-serif tracking-tight',
        'display' => 'font-black tracking-tight',
        default => 'font-semibold tracking-tight',
    };
    $mediaStyle = $presentation['media_style'] ?? 'card';
    $fieldStyles = is_array($presentation['field_styles'] ?? null) ? $presentation['field_styles'] : [];
    $blocks = $revision->composition_mode === \App\Models\SpaceContentRevision::COMPOSITION_BLOCKS
        ? $revision->blocks()->get()
        : collect();
    $mediaByPlacement = $revision->assets->keyBy(fn ($asset) => (string) $asset->pivot->uuid);
    $relationships = $revision->containedRelationships()->with(['childContent.activeRevision'])->get();
@endphp

<section
    class="min-h-screen space-y-6 px-1 pb-12"
    style="--content-bg: {{ $readerBackground }}; --content-surface: {{ $readerSurface }}; --content-text: {{ $readerText }}; --content-muted: {{ $readerMuted }}; --content-accent: {{ $readerAccent }}; --content-border: {{ $readerBorder }}; background: var(--content-bg); color: var(--content-text);"
>
    <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 pt-4">
        <flux:button :href="route('groups.spaces.contents.index', [$group, $space])" variant="ghost" size="sm">
            ← {{ __('reader.back_to_library') }}
        </flux:button>

        <div class="flex flex-wrap gap-2">
            @if ($canInteract)
                <flux:button wire:click="openRevisionAnnotation" variant="ghost" size="sm">
                    + {{ __('interactions.annotate_edition') }}
                </flux:button>
            @endif
            @if ($canEnterStudio)
                <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $content])" variant="primary" size="sm">
                    {{ __('reader.edit_in_studio') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($legacyEvidence)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="warning">
                <div class="font-medium">{{ __('reader.legacy_title') }}</div>
                <div class="mt-1 text-sm">{{ __('reader.legacy_help') }}</div>
            </flux:callout>
        </div>
    @endif

    @if (session('interaction-status'))
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="success">{{ session('interaction-status') }}</flux:callout>
        </div>
    @endif

    <div class="mx-auto grid w-full max-w-7xl gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <article class="min-w-0">
            <header class="mx-auto {{ $contentWidthClass }} border-b pb-6" style="border-color: var(--content-border)">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm" style="color: var(--content-muted)">
                    <span dir="auto">{{ __('reader.by_author', ['author' => $content->author->user?->username ?? __('ui.common.unknown_account')]) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ __('reader.published', ['date' => $content->published_at?->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') ?? '—']) }}</span>
                    @unless ($legacyEvidence)
                        <flux:badge size="sm">{{ __('reader.verified_edition') }}</flux:badge>
                    @endunless
                    <flux:badge size="sm">{{ __('presentation.preset.'.$revision->render_template_key) }}</flux:badge>
                </div>

                <h1 class="text-3xl sm:text-4xl {{ $headingClass }}" dir="auto">
                    {{ $revision->title }}
                </h1>
                <div class="mt-3 text-sm" style="color: var(--content-muted)" dir="auto">{{ $content->definition->name }}</div>
            </header>

            <div class="mx-auto mt-8 {{ $contentWidthClass }} space-y-7">
                @if ($revision->composition_mode === \App\Models\SpaceContentRevision::COMPOSITION_BLOCKS && $blocks->isNotEmpty())
                    @foreach ($blocks as $block)
                        @php
                            $blockStyle = is_array($block->style) ? $block->style : [];
                            $blockData = is_array($block->data) ? $block->data : [];
                            $blockTextColor = $blockStyle['text_color'] ?? null;
                            $blockBgColor = $blockStyle['background_color'] ?? null;
                            $blockAccent = $blockStyle['accent_color'] ?? $readerAccent;
                            $alignment = match ($blockStyle['alignment'] ?? 'start') {
                                'center' => 'text-center',
                                'end' => 'text-end',
                                default => 'text-start',
                            };
                            $emphasisClass = match ($blockStyle['emphasis'] ?? 'normal') {
                                'muted' => 'opacity-70',
                                'strong' => 'font-semibold',
                                'callout' => 'border-s-4 ps-4 py-2',
                                default => '',
                            };
                            $blockInlineStyle = collect([
                                $blockTextColor ? 'color: '.$blockTextColor : null,
                                $blockBgColor ? 'background: '.$blockBgColor.'; padding: 1rem' : null,
                                ($blockStyle['emphasis'] ?? null) === 'callout' ? 'border-color: '.$blockAccent : null,
                            ])->filter()->implode('; ');
                        @endphp

                        <section
                            wire:key="reader-block-{{ $block->uuid }}"
                            class="group relative {{ $alignment }} {{ $emphasisClass }} {{ $radiusClass }}"
                            style="{{ $blockInlineStyle }}"
                            data-annotation-target="block"
                            data-block-uuid="{{ $block->uuid }}"
                            data-block-label="{{ __('blocks.type.'.$block->type) }}"
                        >
                            @switch($block->type)
                                @case('paragraph')
                                    <div class="whitespace-pre-wrap break-words {{ $fontClass }}" dir="auto">{{ $blockData['text'] ?? '' }}</div>
                                    @break
                                @case('heading')
                                    @php $level = (int) ($blockData['level'] ?? 2); @endphp
                                    @if ($level === 4)
                                        <h4 class="text-lg {{ $headingClass }}" dir="auto">{{ $blockData['text'] ?? '' }}</h4>
                                    @elseif ($level === 3)
                                        <h3 class="text-xl {{ $headingClass }}" dir="auto">{{ $blockData['text'] ?? '' }}</h3>
                                    @else
                                        <h2 class="text-2xl {{ $headingClass }}" dir="auto">{{ $blockData['text'] ?? '' }}</h2>
                                    @endif
                                    @break
                                @case('quote')
                                    <blockquote class="border-s-4 ps-5 text-lg italic" style="border-color: var(--content-accent)" dir="auto">
                                        <div class="whitespace-pre-wrap">{{ $blockData['text'] ?? '' }}</div>
                                        @if (! empty($blockData['attribution']))
                                            <footer class="mt-2 text-sm not-italic" style="color: var(--content-muted)">— {{ $blockData['attribution'] }}</footer>
                                        @endif
                                    </blockquote>
                                    @break
                                @case('list')
                                    @if ($blockData['ordered'] ?? false)
                                        <ol class="list-decimal space-y-2 ps-6 {{ $fontClass }}" dir="auto">
                                            @foreach (($blockData['items'] ?? []) as $item)<li>{{ $item }}</li>@endforeach
                                        </ol>
                                    @else
                                        <ul class="list-disc space-y-2 ps-6 {{ $fontClass }}" dir="auto">
                                            @foreach (($blockData['items'] ?? []) as $item)<li>{{ $item }}</li>@endforeach
                                        </ul>
                                    @endif
                                    @break
                                @case('callout')
                                    @php
                                        $toneClass = match ($blockData['tone'] ?? 'info') {
                                            'success' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/20',
                                            'warning' => 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/20',
                                            'danger' => 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/20',
                                            default => 'border-blue-300 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/20',
                                        };
                                    @endphp
                                    <div class="rounded-xl border p-4 {{ $toneClass }} whitespace-pre-wrap {{ $fontClass }}" dir="auto">{{ $blockData['text'] ?? '' }}</div>
                                    @break
                                @case('divider')
                                    <hr style="border-color: var(--content-border)" />
                                    @break
                                @case('field')
                                    @php
                                        $fieldKey = $blockData['field_key'] ?? '';
                                        $field = collect($definitionVersion->schema['fields'])->firstWhere('key', $fieldKey);
                                        $value = $revision->payload[$fieldKey] ?? null;
                                        if (($field['type'] ?? null) === 'boolean') {
                                            $value = $value ? __('ui.content.yes') : __('ui.content.no');
                                        } elseif (($field['type'] ?? null) === 'select' && $value !== null) {
                                            $match = collect($field['options'] ?? [])->firstWhere('value', $value);
                                            $value = $match['label'] ?? $value;
                                        }
                                    @endphp
                                    @if ($value !== null && $value !== '')
                                        <div class="space-y-2" data-annotation-target="field" data-field-key="{{ $fieldKey }}">
                                            <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--content-muted)" dir="auto">{{ $blockData['label'] ?? $field['label'] ?? $fieldKey }}</div>
                                            <div class="whitespace-pre-wrap break-words {{ $fontClass }}" dir="auto">{{ $value }}</div>
                                        </div>
                                    @endif
                                    @break
                                @case('image')
                                @case('audio')
                                @case('video')
                                @case('file')
                                    @php $asset = $mediaByPlacement->get((string) ($blockData['asset_placement_uuid'] ?? '')); @endphp
                                    @if ($asset)
                                        @php
                                            $assetUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $asset]);
                                            $downloadUrl = route('groups.spaces.contents.assets.download', [$group, $space, $content, $asset]);
                                        @endphp
                                        <figure class="space-y-3 {{ $mediaStyle === 'card' ? 'border p-3 '.$radiusClass : '' }}" style="border-color: var(--content-border)">
                                            @if ($block->type === 'image')
                                                <img src="{{ $assetUrl }}" alt="{{ $asset->alt_text ?: ($blockData['caption'] ?? $asset->original_filename) }}" class="mx-auto max-h-[42rem] w-auto {{ $radiusClass }} object-contain" loading="lazy" />
                                            @elseif ($block->type === 'audio')
                                                <audio controls preload="metadata" class="w-full" src="{{ $assetUrl }}"></audio>
                                            @elseif ($block->type === 'video')
                                                <video controls preload="metadata" class="max-h-[42rem] w-full {{ $radiusClass }} bg-black" src="{{ $assetUrl }}"></video>
                                            @else
                                                <a href="{{ $downloadUrl }}" class="block p-4 font-medium" dir="auto">{{ __('reader.download') }} · {{ $asset->original_filename }}</a>
                                            @endif
                                            @if (! empty($blockData['caption']))<figcaption class="text-sm" style="color: var(--content-muted)" dir="auto">{{ $blockData['caption'] }}</figcaption>@endif
                                        </figure>
                                    @endif
                                    @break
                            @endswitch

                            @if ($canInteract && $block->type !== 'divider')
                                <div class="mt-2 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                    <button
                                        type="button"
                                        class="text-xs font-medium underline underline-offset-4"
                                        style="color: var(--content-accent)"
                                        x-data
                                        x-on:click="$wire.annotationComposerOpen = true; $wire.annotationAnchors = [...($wire.annotationAnchors || []), {target_type:'block', target_uuid:'{{ $block->uuid }}', field_key:null, selector:{label:@js(__('blocks.type.'.$block->type))}}]"
                                    >+ {{ __('interactions.add_note_here') }}</button>
                                </div>
                            @endif
                        </section>
                    @endforeach
                @else
                    @forelse ($definitionVersion->schema['fields'] as $field)
                        @php
                            $value = $revision->payload[$field['key']] ?? null;
                            if ($field['type'] === 'boolean') {
                                $displayValue = $value ? __('ui.content.yes') : __('ui.content.no');
                            } elseif ($field['type'] === 'select' && $value !== null) {
                                $match = collect($field['options'])->firstWhere('value', $value);
                                $displayValue = $match['label'] ?? $value;
                            } else {
                                $displayValue = $value;
                            }
                            $fieldStyle = is_array($fieldStyles[$field['key']] ?? null) ? $fieldStyles[$field['key']] : [];
                            $fieldInline = collect([
                                ! empty($fieldStyle['text_color']) ? 'color: '.$fieldStyle['text_color'] : null,
                                ! empty($fieldStyle['background_color']) ? 'background: '.$fieldStyle['background_color'].'; padding: 1rem' : null,
                            ])->filter()->implode('; ');
                        @endphp

                        @if ($displayValue !== null && $displayValue !== '')
                            <section
                                class="group relative space-y-2 {{ ($fieldStyle['emphasis'] ?? null) === 'callout' ? 'border-s-4 ps-4' : '' }} {{ ($fieldStyle['emphasis'] ?? null) === 'strong' ? 'font-semibold' : '' }} {{ ($fieldStyle['emphasis'] ?? null) === 'muted' ? 'opacity-70' : '' }} {{ $radiusClass }}"
                                style="{{ $fieldInline }}; {{ ($fieldStyle['emphasis'] ?? null) === 'callout' ? 'border-color: '.($fieldStyle['accent_color'] ?? $readerAccent) : '' }}"
                                data-annotation-target="field"
                                data-field-key="{{ $field['key'] }}"
                            >
                                <h2 class="text-sm font-semibold uppercase tracking-wide" style="color: var(--content-muted)" dir="auto">{{ $field['label'] }}</h2>
                                <div class="whitespace-pre-wrap break-words {{ $fontClass }}" dir="auto">{{ $displayValue }}</div>
                                @if ($canInteract)
                                    <div class="flex items-center gap-2 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                        <button type="button" wire:click="addFieldAnchor(@js($field['key']))" class="text-xs font-medium underline underline-offset-4" style="color: var(--content-accent)">+ {{ __('interactions.add_note_here') }}</button>
                                        @if (($fieldAnnotationCounts[$field['key']] ?? 0) > 0)
                                            <span class="text-xs" style="color: var(--content-muted)">{{ trans_choice('interactions.annotation_count', $fieldAnnotationCounts[$field['key']], ['count' => $fieldAnnotationCounts[$field['key']]]) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </section>
                        @endif
                    @empty
                        <flux:text>{{ __('reader.no_body') }}</flux:text>
                    @endforelse
                @endif

                @if ($revision->assets->isNotEmpty() && $revision->composition_mode !== \App\Models\SpaceContentRevision::COMPOSITION_BLOCKS)
                    <section class="space-y-5 border-t pt-7" style="border-color: var(--content-border)">
                        <h2 class="text-xl {{ $headingClass }}">{{ __('reader.media') }}</h2>

                        @foreach ($revision->assets as $asset)
                            @php
                                $assetUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $asset]);
                                $downloadUrl = route('groups.spaces.contents.assets.download', [$group, $space, $content, $asset]);
                                $caption = $asset->pivot->caption;
                                $placementUuid = (string) $asset->pivot->uuid;
                            @endphp

                            <figure class="group space-y-3 {{ $mediaStyle === 'card' ? 'border p-3 sm:p-4 '.$radiusClass : '' }}" style="border-color: var(--content-border)" data-annotation-target="asset" data-placement-uuid="{{ $placementUuid }}">
                                @switch($asset->mediaKind())
                                    @case('image')
                                        <img src="{{ $assetUrl }}" alt="{{ $asset->alt_text ?: ($caption ?: $asset->original_filename) }}" class="mx-auto max-h-[42rem] w-auto {{ $radiusClass }} object-contain" loading="lazy" />
                                        @break
                                    @case('audio')
                                        <audio controls preload="metadata" class="w-full" src="{{ $assetUrl }}"></audio>
                                        @break
                                    @case('video')
                                        <video controls preload="metadata" class="max-h-[42rem] w-full {{ $radiusClass }} bg-black" src="{{ $assetUrl }}"></video>
                                        @break
                                    @case('pdf')
                                        <a href="{{ $assetUrl }}" target="_blank" rel="noopener" class="block p-5 font-medium" dir="auto">{{ $asset->original_filename }} ↗</a>
                                        @break
                                    @default
                                        <a href="{{ $downloadUrl }}" class="block p-5 font-medium" dir="auto">{{ __('reader.download') }} · {{ $asset->original_filename }}</a>
                                @endswitch

                                @if ($caption)<figcaption class="text-sm leading-6" style="color: var(--content-muted)" dir="auto">{{ $caption }}</figcaption>@endif
                                @if ($canInteract)
                                    <div class="flex items-center gap-2 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                        <button type="button" wire:click="addAssetAnchor('{{ $placementUuid }}')" class="text-xs font-medium underline underline-offset-4" style="color: var(--content-accent)">+ {{ __('interactions.add_note_here') }}</button>
                                        @if (($assetAnnotationCounts[$placementUuid] ?? 0) > 0)
                                            <span class="text-xs" style="color: var(--content-muted)">{{ trans_choice('interactions.annotation_count', $assetAnnotationCounts[$placementUuid], ['count' => $assetAnnotationCounts[$placementUuid]]) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </figure>
                        @endforeach
                    </section>
                @endif

                @if ($canInteract && $annotationComposerOpen)
                    <section class="space-y-5 border-t pt-7" style="border-color: var(--content-border)" id="annotation-composer">
                        <div class="{{ $radiusClass }} border p-4 sm:p-5" style="border-color: var(--content-border); background: var(--content-surface)">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-semibold">{{ __('interactions.composer_title') }}</h2>
                                    <p class="mt-1 text-sm" style="color: var(--content-muted)">{{ __('interactions.composer_help') }}</p>
                                </div>
                                <flux:button wire:click="clearAnnotationComposer" size="sm" variant="ghost">{{ __('interactions.cancel') }}</flux:button>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @forelse ($annotationAnchors as $index => $anchor)
                                    <button type="button" wire:click="removeAnnotationAnchor({{ $index }})" class="rounded-full border px-3 py-1 text-xs" style="border-color: var(--content-border)">
                                        {{ $anchor['selector']['label'] ?? __('interactions.anchor.'.$anchor['target_type']) }} ×
                                    </button>
                                @empty
                                    <span class="text-sm" style="color: var(--content-muted)">{{ __('interactions.anchor.entire_edition') }}</span>
                                @endforelse
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <flux:select wire:model="annotationKind" :label="__('interactions.role')">
                                    @foreach ($annotationKinds as $kind)
                                        <option value="{{ $kind }}">{{ __('interactions.kind.'.$kind) }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="annotationVisibility" :label="__('interactions.visibility')">
                                    @foreach ($annotationVisibilities as $visibility)
                                        <option value="{{ $visibility }}">{{ __('interactions.visibility_value.'.$visibility) }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="mt-4">
                                <flux:textarea wire:model="annotationBody" :label="__('interactions.note_body')" :placeholder="__('interactions.note_placeholder')" rows="4" maxlength="5000" />
                                @error('annotationBody')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                            </div>

                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                <div class="space-y-3 rounded-xl border p-4" style="border-color: var(--content-border)">
                                    <div class="font-medium">{{ __('interactions.attach_file') }}</div>
                                    <input type="file" wire:model="annotationUpload" class="block w-full text-sm" />
                                    @error('annotationUpload')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                                    @if ($annotationUpload)
                                        <flux:select wire:model="annotationRightsStatus" :label="__('media.rights_status')">
                                            @foreach ($rightsStatuses as $status)<option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>@endforeach
                                        </flux:select>
                                        <flux:input wire:model="annotationCaption" :label="__('media.caption')" maxlength="1000" />
                                        <flux:button wire:click="clearAnnotationUpload" size="sm" variant="ghost">{{ __('interactions.remove_attachment') }}</flux:button>
                                    @endif
                                </div>

                                <div class="space-y-3 rounded-xl border p-4" style="border-color: var(--content-border)">
                                    <div class="font-medium">{{ __('interactions.voice_note') }}</div>
                                    <p class="text-sm" style="color: var(--content-muted)">{{ __('interactions.voice_note_help') }}</p>
                                    <div id="annotation-audio-recorder-{{ $content->uuid }}" wire:ignore class="flex flex-wrap items-center gap-2">
                                        <flux:button id="annotation-audio-start-{{ $content->uuid }}" type="button" size="sm" variant="primary">{{ __('media.start_recording') }}</flux:button>
                                        <flux:button id="annotation-audio-stop-{{ $content->uuid }}" type="button" size="sm" variant="danger" class="hidden">{{ __('media.stop_recording') }}</flux:button>
                                        <span id="annotation-audio-status-{{ $content->uuid }}" class="hidden text-sm" style="color: var(--content-muted)"></span>
                                        <span id="annotation-audio-timer-{{ $content->uuid }}" class="hidden text-sm">00:00</span>
                                    </div>
                                    <p id="annotation-audio-error-{{ $content->uuid }}" class="hidden text-sm text-red-600"></p>
                                </div>
                            </div>

                            <div class="mt-5 flex justify-end">
                                <flux:button wire:click="postAnnotation" wire:loading.attr="disabled" wire:target="postAnnotation,annotationUpload" variant="primary">{{ __('interactions.post_annotation') }}</flux:button>
                            </div>
                        </div>
                    </section>
                @endif

                <section class="space-y-6 border-t pt-7" style="border-color: var(--content-border)" id="discussion">
                    <div class="space-y-1">
                        <h2 class="text-xl {{ $headingClass }}">{{ __('interactions.title') }}</h2>
                        <p class="text-sm" style="color: var(--content-muted)">{{ __('interactions.edition_help') }}</p>
                    </div>

                    @error('interaction')<flux:callout variant="warning">{{ $message }}</flux:callout>@enderror
                    @if (! $canInteract)<flux:callout>{{ __('interactions.verified_only') }}</flux:callout>@endif

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="me-1 text-sm font-medium">{{ __('interactions.reactions') }}</span>
                        @foreach ($reactionTypes as $reactionType)
                            @php
                                $reactionActive = in_array($reactionType, $viewerReactions, true);
                                $reactionCount = $reactionCounts[$reactionType] ?? 0;
                            @endphp
                            <flux:button wire:key="reaction-{{ $reactionType }}" wire:click="toggleReaction('{{ $reactionType }}')" wire:loading.attr="disabled" wire:target="toggleReaction" size="sm" :variant="$reactionActive ? 'primary' : 'ghost'" :disabled="! $canInteract">
                                {{ __('interactions.reaction.'.$reactionType) }} · {{ $reactionCount }}
                            </flux:button>
                        @endforeach
                    </div>

                    @if ($canInteract && ! $annotationComposerOpen)
                        <div class="flex flex-wrap gap-2">
                            <flux:button wire:click="openRevisionAnnotation" variant="primary">+ {{ __('interactions.add_annotation') }}</flux:button>
                        </div>
                    @endif

                    <div class="space-y-5">
                        @forelse ($annotations as $annotation)
                            <article wire:key="annotation-{{ $annotation->uuid }}" class="space-y-4 {{ $radiusClass }} border p-4" style="border-color: var(--content-border); background: var(--content-surface)">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium" dir="auto">{{ $annotation->author->user?->username ?? __('ui.common.unknown_account') }}</span>
                                        <flux:badge size="sm">{{ __('interactions.kind.'.$annotation->kind) }}</flux:badge>
                                        @if ($annotation->visibility === 'private')<flux:badge size="sm">{{ __('interactions.visibility_value.private') }}</flux:badge>@endif
                                    </div>
                                    <div class="text-xs" style="color: var(--content-muted)">{{ $annotation->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}</div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @foreach ($annotation->anchors as $anchor)
                                        <span class="rounded-full border px-2 py-1 text-xs" style="border-color: var(--content-border)">
                                            {{ $anchor->selector['label'] ?? __('interactions.anchor.'.$anchor->target_type) }}
                                            @if (! empty($anchor->selector['exact'])) · “{{ \Illuminate\Support\Str::limit($anchor->selector['exact'], 80) }}” @endif
                                        </span>
                                    @endforeach
                                </div>

                                @if ($annotation->body)<div class="whitespace-pre-wrap break-words text-sm leading-7" dir="auto">{{ $annotation->body }}</div>@endif

                                @foreach ($annotation->assets as $attachment)
                                    @php
                                        $attachmentUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $attachment]);
                                        $attachmentDownload = route('groups.spaces.contents.assets.download', [$group, $space, $content, $attachment]);
                                    @endphp
                                    <div class="rounded-xl border p-3" style="border-color: var(--content-border)">
                                        @if ($attachment->processing_status !== 'ready')
                                            <div class="text-sm">{{ __('interactions.attachment_processing') }}</div>
                                        @elseif ($attachment->mediaKind() === 'image')
                                            <img src="{{ $attachmentUrl }}" alt="{{ $attachment->pivot->caption ?: $attachment->original_filename }}" class="max-h-72 rounded-lg object-contain" />
                                        @elseif ($attachment->mediaKind() === 'audio')
                                            <audio controls preload="metadata" class="w-full" src="{{ $attachmentUrl }}"></audio>
                                        @elseif ($attachment->mediaKind() === 'video')
                                            <video controls preload="metadata" class="max-h-80 w-full rounded-lg bg-black" src="{{ $attachmentUrl }}"></video>
                                        @else
                                            <a href="{{ $attachmentDownload }}" class="font-medium" dir="auto">{{ $attachment->original_filename }}</a>
                                        @endif
                                        @if ($attachment->pivot->caption)<div class="mt-2 text-sm" style="color: var(--content-muted)" dir="auto">{{ $attachment->pivot->caption }}</div>@endif
                                    </div>
                                @endforeach

                                @if ($canInteract)
                                    <flux:button wire:click="startReply('{{ $annotation->uuid }}')" size="sm" variant="ghost">
                                        {{ $annotation->kind === 'question' ? __('interactions.answer') : __('interactions.reply') }}
                                    </flux:button>
                                @endif

                                @if ($annotation->replies->isNotEmpty())
                                    <div class="space-y-3 border-s-2 ps-4" style="border-color: var(--content-border)">
                                        @foreach ($annotation->replies as $reply)
                                            <div wire:key="reply-{{ $reply->uuid }}" class="space-y-2 rounded-lg p-3" style="background: var(--content-bg)">
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                    <div class="flex items-center gap-2"><span class="font-medium" dir="auto">{{ $reply->author->user?->username ?? __('ui.common.unknown_account') }}</span><flux:badge size="sm">{{ __('interactions.kind.'.$reply->kind) }}</flux:badge></div>
                                                    <div class="text-xs" style="color: var(--content-muted)">{{ $reply->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}</div>
                                                </div>
                                                @if ($reply->body)<div class="whitespace-pre-wrap break-words text-sm leading-6" dir="auto">{{ $reply->body }}</div>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($canInteract && $replyingTo === $annotation->uuid)
                                    <form wire:submit="addReply" class="space-y-3 border-t pt-4" style="border-color: var(--content-border)">
                                        <textarea wire:model="replyBody" rows="3" maxlength="5000" placeholder="{{ $annotation->kind === 'question' ? __('interactions.answer_placeholder') : __('interactions.reply_placeholder') }}" class="w-full rounded-lg border bg-transparent px-3 py-2 text-sm" style="border-color: var(--content-border)" dir="auto"></textarea>
                                        @error('replyBody')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                                        <div class="flex justify-end gap-2">
                                            <flux:button wire:click="cancelReply" type="button" variant="ghost">{{ __('interactions.cancel') }}</flux:button>
                                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addReply">{{ $annotation->kind === 'question' ? __('interactions.post_answer') : __('interactions.post_reply') }}</flux:button>
                                        </div>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="{{ $radiusClass }} p-5 text-sm" style="background: var(--content-surface); color: var(--content-muted)">{{ __('interactions.no_comments') }}</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </article>

        <aside class="space-y-4 self-start xl:sticky xl:top-6">
            @if (count($outline) > 0)
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('reader.outline') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('reader.outline_help') }}</flux:text>
                    </div>
                    <x-app.content-outline-tree :items="$outline" :group="$group" :space="$space" />
                </flux:card>
            @endif

            @if ($canInteract && $relationships->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('interactions.annotate_outline') }}</flux:heading>
                    @foreach ($relationships as $relationship)
                        @php $childRevision = $relationship->childContent->activeRevision; @endphp
                        <button type="button" wire:click="addRelationshipAnchor('{{ $relationship->uuid }}', @js($childRevision?->title ?? __('reader.outline')))" class="w-full rounded-lg border border-zinc-200 p-2 text-start text-sm dark:border-zinc-800" dir="auto">
                            + {{ $childRevision?->title ?? __('reader.outline') }}
                        </button>
                    @endforeach
                </flux:card>
            @endif
        </aside>
    </div>

    <button id="content-selection-annotator-{{ $content->uuid }}" type="button" class="fixed z-50 hidden rounded-full px-3 py-2 text-xs font-semibold text-white shadow-lg" style="background: var(--content-accent)">
        {{ __('interactions.annotate_selection') }}
    </button>
</section>

@if ($canInteract)
    @script
    <script>
        (() => {
            const suffix = @js((string) $content->uuid)
            const selectionButton = document.getElementById(`content-selection-annotator-${suffix}`)
            let pendingAnchor = null

            const textContext = (container, exact) => {
                const full = container.innerText || container.textContent || ''
                const index = full.indexOf(exact)
                return {
                    prefix: index >= 0 ? full.slice(Math.max(0, index - 120), index) : '',
                    suffix: index >= 0 ? full.slice(index + exact.length, index + exact.length + 120) : '',
                }
            }

            const hideSelectionButton = () => {
                selectionButton?.classList.add('hidden')
                pendingAnchor = null
            }

            document.addEventListener('mouseup', event => {
                if (!selectionButton) return
                const selection = window.getSelection()
                const exact = selection?.toString().trim() || ''
                if (!exact || exact.length > 2000 || !selection?.rangeCount) {
                    hideSelectionButton()
                    return
                }

                const range = selection.getRangeAt(0)
                const node = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
                    ? range.commonAncestorContainer
                    : range.commonAncestorContainer.parentElement
                const container = node?.closest?.('[data-annotation-target]')
                if (!container || !container.contains(range.commonAncestorContainer)) {
                    hideSelectionButton()
                    return
                }

                const context = textContext(container, exact)
                if (container.dataset.annotationTarget === 'field') {
                    pendingAnchor = {
                        target_type: 'text', target_uuid: null, field_key: container.dataset.fieldKey,
                        selector: { exact, prefix: context.prefix, suffix: context.suffix, label: exact.slice(0, 80) },
                    }
                } else if (container.dataset.annotationTarget === 'block') {
                    pendingAnchor = {
                        target_type: 'block', target_uuid: container.dataset.blockUuid, field_key: null,
                        selector: { exact, prefix: context.prefix, suffix: context.suffix, label: container.dataset.blockLabel || exact.slice(0, 80) },
                    }
                } else {
                    hideSelectionButton()
                    return
                }

                const x = Math.min(window.innerWidth - 180, event.clientX + 8)
                const y = Math.max(8, event.clientY - 44)
                selectionButton.style.left = `${x}px`
                selectionButton.style.top = `${y}px`
                selectionButton.classList.remove('hidden')
            })

            selectionButton?.addEventListener('click', () => {
                if (!pendingAnchor) return
                const existing = Array.isArray($wire.annotationAnchors) ? $wire.annotationAnchors : []
                $wire.set('annotationAnchors', [...existing, pendingAnchor])
                $wire.set('annotationComposerOpen', true)
                hideSelectionButton()
                window.getSelection()?.removeAllRanges()
                setTimeout(() => document.getElementById('annotation-composer')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100)
            })

            const root = document.getElementById(`annotation-audio-recorder-${suffix}`)
            if (!root || root.dataset.recorderReady === '1') return
            root.dataset.recorderReady = '1'
            const start = document.getElementById(`annotation-audio-start-${suffix}`)
            const stop = document.getElementById(`annotation-audio-stop-${suffix}`)
            const status = document.getElementById(`annotation-audio-status-${suffix}`)
            const timerLabel = document.getElementById(`annotation-audio-timer-${suffix}`)
            const errorLabel = document.getElementById(`annotation-audio-error-${suffix}`)
            let recorder = null
            let stream = null
            let chunks = []
            let elapsed = 0
            let timer = null

            const show = (el, visible) => el?.classList.toggle('hidden', !visible)
            const setError = message => {
                if (!errorLabel) return
                errorLabel.textContent = message || ''
                show(errorLabel, Boolean(message))
            }
            const updateTimer = () => {
                if (!timerLabel) return
                timerLabel.textContent = `${Math.floor(elapsed / 60).toString().padStart(2, '0')}:${(elapsed % 60).toString().padStart(2, '0')}`
            }
            const cleanup = () => {
                clearInterval(timer)
                timer = null
                stream?.getTracks().forEach(track => track.stop())
                stream = null
            }

            start?.addEventListener('click', async () => {
                setError('')
                if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
                    setError(@js(__('media.recorder_unavailable')))
                    return
                }
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']
                    const mimeType = candidates.find(type => MediaRecorder.isTypeSupported(type)) ?? ''
                    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined)
                    chunks = []
                    elapsed = 0
                    updateTimer()
                    recorder.addEventListener('dataavailable', event => { if (event.data.size > 0) chunks.push(event.data) })
                    recorder.addEventListener('stop', () => {
                        const type = recorder?.mimeType || 'audio/webm'
                        const extension = type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm')
                        const blob = new Blob(chunks, { type })
                        cleanup()
                        recorder = null
                        chunks = []
                        if (!blob.size) {
                            setError(@js(__('media.recording_error')))
                            show(start, true); show(stop, false); show(status, false); show(timerLabel, false)
                            return
                        }
                        const file = new File([blob], `voice-note-${Date.now()}.${extension}`, { type })
                        if (status) status.textContent = @js(__('interactions.uploading_voice_note'))
                        show(status, true); show(timerLabel, false); show(stop, false)
                        $wire.upload('annotationUpload', file, () => {
                            $wire.call('markAnnotationRecordingReady').then(() => {
                                if (status) status.textContent = @js(__('interactions.voice_note_ready'))
                                show(start, true)
                            })
                        }, () => {
                            setError(@js(__('media.recording_error')))
                            show(start, true); show(status, false)
                        })
                    }, { once: true })
                    recorder.start()
                    show(start, false); show(stop, true); show(status, true); show(timerLabel, true)
                    if (status) status.textContent = @js(__('media.recording'))
                    timer = setInterval(() => { elapsed++; updateTimer() }, 1000)
                } catch (error) {
                    cleanup()
                    recorder = null
                    setError(@js(__('media.recording_error')))
                    show(start, true); show(stop, false); show(status, false); show(timerLabel, false)
                }
            })

            stop?.addEventListener('click', () => {
                if (!recorder || recorder.state === 'inactive') return
                clearInterval(timer)
                recorder.stop()
            })
        })()
    </script>
    @endscript
@endif
