{{--
    Side drawer opened and closed in the browser (Alpine), so its content keeps
    its own Livewire state while hidden.

    <x-app.drawer name="history" :title="..." side="end">...</x-app.drawer>
    Open with:  x-on:click="$dispatch('open-drawer', 'history')"
    Close with: x-on:click="$dispatch('close-drawer', 'history')"
--}}
@props([
    'name',
    'title' => null,
    'side' => 'end',
    'width' => 'sm:max-w-md',
])

<div
    x-data="{ open: false }"
    x-on:open-drawer.window="if ($event.detail === @js($name)) open = true"
    x-on:close-drawer.window="if ($event.detail === @js($name)) open = false"
    x-on:keydown.escape.window="open = false"
    data-drawer="{{ $name }}"
>
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-zinc-950/30" x-on:click="open = false" aria-hidden="true"></div>
    <aside
        x-show="open"
        x-cloak
        x-transition
        role="dialog"
        aria-modal="true"
        @if ($title) aria-label="{{ $title }}" @endif
        {{ $attributes->merge(['class' => "fixed inset-y-0 z-50 flex w-full {$width} flex-col bg-white shadow-xl dark:bg-zinc-900 ".($side === 'start' ? 'start-0' : 'end-0')]) }}
    >
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="font-semibold">{{ $title }}</h2>
            <flux:button x-on:click="open = false" size="sm" variant="ghost" icon="x-mark" :aria-label="__('ui.common.close')" />
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            {{ $slot }}
        </div>
    </aside>
</div>
