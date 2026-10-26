@props(['card'])

@php($card = $card ?? null)
@if ($card)
    <flux:card class="space-y-3">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <flux:badge color="{{ ($card['kind'] ?? '') === 'need' ? 'amber' : 'emerald' }}">{{ __('deals.origin_kind.'.($card['kind'] ?? 'offer')) }}</flux:badge>
            @if (! empty($card['arrangement']))<flux:badge color="zinc">{{ $card['arrangement'] }}</flux:badge>@endif
            @if ($card['publisherName'])<span class="text-zinc-500" dir="auto">{{ $card['publisherName'] }}</span>@endif
            <span class="text-zinc-500">{{ __('deals.origin_version', ['version' => $card['version']]) }}</span>
        </div>
        <div class="text-base font-semibold" dir="auto">{{ $card['title'] }}</div>
        @if (! empty($card['description']))
            <p class="line-clamp-3 text-sm text-zinc-600 dark:text-zinc-300" dir="auto">{{ $card['description'] }}</p>
        @endif
        <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            @foreach ([['price', $card['price'] ?? null], ['place', $card['location'] ?? null], ['route', $card['route'] ?? null], ['quantity', isset($card['quantity']) ? trim($card['quantity'].' '.($card['unit'] ?? '')) : null], ['time', ! empty($card['starts_on']) ? trim($card['starts_on'].' – '.($card['ends_on'] ?? ''), ' –') : null]] as [$key, $value])
                @if ($value)
                    <div class="flex gap-2"><dt class="text-zinc-500">{{ __('deals.terms.'.$key) }}</dt><dd dir="auto">{{ $value }}@if ($key === 'price' && ! empty($card['basis'])) / {{ $card['basis'] }}@endif</dd></div>
                @endif
            @endforeach
        </dl>
    </flux:card>
@endif
