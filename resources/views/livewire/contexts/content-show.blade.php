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
    $readerPublishedAt = $viewingEvidenceReferenceUuid !== null
        ? $revision->manifest_sealed_at
        : $content->published_at;
@endphp

<section
    id="content-reader-{{ $content->uuid }}"
    class="min-h-screen space-y-6 px-1 pb-16"
    style="--content-bg: {{ $readerBackground }}; --content-surface: {{ $readerSurface }}; --content-text: {{ $readerText }}; --content-muted: {{ $readerMuted }}; --content-accent: {{ $readerAccent }}; --content-border: {{ $readerBorder }}; background: var(--content-bg); color: var(--content-text);"
>
    <style>
        @media (max-width: 639px) {
            #annotation-selection-toolbar-{{ $content->uuid }},
            #annotation-context-composer-{{ $content->uuid }},
            #annotation-preview-panel-{{ $content->uuid }} {
                inset-inline: .75rem !important;
                left: auto !important;
                right: auto !important;
                top: auto !important;
                bottom: .75rem !important;
                width: auto !important;
                max-width: none !important;
            }
        }
    </style>

    <div id="annotation-marker-payload-{{ $content->uuid }}" class="hidden" data-marker-payload='@json($rangeMarkers)'></div>

    <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 pt-4">
        <flux:button :href="route('contexts.contents.index', $context)" variant="ghost" size="sm">
            ← {{ __('reader.back_to_library') }}
        </flux:button>

        <div class="flex flex-wrap items-center gap-2">
            @if ($canInteract)
                <x-app.annotation-target-menu
                    target-type="revision"
                    :target-uuid="$revision->uuid"
                    :label="__('interactions.anchor.entire_edition')"
                />
            @endif
            @if ($revision->hasVerifiableManifest())
                <flux:button :href="route('contexts.contents.revisions.show', [$context, $content, $revision])" variant="ghost" size="sm">
                    {{ __('reader.revision_permalink') }}
                </flux:button>
            @endif
            @if ($canEnterStudio)
                @unless ($legacyEvidence || $viewingEvidenceReferenceUuid || $viewingRevisionUuid)
                    <flux:button wire:click="createRevisionEvidence" variant="ghost" size="sm">
                        {{ __('ui.context_content.create_evidence_reference') }}
                    </flux:button>
                @endunless
                <flux:button :href="route('contexts.contents.studio', [$context, $content])" variant="primary" size="sm">
                    {{ __('reader.edit_in_studio') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($viewingEvidenceReferenceUuid)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="info">
                <div class="font-medium">{{ __('ui.context_content.historical_evidence') }}</div>
                <div class="mt-1 text-sm">{{ __('ui.context_content.historical_evidence_help') }}</div>
                <div class="mt-2 break-all font-mono text-xs">{{ $viewingEvidenceReferenceUuid }}</div>
            </flux:callout>
        </div>
    @endif

    @if ($viewingRevisionUuid)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="info">
                <div class="font-medium">{{ __('reader.fixed_edition_title') }}</div>
                <div class="mt-1 text-sm">{{ __('reader.fixed_edition_help') }}</div>
                <div class="mt-2 break-all font-mono text-xs">{{ $viewingRevisionUuid }}</div>
            </flux:callout>
        </div>
    @endif

    @if ($evidenceReferenceUuid)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="success">
                {{ __('ui.context_content.evidence_reference_created') }}
                <span class="font-mono">{{ $evidenceReferenceUuid }}</span>
            </flux:callout>
        </div>
    @endif

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
                    <x-app.actor-identity :actor="$content->author" size="xs" />
                    <span aria-hidden="true">·</span>
                    <span>{{ __('reader.published', ['date' => $readerPublishedAt?->timezone($viewerTimezone)->format('Y-m-d H:i') ?? '—']) }}</span>
                    @unless ($legacyEvidence)
                        <flux:badge size="sm">{{ __('reader.verified_edition') }}</flux:badge>
                    @endunless
                    <flux:badge size="sm">{{ __('presentation.preset.'.$revision->render_template_key) }}</flux:badge>
                </div>

                <h1 class="text-3xl sm:text-4xl {{ $headingClass }}" dir="auto">{{ $revision->title }}</h1>
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
                            $blockMarkers = $blockMarkerUuids[$block->uuid] ?? [];
                        @endphp

                        <section
                            wire:key="reader-block-{{ $block->uuid }}"
                            class="group relative {{ $alignment }} {{ $emphasisClass }} {{ $radiusClass }}"
                            style="{{ $blockInlineStyle }}"
                            data-annotation-target="block"
                            data-target-type="block"
                            data-target-uuid="{{ $block->uuid }}"
                            data-target-label="{{ __('blocks.type.'.$block->type) }}"
                        >
                            @switch($block->type)
                                @case('paragraph')
                                    <div
                                        class="whitespace-pre-wrap break-words {{ $fontClass }}"
                                        dir="auto"
                                        data-annotation-text
                                        data-anchor-target="block"
                                        data-target-uuid="{{ $block->uuid }}"
                                        data-anchor-base="0"
                                    >{{ $blockData['text'] ?? '' }}</div>
                                    @break
                                @case('heading')
                                    @php $level = (int) ($blockData['level'] ?? 2); @endphp
                                    @if ($level === 4)
                                        <h4 class="text-lg {{ $headingClass }}" dir="auto" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="0">{{ $blockData['text'] ?? '' }}</h4>
                                    @elseif ($level === 3)
                                        <h3 class="text-xl {{ $headingClass }}" dir="auto" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="0">{{ $blockData['text'] ?? '' }}</h3>
                                    @else
                                        <h2 class="text-2xl {{ $headingClass }}" dir="auto" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="0">{{ $blockData['text'] ?? '' }}</h2>
                                    @endif
                                    @break
                                @case('quote')
                                    @php $quoteText = (string) ($blockData['text'] ?? ''); @endphp
                                    <blockquote class="border-s-4 ps-5 text-lg italic" style="border-color: var(--content-accent)" dir="auto">
                                        <div class="whitespace-pre-wrap" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="0">{{ $quoteText }}</div>
                                        @if (! empty($blockData['attribution']))
                                            <footer class="mt-2 text-sm not-italic" style="color: var(--content-muted)" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="{{ mb_strlen($quoteText) + 1 }}">{{ $blockData['attribution'] }}</footer>
                                        @endif
                                    </blockquote>
                                    @break
                                @case('list')
                                    @php $listBase = 0; @endphp
                                    @if ($blockData['ordered'] ?? false)
                                        <ol class="list-decimal space-y-2 ps-6 {{ $fontClass }}" dir="auto">
                                            @foreach (($blockData['items'] ?? []) as $item)
                                                <li data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="{{ $listBase }}">{{ $item }}</li>
                                                @php $listBase += mb_strlen((string) $item) + 1; @endphp
                                            @endforeach
                                        </ol>
                                    @else
                                        <ul class="list-disc space-y-2 ps-6 {{ $fontClass }}" dir="auto">
                                            @foreach (($blockData['items'] ?? []) as $item)
                                                <li data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="{{ $listBase }}">{{ $item }}</li>
                                                @php $listBase += mb_strlen((string) $item) + 1; @endphp
                                            @endforeach
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
                                    <div class="rounded-xl border p-4 {{ $toneClass }} whitespace-pre-wrap {{ $fontClass }}" dir="auto" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="0">{{ $blockData['text'] ?? '' }}</div>
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
                                        $fieldMarkers = $fieldMarkerUuids[$fieldKey] ?? [];
                                    @endphp
                                    @if ($value !== null && $value !== '')
                                        <div class="group/field relative space-y-2" data-annotation-target="field" data-target-type="field" data-field-key="{{ $fieldKey }}" data-target-label="{{ $blockData['label'] ?? $field['label'] ?? $fieldKey }}">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--content-muted)" dir="auto">{{ $blockData['label'] ?? $field['label'] ?? $fieldKey }}</div>
                                                @if ($canInteract)
                                                    <x-app.annotation-target-menu target-type="field" :field-key="$fieldKey" :label="$blockData['label'] ?? $field['label'] ?? $fieldKey" :marker-uuids="$fieldMarkers" />
                                                @endif
                                            </div>
                                            <div
                                                class="whitespace-pre-wrap break-words {{ $fontClass }}"
                                                dir="auto"
                                                @if (($field['type'] ?? null) !== 'boolean')
                                                    data-annotation-text data-anchor-target="field" data-field-key="{{ $fieldKey }}" data-anchor-base="0"
                                                @endif
                                            >{{ $value }}</div>
                                        </div>
                                    @endif
                                    @break
                                @case('image')
                                @case('audio')
                                @case('video')
                                @case('file')
                                    @php
                                        $placementUuid = (string) ($blockData['asset_placement_uuid'] ?? '');
                                        $asset = $mediaByPlacement->get($placementUuid);
                                        $assetMarkers = $assetMarkerUuids[$placementUuid] ?? [];
                                    @endphp
                                    @if ($asset)
                                        @php
                                            $assetUrl = route('contexts.contents.assets.show', [$context, $content, $asset]);
                                            $downloadUrl = route('contexts.contents.assets.download', [$context, $content, $asset]);
                                        @endphp
                                        <figure class="group/media relative space-y-3 {{ $mediaStyle === 'card' ? 'border p-3 '.$radiusClass : '' }}" style="border-color: var(--content-border)" data-annotation-target="asset" data-target-type="asset" data-target-uuid="{{ $placementUuid }}" data-target-label="{{ $blockData['caption'] ?? $asset->original_filename }}">
                                            <div class="absolute end-2 top-2 z-10 opacity-0 transition group-hover/media:opacity-100 group-focus-within/media:opacity-100">
                                                @if ($canInteract)
                                                    <x-app.annotation-target-menu target-type="asset" :target-uuid="$placementUuid" :label="$blockData['caption'] ?? $asset->original_filename" :marker-uuids="$assetMarkers" />
                                                @endif
                                            </div>
                                            @if ($block->type === 'image')
                                                <img src="{{ $assetUrl }}" alt="{{ $asset->alt_text ?: ($blockData['caption'] ?? $asset->original_filename) }}" class="mx-auto max-h-[42rem] w-auto {{ $radiusClass }} object-contain" loading="lazy" />
                                            @elseif ($block->type === 'audio')
                                                <audio controls preload="metadata" class="w-full" src="{{ $assetUrl }}"></audio>
                                            @elseif ($block->type === 'video')
                                                <video controls preload="metadata" class="max-h-[42rem] w-full {{ $radiusClass }} bg-black" src="{{ $assetUrl }}"></video>
                                            @else
                                                <a href="{{ $downloadUrl }}" class="block p-4 font-medium" dir="auto">{{ __('reader.download') }} · {{ $asset->original_filename }}</a>
                                            @endif
                                            @if (! empty($blockData['caption']))
                                                <figcaption class="text-sm" style="color: var(--content-muted)" dir="auto" data-annotation-text data-anchor-target="block" data-target-uuid="{{ $block->uuid }}" data-anchor-base="0">{{ $blockData['caption'] }}</figcaption>
                                            @endif
                                        </figure>
                                    @endif
                                    @break
                            @endswitch

                            @if ($canInteract && ! in_array($block->type, ['divider', 'field', 'image', 'audio', 'video', 'file'], true))
                                <div class="absolute -end-10 top-0 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                    <x-app.annotation-target-menu target-type="block" :target-uuid="$block->uuid" :label="__('blocks.type.'.$block->type)" :marker-uuids="$blockMarkers" />
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
                            $fieldMarkers = $fieldMarkerUuids[$field['key']] ?? [];
                        @endphp

                        @if ($displayValue !== null && $displayValue !== '')
                            <section
                                class="group relative space-y-2 {{ ($fieldStyle['emphasis'] ?? null) === 'callout' ? 'border-s-4 ps-4' : '' }} {{ ($fieldStyle['emphasis'] ?? null) === 'strong' ? 'font-semibold' : '' }} {{ ($fieldStyle['emphasis'] ?? null) === 'muted' ? 'opacity-70' : '' }} {{ $radiusClass }}"
                                style="{{ $fieldInline }}; {{ ($fieldStyle['emphasis'] ?? null) === 'callout' ? 'border-color: '.($fieldStyle['accent_color'] ?? $readerAccent) : '' }}"
                                data-annotation-target="field"
                                data-target-type="field"
                                data-field-key="{{ $field['key'] }}"
                                data-target-label="{{ $field['label'] }}"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <h2 class="text-sm font-semibold uppercase tracking-wide" style="color: var(--content-muted)" dir="auto">{{ $field['label'] }}</h2>
                                    @if ($canInteract)
                                        <x-app.annotation-target-menu target-type="field" :field-key="$field['key']" :label="$field['label']" :marker-uuids="$fieldMarkers" />
                                    @endif
                                </div>
                                <div
                                    class="whitespace-pre-wrap break-words {{ $fontClass }}"
                                    dir="auto"
                                    @if ($field['type'] !== 'boolean')
                                        data-annotation-text data-anchor-target="field" data-field-key="{{ $field['key'] }}" data-anchor-base="0"
                                    @endif
                                >{{ $displayValue }}</div>
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
                                $assetUrl = route('contexts.contents.assets.show', [$context, $content, $asset]);
                                $downloadUrl = route('contexts.contents.assets.download', [$context, $content, $asset]);
                                $caption = $asset->pivot->caption;
                                $placementUuid = (string) $asset->pivot->uuid;
                                $assetMarkers = $assetMarkerUuids[$placementUuid] ?? [];
                            @endphp
                            <figure class="group relative space-y-3 {{ $mediaStyle === 'card' ? 'border p-3 sm:p-4 '.$radiusClass : '' }}" style="border-color: var(--content-border)" data-annotation-target="asset" data-target-type="asset" data-target-uuid="{{ $placementUuid }}" data-target-label="{{ $caption ?: $asset->original_filename }}">
                                <div class="absolute end-2 top-2 z-10 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                    @if ($canInteract)
                                        <x-app.annotation-target-menu target-type="asset" :target-uuid="$placementUuid" :label="$caption ?: $asset->original_filename" :marker-uuids="$assetMarkers" />
                                    @endif
                                </div>
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
                                @if ($caption)
                                    <figcaption class="text-sm leading-6" style="color: var(--content-muted)" dir="auto">{{ $caption }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </section>
                @endif

                <section class="space-y-5 border-t pt-7" style="border-color: var(--content-border)" id="discussion">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl {{ $headingClass }}">{{ __('interactions.title') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--content-muted)">{{ __('interactions.edition_help') }}</p>
                        </div>
                        @if ($canInteract)
                            <x-app.annotation-target-menu target-type="revision" :target-uuid="$revision->uuid" :label="__('interactions.anchor.entire_edition')" />
                        @endif
                    </div>

                    @error('interaction')
                        <flux:callout variant="warning">{{ $message }}</flux:callout>
                    @enderror
                    @if (! $canInteract)
                        <flux:callout>{{ __('interactions.verified_only') }}</flux:callout>
                    @endif

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

                    <details class="rounded-xl border p-4" style="border-color: var(--content-border); background: var(--content-surface)">
                        <summary class="cursor-pointer font-medium">{{ __('interactions.browse_discussion') }} · {{ $annotations->count() }}</summary>
                        <div class="mt-5 space-y-5">
                            @forelse ($annotations as $annotation)
                                <article wire:key="annotation-{{ $annotation->uuid }}" class="space-y-3 rounded-xl border p-4" style="border-color: var(--content-border); background: var(--content-bg)">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-app.actor-identity :actor="$annotation->author" size="xs" />
                                            <flux:badge size="sm">{{ __('interactions.kind.'.$annotation->kind) }}</flux:badge>
                                            @if ($annotation->visibility === 'private')
                                                <flux:badge size="sm">🔒 {{ __('interactions.visibility_value.private') }}</flux:badge>
                                            @endif
                                        </div>
                                        <div class="text-xs" style="color: var(--content-muted)">{{ $annotation->created_at->timezone($viewerTimezone)->format('Y-m-d H:i') }}</div>
                                    </div>
                                    @if ($annotation->body)
                                        <div class="whitespace-pre-wrap break-words text-sm leading-7" dir="auto">{{ $annotation->body }}</div>
                                    @endif
                                    @foreach ($annotation->assets as $attachment)
                                        @php
                                            $attachmentUrl = route('contexts.contents.assets.show', [$context, $content, $attachment]);
                                            $attachmentDownload = route('contexts.contents.assets.download', [$context, $content, $attachment]);
                                        @endphp
                                        <div class="rounded-lg border p-2" style="border-color: var(--content-border)">
                                            @if ($attachment->processing_status !== 'ready')
                                                <div class="text-sm">{{ __('interactions.attachment_processing') }}</div>
                                            @elseif ($attachment->mediaKind() === 'image')
                                                <img src="{{ $attachmentUrl }}" alt="{{ $attachment->pivot->caption ?: $attachment->original_filename }}" class="max-h-56 rounded object-contain" />
                                            @elseif ($attachment->mediaKind() === 'audio')
                                                <audio controls preload="metadata" class="w-full" src="{{ $attachmentUrl }}"></audio>
                                            @elseif ($attachment->mediaKind() === 'video')
                                                <video controls preload="metadata" class="max-h-64 w-full rounded bg-black" src="{{ $attachmentUrl }}"></video>
                                            @else
                                                <a href="{{ $attachmentDownload }}" class="font-medium" dir="auto">{{ $attachment->original_filename }}</a>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if ($canInteract)
                                        <button type="button" data-reply-annotation="{{ $annotation->uuid }}" class="text-sm font-medium underline underline-offset-4" style="color: var(--content-accent)">
                                            {{ $annotation->kind === 'question' ? __('interactions.answer') : __('interactions.reply') }}
                                        </button>
                                    @endif

                                    @if ($annotation->replies->isNotEmpty())
                                        <div class="space-y-3 border-s-2 ps-4" style="border-color: var(--content-border)">
                                            @foreach ($annotation->replies as $reply)
                                                <div wire:key="reply-{{ $reply->uuid }}" class="space-y-2 rounded-lg p-3" style="background: var(--content-surface)">
                                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                        <div class="flex items-center gap-2">
                                                            <x-app.actor-identity :actor="$reply->author" size="xs" />
                                                            <flux:badge size="sm">{{ __('interactions.kind.'.$reply->kind) }}</flux:badge>
                                                        </div>
                                                        <div class="text-xs" style="color: var(--content-muted)">{{ $reply->created_at->timezone($viewerTimezone)->format('Y-m-d H:i') }}</div>
                                                    </div>
                                                    @if ($reply->body)
                                                        <div class="whitespace-pre-wrap break-words text-sm leading-6" dir="auto">{{ $reply->body }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <div class="text-sm" style="color: var(--content-muted)">{{ __('interactions.no_comments') }}</div>
                            @endforelse
                        </div>
                    </details>
                </section>
            </div>
        </article>

        <aside class="space-y-4 self-start xl:sticky xl:top-6">
            @if ($canInteract)
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('interactions.markers_title') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('interactions.markers_help') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([
                            'all' => __('interactions.marker_filter.all'),
                            'mine' => __('interactions.marker_filter.mine'),
                            'questions' => __('interactions.marker_filter.questions'),
                            'hidden' => __('interactions.marker_filter.hidden'),
                        ] as $filter => $label)
                            <flux:button wire:click="setMarkerFilter('{{ $filter }}')" size="sm" :variant="$markerFilter === $filter ? 'primary' : 'ghost'">{{ $label }}</flux:button>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            @if (count($outline) > 0)
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('reader.outline') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('reader.outline_help') }}</flux:text>
                    </div>
                    <x-app.context-content-outline-tree :items="$outline" :context="$context" />
                </flux:card>
            @endif

            @if ($canInteract && $relationships->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('interactions.annotate_outline') }}</flux:heading>
                    @foreach ($relationships as $relationship)
                        @php $childRevision = $relationship->childContent->activeRevision; @endphp
                        <div class="flex items-center justify-between gap-2 rounded-lg border p-2" style="border-color: var(--content-border)">
                            <span class="min-w-0 truncate text-sm" dir="auto">{{ $childRevision?->title ?? __('reader.outline') }}</span>
                            <x-app.annotation-target-menu target-type="relationship" :target-uuid="$relationship->uuid" :label="$childRevision?->title ?? __('reader.outline')" />
                        </div>
                    @endforeach
                </flux:card>
            @endif
        </aside>
    </div>

    @if ($canInteract && count($selectedTargets) > 0)
        <div class="fixed bottom-4 start-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full border px-3 py-2 shadow-xl" style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)">
            <span class="text-sm font-medium">{{ trans_choice('interactions.selected_count', count($selectedTargets), ['count' => count($selectedTargets)]) }}</span>
            <flux:button wire:click="openSelectionComposer('note', 24, 80)" size="sm" variant="primary">{{ __('interactions.quick.note') }}</flux:button>
            <flux:button wire:click="openSelectionComposer('question', 24, 80)" size="sm" variant="ghost">{{ __('interactions.quick.question') }}</flux:button>
            <flux:button wire:click="clearTargetSelection" size="sm" variant="ghost">{{ __('interactions.clear_selection') }}</flux:button>
        </div>
    @endif

    <div
        id="annotation-selection-toolbar-{{ $content->uuid }}"
        class="fixed z-50 hidden max-w-[calc(100vw-1.5rem)] rounded-xl border p-1.5 shadow-2xl sm:w-max"
        style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
        role="toolbar"
        aria-label="{{ __('interactions.selection_actions') }}"
    >
        <div class="flex flex-wrap gap-1">
            @foreach ([
                'remember' => __('interactions.quick.remember'),
                'note' => __('interactions.quick.note'),
                'question' => __('interactions.quick.question'),
                'comment' => __('interactions.quick.comment'),
            ] as $purpose => $label)
                <button type="button" data-selection-purpose="{{ $purpose }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">{{ $label }}</button>
            @endforeach
            <button type="button" data-selection-select class="rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">{{ __('interactions.quick.add_to_selection') }}</button>
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:outline-none dark:hover:bg-white/10 [&::-webkit-details-marker]:hidden">{{ __('interactions.quick.more') }}</summary>
                <div class="absolute end-0 mt-2 w-44 rounded-xl border p-1.5 shadow-xl" style="border-color: var(--content-border); background: var(--content-surface)">
                    @foreach ([
                        'translate' => __('interactions.quick.translate'),
                        'file' => __('interactions.quick.file'),
                        'voice' => __('interactions.quick.voice'),
                        'advanced' => __('interactions.quick.advanced'),
                    ] as $purpose => $label)
                        <button type="button" data-selection-purpose="{{ $purpose }}" class="block w-full rounded-lg px-3 py-2 text-start text-xs hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">{{ $label }}</button>
                    @endforeach
                </div>
            </details>
        </div>
    </div>

    @if ($canInteract)
        <section
            id="annotation-context-composer-{{ $content->uuid }}"
            class="fixed z-50 w-[22rem] max-w-[calc(100vw-1.5rem)] rounded-2xl border p-4 shadow-2xl {{ $annotationComposerOpen ? '' : 'hidden' }}"
            style="left: {{ $annotationComposerX }}px; top: {{ $annotationComposerY }}px; border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
            data-annotation-composer
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="font-semibold">{{ __('interactions.quick.'.$annotationComposerMode) }}</div>
                    <div class="mt-1 text-xs" style="color: var(--content-muted)">
                        {{ $annotationVisibility === 'private' ? '🔒 '.__('interactions.visibility_value.private') : __('interactions.visibility_value.space') }}
                    </div>
                </div>
                <button type="button" data-close-composer class="rounded-full px-2 py-1 text-sm focus:outline-none focus:ring-2">×</button>
            </div>

            <div class="mt-3 flex max-h-20 flex-wrap gap-1 overflow-auto">
                @foreach ($annotationAnchors as $index => $anchor)
                    @if ($annotationParentUuid)
                        <span class="rounded-full border px-2 py-1 text-[11px]" style="border-color: var(--content-border)">{{ $anchor['selector']['label'] ?? __('interactions.anchor.'.$anchor['target_type']) }}</span>
                    @else
                        <button type="button" wire:click="removeAnnotationAnchor({{ $index }})" class="rounded-full border px-2 py-1 text-[11px]" style="border-color: var(--content-border)">{{ $anchor['selector']['label'] ?? __('interactions.anchor.'.$anchor['target_type']) }} ×</button>
                    @endif
                @endforeach
            </div>

            @if ($annotationComposerMode === 'advanced')
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <flux:select wire:model="annotationKind" :label="__('interactions.role')" :disabled="$annotationParentUuid !== null">
                        @foreach ($annotationKinds as $kind)<option value="{{ $kind }}">{{ __('interactions.kind.'.$kind) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="annotationVisibility" :label="__('interactions.visibility')" :disabled="$annotationParentUuid !== null">
                        @foreach ($annotationVisibilities as $visibility)<option value="{{ $visibility }}">{{ __('interactions.visibility_value.'.$visibility) }}</option>@endforeach
                    </flux:select>
                </div>
            @elseif (! $annotationParentUuid)
                <div class="mt-3 flex justify-end">
                    <button type="button" wire:click="$set('annotationVisibility', '{{ $annotationVisibility === 'private' ? 'space' : 'private' }}')" class="text-xs underline underline-offset-4" style="color: var(--content-muted)">
                        {{ $annotationVisibility === 'private' ? __('interactions.make_space_visible') : __('interactions.make_private') }}
                    </button>
                </div>
            @endif

            @if (! in_array($annotationComposerMode, ['file', 'voice'], true))
                <div class="mt-3">
                    <textarea
                        wire:model="annotationBody"
                        rows="{{ in_array($annotationComposerMode, ['remember', 'note', 'translate'], true) ? 2 : 3 }}"
                        maxlength="5000"
                        placeholder="{{ __('interactions.placeholder.'.$annotationComposerMode) }}"
                        class="w-full rounded-xl border bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        style="border-color: var(--content-border)"
                        dir="auto"
                    ></textarea>
                    @error('annotationBody')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
            @endif

            @if (in_array($annotationComposerMode, ['answer', 'reply'], true) && $annotationMedium === 'text')
                <div class="mt-2 flex gap-2">
                    <button type="button" wire:click="setComposerMedium('file')" class="text-xs underline underline-offset-4">{{ __('interactions.quick.file') }}</button>
                    <button type="button" wire:click="setComposerMedium('voice')" class="text-xs underline underline-offset-4">{{ __('interactions.quick.voice') }}</button>
                </div>
            @endif

            @if ($annotationComposerMode === 'advanced')
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['text', 'file', 'voice'] as $medium)
                        <flux:button wire:click="setComposerMedium('{{ $medium }}')" size="sm" :variant="$annotationMedium === $medium ? 'primary' : 'ghost'">{{ __('interactions.medium.'.$medium) }}</flux:button>
                    @endforeach
                </div>
            @endif

            <div class="mt-3 {{ in_array($annotationMedium, ['file', 'advanced'], true) || $annotationComposerMode === 'file' ? '' : 'hidden' }}">
                <input type="file" wire:model="annotationUpload" class="block w-full text-sm" />
                @error('annotationUpload')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                @if ($annotationUpload)
                    <div class="mt-2 space-y-2">
                        <flux:select wire:model="annotationRightsStatus" :label="__('media.rights_status')">
                            @foreach ($rightsStatuses as $status)<option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>@endforeach
                        </flux:select>
                        <flux:input wire:model="annotationCaption" :label="__('media.caption')" maxlength="1000" />
                    </div>
                @endif
            </div>

            <div class="mt-3 {{ in_array($annotationMedium, ['voice', 'advanced'], true) || $annotationComposerMode === 'voice' ? '' : 'hidden' }}">
                <div id="annotation-audio-recorder-{{ $content->uuid }}" wire:ignore class="flex flex-wrap items-center gap-2 rounded-xl border p-3" style="border-color: var(--content-border)">
                    <flux:button id="annotation-audio-start-{{ $content->uuid }}" type="button" size="sm" variant="primary">{{ __('media.start_recording') }}</flux:button>
                    <flux:button id="annotation-audio-stop-{{ $content->uuid }}" type="button" size="sm" variant="danger" class="hidden">{{ __('media.stop_recording') }}</flux:button>
                    <span id="annotation-audio-status-{{ $content->uuid }}" class="hidden text-xs" style="color: var(--content-muted)"></span>
                    <span id="annotation-audio-timer-{{ $content->uuid }}" class="hidden text-xs">00:00</span>
                </div>
                <p id="annotation-audio-error-{{ $content->uuid }}" class="mt-1 hidden text-xs text-red-600"></p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                @if ($annotationComposerMode !== 'advanced')
                    <button type="button" wire:click="$set('annotationComposerMode', 'advanced')" class="text-xs underline underline-offset-4" style="color: var(--content-muted)">{{ __('interactions.quick.more') }}</button>
                @endif
                <flux:button wire:click="postAnnotation" wire:loading.attr="disabled" wire:target="postAnnotation,annotationUpload" size="sm" variant="primary">
                    {{ in_array($annotationComposerMode, ['answer', 'reply'], true) ? __('interactions.post_'.$annotationComposerMode) : __('interactions.save') }}
                </flux:button>
            </div>
        </section>
    @endif

    @if ($previewAnnotations->isNotEmpty())
        <aside
            id="annotation-preview-panel-{{ $content->uuid }}"
            class="fixed end-4 top-20 z-50 max-h-[70vh] w-[22rem] max-w-[calc(100vw-1.5rem)] overflow-auto rounded-2xl border p-4 shadow-2xl"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold">{{ __('interactions.marker_preview') }}</div>
                <flux:button wire:click="closeMarkerPreview" size="sm" variant="ghost">×</flux:button>
            </div>
            <div class="mt-4 space-y-3">
                @foreach ($previewAnnotations as $annotation)
                    <article class="rounded-xl border p-3" style="border-color: var(--content-border)">
                        <div class="flex flex-wrap items-center gap-2 text-xs" style="color: var(--content-muted)">
                            <x-app.actor-identity :actor="$annotation->author" size="xs" />
                            <flux:badge size="sm">{{ __('interactions.kind.'.$annotation->kind) }}</flux:badge>
                            @if ($annotation->visibility === 'private')<span>🔒</span>@endif
                        </div>
                        @if ($annotation->body)<div class="mt-2 whitespace-pre-wrap text-sm" dir="auto">{{ \Illuminate\Support\Str::limit($annotation->body, 320) }}</div>@endif
                        @if ($canInteract)
                            <button type="button" data-reply-annotation="{{ $annotation->uuid }}" class="mt-2 text-xs font-medium underline underline-offset-4" style="color: var(--content-accent)">{{ $annotation->kind === 'question' ? __('interactions.answer') : __('interactions.reply') }}</button>
                        @endif
                    </article>
                @endforeach
            </div>
        </aside>
    @endif
</section>

@if ($canInteract)
    @script
    <script>
        (() => {
            const suffix = @js((string) $content->uuid);
            const root = document.getElementById(`content-reader-${suffix}`)
            const selectionToolbar = document.getElementById(`annotation-selection-toolbar-${suffix}`)
            const composer = document.getElementById(`annotation-context-composer-${suffix}`)
            let pendingAnchor = null
            let pendingPosition = { x: 24, y: 80 }
            let observer = null
            let highlightTimer = null

            const codePointLength = value => Array.from(value || '').length
            const cpToUtf16 = (value, offset) => Array.from(value || '').slice(0, offset).join('').length
            const clamp = (value, min, max) => Math.max(min, Math.min(max, value))

            const targetAnchor = element => {
                if (!element) return null
                const type = element.dataset.targetType
                const selector = { label: element.dataset.targetLabel || '' }
                if (type === 'field') return { target_type: 'field', target_uuid: null, field_key: element.dataset.fieldKey || null, selector }
                if (type === 'block') return { target_type: 'block', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                if (type === 'asset') return { target_type: 'asset', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                if (type === 'relationship') return { target_type: 'relationship', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                if (type === 'revision') return { target_type: 'revision', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                return null
            }

            const positionNear = rect => {
                if (!rect) return { x: 24, y: 80 }
                const width = 360
                return {
                    x: clamp(Math.round(rect.left), 12, Math.max(12, window.innerWidth - width - 12)),
                    y: clamp(Math.round(rect.bottom + 10), 12, Math.max(12, window.innerHeight - 260)),
                }
            }

            const hideSelectionToolbar = () => {
                selectionToolbar?.classList.add('hidden')
                pendingAnchor = null
            }

            const segmentForNode = node => {
                const element = node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement
                return element?.closest?.('[data-annotation-text]') ?? null
            }

            const offsetWithin = (segment, container, offset) => {
                const range = document.createRange()
                range.selectNodeContents(segment)
                try {
                    range.setEnd(container, offset)
                } catch (_) {
                    return null
                }
                return codePointLength(range.toString())
            }

            const captureSelection = () => {
                if (!selectionToolbar || !root) return
                const selection = window.getSelection()
                if (!selection || selection.isCollapsed || !selection.rangeCount) {
                    hideSelectionToolbar()
                    return
                }

                const exact = selection.toString()
                if (!exact.trim() || codePointLength(exact) > 2000) {
                    hideSelectionToolbar()
                    return
                }

                const range = selection.getRangeAt(0)
                const startSegment = segmentForNode(range.startContainer)
                const endSegment = segmentForNode(range.endContainer)
                if (!startSegment || !endSegment || !root.contains(startSegment) || !root.contains(endSegment)) {
                    hideSelectionToolbar()
                    return
                }

                const startTarget = startSegment.dataset.anchorTarget
                const endTarget = endSegment.dataset.anchorTarget
                const startUuid = startSegment.dataset.targetUuid || ''
                const endUuid = endSegment.dataset.targetUuid || ''
                const startField = startSegment.dataset.fieldKey || ''
                const endField = endSegment.dataset.fieldKey || ''
                if (startTarget !== endTarget || startUuid !== endUuid || startField !== endField) {
                    hideSelectionToolbar()
                    return
                }

                const startLocal = offsetWithin(startSegment, range.startContainer, range.startOffset)
                const endLocal = offsetWithin(endSegment, range.endContainer, range.endOffset)
                if (startLocal === null || endLocal === null) {
                    hideSelectionToolbar()
                    return
                }

                const start = Number(startSegment.dataset.anchorBase || 0) + startLocal
                const end = Number(endSegment.dataset.anchorBase || 0) + endLocal
                if (end <= start) {
                    hideSelectionToolbar()
                    return
                }

                const selector = { exact, start, end, label: exact.trim().slice(0, 80) }
                pendingAnchor = startTarget === 'field'
                    ? { target_type: 'text', target_uuid: null, field_key: startField, selector }
                    : { target_type: 'block', target_uuid: startUuid, field_key: null, selector }

                const rect = range.getBoundingClientRect()
                pendingPosition = positionNear(rect)
                if (window.innerWidth >= 640) {
                    selectionToolbar.style.left = `${pendingPosition.x}px`
                    selectionToolbar.style.top = `${pendingPosition.y}px`
                }
                selectionToolbar.classList.remove('hidden')
            }

            const openComposer = (purpose, anchor, position = pendingPosition) => {
                if (!anchor) return
                hideSelectionToolbar()
                window.getSelection()?.removeAllRanges()
                $wire.call('openContextComposer', purpose, anchor, position.x, position.y)
            }

            const addToSelection = anchor => {
                if (!anchor) return
                hideSelectionToolbar()
                window.getSelection()?.removeAllRanges()
                $wire.call('addTargetToSelection', anchor)
            }

            const parseMarkerPayload = () => {
                const payload = document.getElementById(`annotation-marker-payload-${suffix}`)?.dataset.markerPayload
                if (!payload) return []
                try { return JSON.parse(payload) } catch (_) { return [] }
            }

            const textNodes = element => {
                const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT)
                const nodes = []
                let node
                while ((node = walker.nextNode())) nodes.push(node)
                return nodes
            }

            const pointAt = (element, cpOffset) => {
                let remaining = cpOffset
                for (const node of textNodes(element)) {
                    const length = codePointLength(node.data)
                    if (remaining <= length) return { node, offset: cpToUtf16(node.data, remaining) }
                    remaining -= length
                }
                return null
            }

            const unwrapHighlights = () => {
                root?.querySelectorAll('mark[data-content-annotation-mark]').forEach(mark => {
                    mark.replaceWith(document.createTextNode(mark.textContent || ''))
                    mark.parentNode?.normalize?.()
                })
            }

            const applyHighlights = () => {
                if (!root) return
                observer?.disconnect()
                unwrapHighlights()

                const markers = parseMarkerPayload().sort((a, b) => Number(b.start || 0) - Number(a.start || 0))
                for (const marker of markers) {
                    const selector = marker.target_type === 'text'
                        ? `[data-annotation-text][data-anchor-target="field"][data-field-key="${CSS.escape(marker.field_key || '')}"]`
                        : `[data-annotation-text][data-anchor-target="block"][data-target-uuid="${CSS.escape(marker.target_uuid || '')}"]`
                    const segments = Array.from(root.querySelectorAll(selector))
                    for (const segment of segments) {
                        const base = Number(segment.dataset.anchorBase || 0)
                        const length = codePointLength(segment.textContent || '')
                        const localStart = Math.max(0, Number(marker.start) - base)
                        const localEnd = Math.min(length, Number(marker.end) - base)
                        if (localEnd <= localStart || localStart >= length || localEnd <= 0) continue

                        const startPoint = pointAt(segment, localStart)
                        const endPoint = pointAt(segment, localEnd)
                        if (!startPoint || !endPoint) continue

                        const range = document.createRange()
                        range.setStart(startPoint.node, startPoint.offset)
                        range.setEnd(endPoint.node, endPoint.offset)
                        const mark = document.createElement('mark')
                        mark.dataset.contentAnnotationMark = '1'
                        mark.dataset.annotationUuids = JSON.stringify(marker.annotation_uuids || [])
                        mark.tabIndex = 0
                        mark.setAttribute('role', 'button')
                        mark.setAttribute('aria-label', @js(__('interactions.open_annotations')))
                        mark.style.background = marker.private
                            ? 'color-mix(in srgb, var(--content-accent) 14%, transparent)'
                            : 'color-mix(in srgb, var(--content-accent) 24%, transparent)'
                        mark.style.borderBottom = marker.question
                            ? '2px dotted var(--content-accent)'
                            : '2px solid color-mix(in srgb, var(--content-accent) 55%, transparent)'
                        mark.style.color = 'inherit'
                        mark.style.cursor = 'pointer'
                        try { range.surroundContents(mark) } catch (_) { /* keep reading surface intact */ }
                    }
                }

                observer?.observe(root, { childList: true, subtree: true })
            }

            if (root?.dataset.contextualAnnotationsReady !== '1') {
                root.dataset.contextualAnnotationsReady = '1'

                const scheduleSelection = () => window.setTimeout(captureSelection, 30)
                document.addEventListener('pointerup', scheduleSelection)
                document.addEventListener('keyup', event => {
                    if (event.key === 'Shift' || event.shiftKey) scheduleSelection()
                })
                document.addEventListener('selectionchange', () => window.setTimeout(captureSelection, 80))

                document.addEventListener('click', event => {
                    const marker = event.target.closest?.('[data-marker-preview], mark[data-content-annotation-mark]')
                    if (marker) {
                        let uuids = []
                        try { uuids = JSON.parse(marker.dataset.markerPreview || marker.dataset.annotationUuids || '[]') } catch (_) {}
                        if (Array.isArray(uuids) && uuids.length) $wire.call('openMarkerPreview', uuids)
                        return
                    }

                    const selectionPurpose = event.target.closest?.('[data-selection-purpose]')
                    if (selectionPurpose && pendingAnchor) {
                        openComposer(selectionPurpose.dataset.selectionPurpose, pendingAnchor)
                        return
                    }
                    if (event.target.closest?.('[data-selection-select]') && pendingAnchor) {
                        addToSelection(pendingAnchor)
                        return
                    }

                    const purposeButton = event.target.closest?.('[data-context-purpose]')
                    if (purposeButton) {
                        const menu = purposeButton.closest('[data-annotation-target-menu]')
                        const anchor = targetAnchor(menu)
                        openComposer(purposeButton.dataset.contextPurpose, anchor, positionNear(purposeButton.getBoundingClientRect()))
                        menu?.querySelectorAll('details[open]').forEach(details => details.removeAttribute('open'))
                        return
                    }
                    const selectButton = event.target.closest?.('[data-context-select]')
                    if (selectButton) {
                        const menu = selectButton.closest('[data-annotation-target-menu]')
                        addToSelection(targetAnchor(menu))
                        menu?.querySelectorAll('details[open]').forEach(details => details.removeAttribute('open'))
                        return
                    }

                    const reply = event.target.closest?.('[data-reply-annotation]')
                    if (reply) {
                        const position = positionNear(reply.getBoundingClientRect())
                        $wire.call('openReplyComposer', reply.dataset.replyAnnotation, position.x, position.y)
                    }
                })

                document.addEventListener('keydown', event => {
                    const marker = event.target.closest?.('mark[data-content-annotation-mark]')
                    if (marker && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault()
                        let uuids = []
                        try { uuids = JSON.parse(marker.dataset.annotationUuids || '[]') } catch (_) {}
                        if (uuids.length) $wire.call('openMarkerPreview', uuids)
                        return
                    }
                    if (event.key === 'Escape') {
                        hideSelectionToolbar()
                        if (composer && !composer.classList.contains('hidden')) {
                            const body = composer.querySelector('textarea')?.value?.trim() || ''
                            const file = composer.querySelector('input[type="file"]')?.files?.length || 0
                            if (!body && !file) $wire.call('clearAnnotationComposer')
                            else if (window.confirm(@js(__('interactions.discard_draft_confirm')))) $wire.call('clearAnnotationComposer')
                        }
                    }
                })

                document.addEventListener('pointerdown', event => {
                    if (!composer || composer.classList.contains('hidden')) return
                    if (composer.contains(event.target) || event.target.closest?.('[data-context-menu], #annotation-selection-toolbar-'+suffix)) return
                    const body = composer.querySelector('textarea')?.value?.trim() || ''
                    const file = composer.querySelector('input[type="file"]')?.files?.length || 0
                    if (!body && !file) $wire.call('clearAnnotationComposer')
                })

                document.querySelector('[data-close-composer]')?.addEventListener('click', () => {
                    const body = composer?.querySelector('textarea')?.value?.trim() || ''
                    const file = composer?.querySelector('input[type="file"]')?.files?.length || 0
                    if (!body && !file) $wire.call('clearAnnotationComposer')
                    else if (window.confirm(@js(__('interactions.discard_draft_confirm')))) $wire.call('clearAnnotationComposer')
                })
            }

            observer = new MutationObserver(() => {
                clearTimeout(highlightTimer)
                highlightTimer = setTimeout(applyHighlights, 25)
            })
            observer.observe(root, { childList: true, subtree: true })
            applyHighlights()

            const recorderRoot = document.getElementById(`annotation-audio-recorder-${suffix}`)
            if (!recorderRoot || recorderRoot.dataset.recorderReady === '1') return
            recorderRoot.dataset.recorderReady = '1'

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

            const show = (element, visible) => element?.classList.toggle('hidden', !visible)
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
                        if (status) {
                            status.textContent = @js(__('interactions.uploading_voice_note'));
                        }
                        show(status, true); show(timerLabel, false); show(stop, false)
                        $wire.upload('annotationUpload', file, () => {
                            $wire.call('markAnnotationRecordingReady').then(() => {
                                if (status) {
                                    status.textContent = @js(__('interactions.voice_note_ready'));
                                }
                                show(start, true)
                            })
                        }, () => {
                            setError(@js(__('media.recording_error')))
                            show(start, true); show(status, false)
                        })
                    }, { once: true })
                    recorder.start()
                    show(start, false); show(stop, true); show(status, true); show(timerLabel, true)
                    if (status) {
                        status.textContent = @js(__('media.recording'));
                    }
                    timer = setInterval(() => { elapsed++; updateTimer() }, 1000)
                } catch (_) {
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
