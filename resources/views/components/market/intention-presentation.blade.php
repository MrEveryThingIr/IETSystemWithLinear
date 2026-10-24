@props(['presentation'])
<article class="space-y-4 break-words" data-intention-presentation>
    @if($presentation['title'] !== '')<h3 class="text-xl font-semibold" dir="auto">{{ $presentation['title'] }}</h3>@endif
    @if($presentation['description'] !== '')<p class="whitespace-pre-wrap" dir="auto">{{ $presentation['description'] }}</p>@endif
    @if($presentation['price'])<p class="font-medium" dir="auto">{{ $presentation['price'] }}@if($presentation['basis'] && $presentation['basis'] !== 'total') · {{ __('intents.rate_basis.'.$presentation['basis']) }}@endif</p>@endif
    @if($presentation['quantity'])<p dir="auto">{{ $presentation['quantity'] }} {{ $presentation['unit'] }}</p>@endif
    @if($presentation['location'])<p dir="auto">{{ $presentation['location'] }}</p>@endif
    @if($presentation['starts_on'] || $presentation['ends_on'])<p>{{ $presentation['starts_on'] }}@if($presentation['ends_on']) – {{ $presentation['ends_on'] }}@endif</p>@endif
    @foreach($presentation['details'] as $detail)
        <div dir="auto">
            @if($detail['group'])<p class="text-xs text-zinc-500">{{ $detail['group'] }}</p>@endif
            <p><strong>{{ $detail['label'] }}:</strong>
                @if($detail['url'])<a href="{{ $detail['url'] }}" rel="noopener noreferrer" class="underline">{{ $detail['value'] }}</a>@else<span class="whitespace-pre-wrap">{{ $detail['value'] }}</span>@endif
                @if($detail['unit']) {{ $detail['unit'] }}@endif
                @if($detail['informational'])<span class="text-xs text-zinc-500">({{ __('composer.information_money') }})</span>@endif
                @if($detail['private'])<span class="text-xs text-zinc-500">({{ __('composer.only_me') }})</span>@endif
            </p>
        </div>
    @endforeach
    @if(count($presentation['media']))<div class="grid gap-3 sm:grid-cols-2">
        @foreach($presentation['media'] as $item)<figure>
            @if(str_starts_with($item['mime'], 'image/'))<img src="{{ $item['url'] }}" alt="{{ $item['caption'] }}" class="max-h-80 w-full rounded-xl object-contain" loading="lazy" />
            @elseif(str_starts_with($item['mime'], 'video/'))<video src="{{ $item['url'] }}" controls preload="metadata" class="max-h-80 w-full rounded-xl" aria-label="{{ $item['caption'] ?: $item['name'] }}"></video>
            @else<a href="{{ $item['url'] }}" class="underline">{{ $item['caption'] ?: $item['name'] }}</a>@endif
            @if($item['caption'])<figcaption dir="auto">{{ $item['caption'] }}</figcaption>@endif
        </figure>@endforeach
    </div>@endif
</article>
