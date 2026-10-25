@props(['presentation', 'density' => 'full'])
@php
    $p = $presentation;
    $card = $density === 'card';
    $details = collect($p['details']);
    $order = $p['highlightKeys'] ?? [];
    $first = $details->filter(fn ($d) => in_array($d['key'] ?? null, $order, true))->sortBy(fn ($d) => array_search($d['key'], $order))->values();
    // Without a topic profile, lead with the first few details rather than none.
    $first = $first->isEmpty() ? $details->take($card ? 3 : 0)->values() : $first;
    $limit = $card ? 4 : 99;
    $rest = $details->reject(fn ($d) => $first->contains(fn ($f) => $f === $d))->values();
    $isNeed = ($p['kind'] ?? null) === 'need';
    $when = $p['starts_on'] || $p['ends_on'] ? trim(($p['starts_on'] ?? '').($p['ends_on'] ? ' – '.$p['ends_on'] : '')) : null;
    $unit = fn ($d) => $d['unit'] ? ' '.$d['unit'] : '';
@endphp
<article class="min-w-0 space-y-3 break-words" data-intention-presentation data-density="{{ $density }}" @if($p['kind'] ?? null) data-kind="{{ $p['kind'] }}" @endif>
    <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-1.5">
            @if($p['kind'] ?? null)<flux:badge :color="$isNeed ? 'amber' : 'green'" size="sm">{{ __('intents.kinds.'.$p['kind']) }}</flux:badge>@endif
            @if($p['topicLabel'] ?? null)<flux:badge size="sm">{{ $p['topicLabel'] }}</flux:badge>@endif
            @if($p['arrangement'] ?? null)<flux:badge color="zinc" size="sm">{{ $p['arrangement'] }}</flux:badge>@endif
        </div>
        @if($p['title'] !== '')<h3 class="{{ $card ? 'text-lg' : 'text-2xl' }} font-semibold leading-snug" dir="auto">{{ $p['title'] }}</h3>@endif
        @if($card && ($p['category'] ?? '') !== '' && $p['category'] !== ($p['topicLabel'] ?? ''))<p class="text-xs text-zinc-500" dir="auto">{{ $p['category'] }}</p>@endif
    </header>
    @if($p['description'] !== '')<p class="whitespace-pre-wrap text-zinc-700 dark:text-zinc-200 {{ $card ? 'line-clamp-3 text-sm' : '' }}" dir="auto">{{ $p['description'] }}</p>@endif
    @if($first->isNotEmpty())
        <ul class="flex flex-wrap gap-1.5" aria-label="{{ __('composer.key_details') }}" data-highlights>
            @foreach($first->take($limit) as $detail)
                <li class="rounded-lg bg-zinc-100 px-2.5 py-1 text-sm dark:bg-zinc-800" dir="auto"><span class="text-zinc-500 dark:text-zinc-400">{{ $detail['label'] }}</span> <span class="font-medium">{{ $detail['value'] }}{{ $unit($detail) }}</span></li>
            @endforeach
        </ul>
    @endif
    @if($p['price'] || $p['location'] || $p['route'] || $when || $p['quantity'])
        <dl class="flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm" data-facts>
            @if($p['price'])<div><dt class="sr-only">{{ $p['priceLabel'] }}</dt><dd class="text-base font-semibold" dir="auto">@if($isNeed)<span class="text-xs font-normal text-zinc-500">{{ $p['priceLabel'] }}</span> @endif{{ $p['price'] }}@if($p['basis'] && $p['basis'] !== 'total') · {{ __('intents.rate_basis.'.$p['basis']) }}@endif</dd></div>@endif
            @if($p['location'])<div><dt class="inline text-zinc-500">{{ $p['placeLabel'] }}:</dt> <dd class="inline" dir="auto">{{ $p['location'] }}</dd></div>@endif
            @if($p['route'])<div><dt class="inline text-zinc-500">{{ __('composer.terms.route.label') }}:</dt> <dd class="inline" dir="auto">{{ $p['route'] }}</dd></div>@endif
            @if($when)<div><dt class="inline text-zinc-500">{{ __('composer.terms.when.label') }}:</dt> <dd class="inline">{{ $when }}</dd></div>@endif
            @if($p['quantity'])<div><dt class="inline text-zinc-500">{{ __('composer.terms.quantity.label') }}:</dt> <dd class="inline" dir="auto">{{ $p['quantity'] }} {{ $p['unit'] }}</dd></div>@endif
        </dl>
    @endif
    @if($card)
        @if($details->count() > $first->count() || $first->count() > $limit)<p class="text-xs text-zinc-500">{{ __('composer.more_details_count', ['count' => $details->count() - min($first->count(), $limit)]) }}</p>@endif
    @else
        @if($rest->isNotEmpty())
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2" data-details>
                @foreach($rest as $detail)
                    <div dir="auto">
                        @if($detail['group'])<p class="text-xs text-zinc-500">{{ $detail['group'] }}</p>@endif
                        <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $detail['label'] }}</dt>
                        <dd>
                            @if($detail['url'])<a href="{{ $detail['url'] }}" rel="noopener noreferrer" class="underline">{{ $detail['value'] }}</a>@else<span class="whitespace-pre-wrap">{{ $detail['value'] }}</span>@endif{{ $unit($detail) }}
                            @if($detail['informational'])<span class="text-xs text-zinc-500">({{ __('composer.information_money') }})</span>@endif
                            @if($detail['private'])<span class="text-xs text-zinc-500">({{ __('composer.only_me') }})</span>@endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
        @php($noted = $details->filter(fn ($d) => ! empty($d['note'])))
        @if($noted->isNotEmpty())
            <ul class="space-y-1 border-s-2 ps-3 text-sm text-zinc-600 dark:text-zinc-300" data-notes>
                @foreach($noted as $detail)<li dir="auto"><span class="font-medium">{{ $detail['label'] }}:</span> {{ $detail['note'] }}</li>@endforeach
            </ul>
        @endif
        @if(count($p['media']))<div class="grid gap-3 sm:grid-cols-2">
            @foreach($p['media'] as $item)<figure>
                @if(str_starts_with($item['mime'], 'image/'))<img src="{{ $item['url'] }}" alt="{{ $item['caption'] }}" class="max-h-80 w-full rounded-xl object-contain" loading="lazy" />
                @elseif(str_starts_with($item['mime'], 'video/'))<video src="{{ $item['url'] }}" controls preload="metadata" class="max-h-80 w-full rounded-xl" aria-label="{{ $item['caption'] ?: $item['name'] }}"></video>
                @else<a href="{{ $item['url'] }}" class="underline">{{ $item['caption'] ?: $item['name'] }}</a>@endif
                @if($item['caption'])<figcaption dir="auto">{{ $item['caption'] }}</figcaption>@endif
            </figure>@endforeach
        </div>@endif
    @endif
</article>
