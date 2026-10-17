{{--
    Centered dialog driven by Livewire state.

    <x-app.dialog :show="$open" close="close" :title="...">...</x-app.dialog>

    close → name of the Livewire method that closes it (Escape and the
    backdrop call it too).
--}}
@props([
    'show' => false,
    'close' => null,
    'title' => null,
    'size' => 'lg',
])

@php
    $width = match ($size) {
        'sm' => 'max-w-md',
        'xl' => 'max-w-3xl',
        default => 'max-w-xl',
    };
@endphp

@if ($show)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-0 sm:items-center sm:p-6"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-label="{{ $title }}" @endif
        @if ($close) x-data x-on:keydown.escape.window="$wire.{{ $close }}()" @endif
    >
        @if ($close)
            <button type="button" class="absolute inset-0 cursor-default" aria-hidden="true" tabindex="-1" wire:click="{{ $close }}"></button>
        @endif
        <div {{ $attributes->merge(['class' => "relative max-h-[92vh] w-full {$width} overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6 dark:bg-zinc-900"]) }}>
            @if ($title || $close)
                <div class="mb-4 flex items-start justify-between gap-3">
                    @if ($title)
                        <h2 class="text-lg font-semibold">{{ $title }}</h2>
                    @endif
                    @if ($close)
                        <flux:button wire:click="{{ $close }}" size="sm" variant="ghost" icon="x-mark" :aria-label="__('ui.common.close')" />
                    @endif
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
@endif
