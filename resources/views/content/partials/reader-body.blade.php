{{--
    The body of a published edition: blocks (or fields), then any media the
    document does not place inline. Shared by the signed-in reader and the
    guest reader. Expects the ContentReaderStyle variables plus $revision,
    $blocks, $definitionVersion, $mediaByPlacement, $mediaExtras,
    $assetUrlFor / $downloadUrlFor (closures), $canAnnotate and the marker maps.
--}}
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
            $section = \App\Support\Content\SectionSemantics::of($block);
            $sectionRole = $section['role'];
            $sectionAnswered = ($sectionAnswers ?? [])[$block->uuid] ?? null;
        @endphp

        <section
            wire:key="reader-block-{{ $block->uuid }}"
            class="group relative {{ $alignment }} {{ $emphasisClass }} {{ $radiusClass }} {{ $canAnnotate && ! in_array($block->type, ['divider', 'field', 'image', 'audio', 'video', 'file'], true) ? 'max-xl:pe-9' : '' }}"
            style="{{ $blockInlineStyle }}"
            data-annotation-target="block"
            data-target-type="block"
            data-target-uuid="{{ $block->uuid }}"
            data-target-label="{{ $sectionRole ? __('sections.roles.'.$sectionRole.'.name') : __('blocks.type.'.$block->type) }}"
            @if ($sectionRole) data-section-role="{{ $sectionRole }}" @endif
            @if ($section['tags'] !== []) data-section-tags="{{ implode(' ', $section['tags']) }}" @endif
        >
            @if ($sectionRole)
                <div class="mb-2 flex flex-wrap items-center gap-1.5 text-xs" data-section-badge>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium" style="color: var(--content-accent); background: color-mix(in srgb, var(--content-accent) 12%, transparent)">
                        <flux:icon :name="\App\Support\Content\SectionSemantics::ROLES[$sectionRole]['icon']" class="size-3.5" />
                        {{ __('sections.roles.'.$sectionRole.'.name') }}
                    </span>
                    @foreach ($section['tags'] as $tag)
                        <span class="rounded-full border px-1.5 py-0.5" style="border-color: var(--content-border); color: var(--content-muted)" dir="auto">#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
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
                        <div id="field-{{ $fieldKey }}" class="group/field relative scroll-mt-24 space-y-2" data-annotation-target="field" data-target-type="field" data-field-key="{{ $fieldKey }}" data-target-label="{{ $blockData['label'] ?? $field['label'] ?? $fieldKey }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--content-muted)" dir="auto">{{ $blockData['label'] ?? $field['label'] ?? $fieldKey }}</div>
                                @if ($canAnnotate)
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
                            $assetUrl = $assetUrlFor($asset);
                            $downloadUrl = $downloadUrlFor($asset);
                        @endphp
                        <figure class="group/media relative space-y-3 {{ $mediaStyle === 'card' ? 'border p-3 '.$radiusClass : '' }}" style="border-color: var(--content-border)" data-annotation-target="asset" data-target-type="asset" data-target-uuid="{{ $placementUuid }}" data-target-label="{{ $blockData['caption'] ?? $asset->original_filename }}">
                            <div class="absolute end-2 top-2 z-10 opacity-0 transition group-hover/media:opacity-100 group-focus-within/media:opacity-100">
                                @if ($canAnnotate)
                                    <x-app.annotation-target-menu target-type="asset" :target-uuid="$placementUuid" :label="$blockData['caption'] ?? $asset->original_filename" :marker-uuids="$assetMarkers" />
                                @endif
                            </div>
                            @if ($block->type === 'image')
                                <img src="{{ $assetUrl }}" alt="{{ $asset->alt_text ?: ($blockData['caption'] ?? $asset->original_filename) }}" class="mx-auto max-h-[42rem] w-auto {{ $radiusClass }} object-contain" loading="lazy" />
                            @elseif ($block->type === 'audio')
                                <x-media.video-player audio :src="$assetUrl" :tracks="$mediaExtras[$asset->id]['tracks'] ?? []" :duration-ms="$mediaExtras[$asset->id]['duration_ms'] ?? null" />
                            @elseif ($block->type === 'video')
                                <x-media.video-player :src="$assetUrl" :poster="$mediaExtras[$asset->id]['poster'] ?? null" :tracks="$mediaExtras[$asset->id]['tracks'] ?? []" :duration-ms="$mediaExtras[$asset->id]['duration_ms'] ?? null" :media-class="'max-h-[42rem] w-full '.$radiusClass.' bg-black'" />
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

            @if (\App\Support\Content\SectionSemantics::isAnswerable($sectionRole) && (($canAnswerSections ?? false) || $sectionAnswered))
                <div
                    class="mt-3 flex flex-wrap items-center gap-2"
                    data-section-answers
                    data-annotation-target-menu
                    data-target-type="block"
                    data-target-uuid="{{ $block->uuid }}"
                    data-target-label="{{ __('sections.roles.'.$sectionRole.'.name') }}"
                >
                    @if ($canAnswerSections ?? false)
                        <button type="button" data-context-purpose="answer" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2" style="background: var(--content-accent)" data-section-answer>
                            <flux:icon name="pencil" class="size-4" />
                            {{ ($sectionAnswered['mine'] ?? false) ? __('sections.answer_again') : __('sections.answer') }}
                        </button>
                    @endif
                    @if ($sectionAnswered)
                        <button type="button" data-marker-preview='@json($sectionAnswered['uuids'])' class="inline-flex min-h-9 items-center gap-1 rounded-full border px-3 text-sm focus:outline-none focus:ring-2" style="border-color: var(--content-border); color: var(--content-muted)" data-section-answer-count>
                            {{ trans_choice('sections.answer_count', count($sectionAnswered['uuids']), ['count' => count($sectionAnswered['uuids'])]) }}
                            @if (($sectionAnswered['correct'] ?? 0) > 0)
                                <span class="text-green-700 dark:text-green-400">· {{ trans_choice('sections.correct_count', $sectionAnswered['correct'], ['count' => $sectionAnswered['correct']]) }}</span>
                            @endif
                        </button>
                    @endif
                </div>
            @endif

            @if ($canAnnotate && $block->type !== 'divider' && ! in_array($block->type, ['field', 'image', 'audio', 'video', 'file'], true))
                <div class="absolute end-1 top-1 opacity-60 transition group-hover:opacity-100 group-focus-within:opacity-100 xl:-end-10 xl:top-0 xl:opacity-0 [@media(hover:none)]:opacity-100">
                    <x-app.annotation-target-menu target-type="block" :target-uuid="$block->uuid" :label="$sectionRole ? __('sections.roles.'.$sectionRole.'.name') : __('blocks.type.'.$block->type)" :marker-uuids="$blockMarkers" :role="$sectionRole" />
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
                id="field-{{ $field['key'] }}"
                class="group relative scroll-mt-24 space-y-2 {{ ($fieldStyle['emphasis'] ?? null) === 'callout' ? 'border-s-4 ps-4' : '' }} {{ ($fieldStyle['emphasis'] ?? null) === 'strong' ? 'font-semibold' : '' }} {{ ($fieldStyle['emphasis'] ?? null) === 'muted' ? 'opacity-70' : '' }} {{ $radiusClass }}"
                style="{{ $fieldInline }}; {{ ($fieldStyle['emphasis'] ?? null) === 'callout' ? 'border-color: '.($fieldStyle['accent_color'] ?? $readerAccent) : '' }}"
                data-annotation-target="field"
                data-target-type="field"
                data-field-key="{{ $field['key'] }}"
                data-target-label="{{ $field['label'] }}"
            >
                <div class="flex items-start justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide" style="color: var(--content-muted)" dir="auto">{{ $field['label'] }}</h2>
                    @if ($canAnnotate)
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

