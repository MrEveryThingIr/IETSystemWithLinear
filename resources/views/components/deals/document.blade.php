@props(['sections', 'parties' => [], 'terms' => null])

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
                        <span class="font-medium" dir="auto">@if (! empty($line['date']))<x-app.local-date :value="$line['value']" />@else{{ $line['value'] }}@endif</span>
                    </div>
                @endforeach
                @if (! empty($section['who']))<div class="ps-5 text-xs text-zinc-500" data-clause-responsible>{{ __('deals.doc.responsible', ['name' => $section['who']]) }}</div>@endif
                @if ($section['text'])
                    <p class="whitespace-pre-wrap break-words ps-5" dir="auto">{{ $section['text'] }}</p>
                @endif
                @foreach ($section['clauses'] as $sub)
                    <div class="ps-5" data-agreement-clause="{{ $sub['n'] }}">
                        <span class="text-zinc-400">{{ $sub['n'] }}</span>
                        @if ($sub['title'])<span class="font-medium" dir="auto">{{ $sub['title'] }}</span>@endif
                        @if (! empty($sub['who']))<span class="text-xs text-zinc-500" data-clause-responsible>· {{ __('deals.doc.responsible', ['name' => $sub['who']]) }}</span>@endif
                        @if ($sub['text'])<p class="whitespace-pre-wrap break-words ps-8" dir="auto">{{ $sub['text'] }}</p>@endif
                    </div>
                @endforeach
            </li>
        @endforeach
    </ol>
    @if ($terms)
        @php($warnings = \App\Support\Deals\DealTermsWarnings::for($terms))
        @if ($warnings)
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs dark:border-amber-700 dark:bg-amber-950/30" data-agreement-warnings>
                <div class="font-medium">{{ __('deals.warnings.title') }}</div>
                <ul class="mt-1 list-disc space-y-1 ps-5">@foreach ($warnings as $w)<li data-warning="{{ $w['code'] }}" dir="auto">{{ __('deals.warnings.'.$w['code'], $w['params']) }}</li>@endforeach</ul>
            </div>
        @endif
    @endif
</article>
