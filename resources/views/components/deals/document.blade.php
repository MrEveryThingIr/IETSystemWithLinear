@props(['sections', 'parties' => []])

{{-- The Agreement as a numbered document. Typed terms are canonical values; narrative clauses are text and nothing more. --}}
<article class="space-y-4 text-sm" data-agreement-document>
    @if ($parties)
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-zinc-500">{{ __('deals.doc.between') }}</span>
            @foreach ($parties as $name)<flux:badge size="sm" color="zinc">{{ $name }}</flux:badge>@endforeach
        </div>
    @endif
    <ol class="space-y-3">
        @foreach ($sections as $section)
            <li wire:key="doc-{{ $section['id'] }}" class="space-y-1" data-agreement-section="{{ $section['id'] }}">
                <div class="font-semibold" dir="auto"><span class="text-zinc-400">{{ $section['n'] }}.</span> {{ $section['title'] }}</div>
                @foreach ($section['lines'] as $line)
                    <div class="flex flex-wrap gap-x-2 ps-5">
                        @if ($line['label'])<span class="text-zinc-500" dir="auto">{{ $line['label'] }}</span>@endif
                        <span class="font-medium" dir="auto">{{ $line['value'] }}</span>
                    </div>
                @endforeach
                @if ($section['text'])
                    <p class="whitespace-pre-wrap break-words ps-5" dir="auto">{{ $section['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
</article>