@php
    // Media the document does not place inline is still shown, so
    // nothing attached to an edition is invisible to its readers.
    $placedAssetUuids = $revision->composition_mode === \App\Models\SpaceContentRevision::COMPOSITION_BLOCKS
        ? $blocks->map(fn ($block) => is_array($block->data) ? ($block->data['asset_uuid'] ?? null) : null)->filter()->all()
        : [];
    $listedAssets = $revision->assets->reject(fn ($asset) => in_array($asset->uuid, $placedAssetUuids, true));
@endphp
@if ($listedAssets->isNotEmpty())
    <section class="space-y-5 border-t pt-7" style="border-color: var(--content-border)">
        <h2 class="text-xl {{ $headingClass }}">{{ __('reader.media') }}</h2>
        @foreach ($listedAssets as $asset)
            @php
                $assetUrl = $assetUrlFor($asset);
                $downloadUrl = $downloadUrlFor($asset);
                $caption = $asset->pivot->caption;
                $placementUuid = (string) $asset->pivot->uuid;
                $assetMarkers = $assetMarkerUuids[$placementUuid] ?? [];
            @endphp
            <figure class="group relative space-y-3 {{ $mediaStyle === 'card' ? 'border p-3 sm:p-4 '.$radiusClass : '' }}" style="border-color: var(--content-border)" data-annotation-target="asset" data-target-type="asset" data-target-uuid="{{ $placementUuid }}" data-target-label="{{ $caption ?: $asset->original_filename }}">
                <div class="absolute end-2 top-2 z-10 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                    @if ($canAnnotate)
                        <x-app.annotation-target-menu target-type="asset" :target-uuid="$placementUuid" :label="$caption ?: $asset->original_filename" :marker-uuids="$assetMarkers" />
                    @endif
                </div>
                @switch($asset->mediaKind())
                    @case('image')
                        <img src="{{ $assetUrl }}" alt="{{ $asset->alt_text ?: ($caption ?: $asset->original_filename) }}" class="mx-auto max-h-[42rem] w-auto {{ $radiusClass }} object-contain" loading="lazy" />
                        @break
                    @case('audio')
                        <x-media.video-player audio :src="$assetUrl" :tracks="$mediaExtras[$asset->id]['tracks'] ?? []" :duration-ms="$mediaExtras[$asset->id]['duration_ms'] ?? null" />
                        @break
                    @case('video')
                        <x-media.video-player :src="$assetUrl" :poster="$mediaExtras[$asset->id]['poster'] ?? null" :tracks="$mediaExtras[$asset->id]['tracks'] ?? []" :duration-ms="$mediaExtras[$asset->id]['duration_ms'] ?? null" :media-class="'max-h-[42rem] w-full '.$radiusClass.' bg-black'" />
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
