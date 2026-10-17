{{--
    One place that turns an already-authorized Asset URL into the right inline media.
    Callers own authorization and the URLs; this component only presents.

    compact   → card-sized preview (Planner evidence, lists)
    mediaClass → overrides the media element classes (Content Reader theming)
--}}
@props([
    'asset',
    'inlineUrl',
    'downloadUrl' => null,
    'caption' => null,
    'compact' => false,
    'mediaClass' => null,
])

@php
    $kind = $asset->mediaKind();
    $label = $asset->alt_text ?: ($caption ?: $asset->original_filename);
    $size = \Illuminate\Support\Number::fileSize((int) $asset->byte_size, precision: 1);
    $extension = strtoupper($asset->extension ?: pathinfo($asset->original_filename, PATHINFO_EXTENSION) ?: 'FILE');
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0', 'data-asset-preview' => $kind]) }}>
    @switch($kind)
        @case('image')
            <img
                src="{{ $inlineUrl }}"
                alt="{{ $label }}"
                class="{{ $mediaClass ?? ($compact ? 'h-44 w-full rounded-lg bg-zinc-100 object-cover dark:bg-zinc-900' : 'mx-auto max-h-[42rem] w-auto rounded-lg object-contain') }}"
                loading="lazy"
            />
            @break
        @case('audio')
            <audio controls preload="metadata" class="{{ $mediaClass ?? 'w-full' }}" src="{{ $inlineUrl }}"></audio>
            @break
        @case('video')
            <video controls preload="metadata" playsinline class="{{ $mediaClass ?? ($compact ? 'aspect-video w-full rounded-lg bg-black' : 'max-h-[42rem] w-full rounded-lg bg-black') }}" src="{{ $inlineUrl }}"></video>
            @break
        @case('pdf')
            @if ($compact)
                <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="flex h-44 flex-col items-center justify-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-center hover:bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                    <flux:icon name="document-text" class="size-10 text-red-500" />
                    <span class="text-sm font-medium">{{ __('media.preview.open_document') }}</span>
                </a>
            @else
                <object data="{{ $inlineUrl }}" type="application/pdf" class="{{ $mediaClass ?? 'h-[75vh] w-full rounded-lg border border-zinc-200 dark:border-zinc-800' }}">
                    <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="block p-5 font-medium" dir="auto">{{ __('media.preview.open_document') }} · {{ $asset->original_filename }} ↗</a>
                </object>
            @endif
            @break
        @default
            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900 {{ $compact ? 'h-44 flex-col justify-center text-center' : '' }}">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-white text-xs font-semibold text-zinc-600 shadow-sm dark:bg-zinc-800 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($extension, 4, '') }}</span>
                <span class="min-w-0 text-sm">
                    <span class="block break-all font-medium" dir="auto">{{ $asset->original_filename }}</span>
                    <span class="block text-xs text-zinc-500">{{ $asset->mime_type }} · {{ $size }}</span>
                </span>
                @if ($downloadUrl && ! $compact)
                    <flux:button :href="$downloadUrl" size="sm" variant="ghost" icon="arrow-down-tray" class="ms-auto">{{ __('media.download') }}</flux:button>
                @endif
            </div>
    @endswitch

    @if ($caption && ! $compact)
        <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-400" dir="auto">{{ $caption }}</p>
    @endif
</div>
