{{--
    Centered dialog driven by Livewire state (a bottom sheet on phones).

    <x-app.dialog :show="$open" close="close" :title="...">...</x-app.dialog>

    close → name of the Livewire method that closes it (Escape and the
    backdrop call it too). The header stays visible while the body scrolls,
    the page behind does not scroll, and Tab stays inside the dialog.
--}}
@props([
    'show' => false,
    'close' => null,
    'title' => null,
    'size' => 'lg',
])

@php
    $width = match ($size) {
        'sm' => 'sm:max-w-md',
        'xl' => 'sm:max-w-3xl',
        default => 'sm:max-w-xl',
    };
@endphp

@if ($show)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/40 p-0 backdrop-blur-[1px] sm:items-center sm:p-6"
        role="dialog"
        aria-modal="true"
        @if ($title) aria-label="{{ $title }}" @endif
        x-data="{ shown: false, open: true }"
        x-init="$nextTick(() => shown = true)"
        x-show="open"
        @if ($close) x-on:keydown.escape.window="open = false; $wire.{{ $close }}()" @endif
        data-app-dialog
    >
        @if ($close)
            {{-- Closes at once on screen; the server catches up in the background. --}}
            <button type="button" class="absolute inset-0 cursor-default" aria-hidden="true" tabindex="-1" x-on:click="open = false; $wire.{{ $close }}()"></button>
        @endif
        <div
            x-trap.noscroll="shown && open"
            x-bind:class="shown ? 'translate-y-0 opacity-100 sm:scale-100' : 'translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95'"
            {{ $attributes->merge(['class' => "relative flex max-h-[92dvh] w-full {$width} flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl transition duration-150 ease-out sm:rounded-2xl dark:bg-zinc-900"]) }}
        >
            @if ($title || $close)
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6 dark:border-zinc-800">
                    @if ($title)
                        <h2 class="min-w-0 text-lg font-semibold" dir="auto">{{ $title }}</h2>
                    @endif
                    @if ($close)
                        <flux:button x-on:click="open = false; $wire.{{ $close }}()" size="sm" variant="ghost" icon="x-mark" class="-me-2 shrink-0" :aria-label="__('ui.common.close')" />
                    @endif
                </div>
            @endif
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6 sm:py-5">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
