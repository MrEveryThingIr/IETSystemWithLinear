{{-- Document outline: jump to fields, headings and media. --}}
<div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('studio_ui.outline') }}</div>
<ol class="space-y-1 text-sm">
    @if ($isBlocks)
        @foreach ($blocks as $index => $block)
            @php
                $outlineLabel = match ($block['type']) {
                    'heading' => $block['data']['text'] ?? '',
                    'field' => $fields->get($block['data']['field_key'] ?? '')['label'] ?? null,
                    'image', 'video', 'audio', 'file' => $block['data']['filename'] ?? null,
                    default => null,
                };
            @endphp
            @if ($outlineLabel)
                <li>
                    <a href="#block-{{ $block['logical_uuid'] }}" class="block truncate rounded px-2 py-1 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 {{ $block['type'] === 'heading' ? 'font-medium' : 'ps-4' }}" dir="auto">{{ $outlineLabel }}</a>
                </li>
            @endif
        @endforeach
    @else
        @foreach ($fields as $key => $field)
            <li><a href="#field-{{ $key }}" class="block truncate rounded px-2 py-1 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" dir="auto">{{ $field['label'] }}</a></li>
        @endforeach
    @endif
    @if ($mediaAssets->isNotEmpty())
        <li><a href="#studio-media" class="block truncate rounded px-2 py-1 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('studio.media') }} ({{ $mediaAssets->count() }})</a></li>
    @endif
</ol>
@if ($canUpdate && $hasOutline)
    <button type="button" x-on:click="$dispatch('open-drawer', 'structure')" class="mt-4 w-full rounded-lg border border-dashed border-zinc-300 px-2 py-2 text-xs text-zinc-500 hover:border-zinc-400 hover:text-zinc-700 dark:border-zinc-700">
        {{ __('studio_ui.structure_hint') }}
    </button>
@endif
