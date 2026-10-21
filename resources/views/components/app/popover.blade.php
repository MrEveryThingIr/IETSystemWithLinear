{{--
    A small panel opened by a button, kept inside the screen (Alpine anchor).
    <x-app.popover>
        <x-slot:trigger><flux:button>…</flux:button></x-slot:trigger>
        …panel content…
    </x-app.popover>
--}}
@props(['width' => 'w-80', 'align' => 'end'])

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="relative inline-block" {{ $attributes }}>
    <div x-ref="trigger" x-on:click="open = ! open">{{ $trigger }}</div>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        x-on:click.outside="open = false"
        x-anchor.bottom-{{ $align }}.offset.6="$refs.trigger"
        class="z-50 {{ $width }} max-w-[calc(100vw-1.5rem)] rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
    >
        {{ $slot }}
    </div>
</div>
