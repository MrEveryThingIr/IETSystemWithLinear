@props(['title' => null, 'help' => null, 'icon' => null])

{{-- A titled section card for classic (non-Livewire) pages; same look as flux:card. --}}
<section {{ $attributes->class('min-w-0 scroll-mt-24 rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900') }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-100 px-4 py-4 sm:px-6 dark:border-zinc-800">
            <div class="min-w-0">
                @if ($title)
                    <flux:heading size="lg" level="2" class="flex items-center gap-2">
                        @if ($icon)<flux:icon :name="$icon" class="size-5 shrink-0 text-zinc-500" />@endif
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ $title }}</span>
                    </flux:heading>
                @endif
                @if ($help)
                    <flux:text class="mt-1">{{ $help }}</flux:text>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="space-y-4 px-4 py-4 sm:px-6 sm:py-5">
        {{ $slot }}
    </div>
</section>
