@props([
    'actor',
    'size' => 'sm',
    'alt' => null,
])

@php
    $sizes = [
        'xs' => 'h-7 w-7 rounded-lg',
        'sm' => 'h-9 w-9 rounded-xl',
        'md' => 'h-12 w-12 rounded-xl',
        'lg' => 'h-16 w-16 rounded-2xl',
        'xl' => 'h-24 w-24 rounded-3xl',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['sm'];
@endphp

<img
    src="{{ route('actors.avatar', $actor) }}"
    alt="{{ $alt ?? '' }}"
    {{ $attributes->class($sizeClass.' shrink-0 bg-zinc-200 object-cover dark:bg-zinc-800') }}
>
