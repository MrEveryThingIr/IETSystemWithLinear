@props(['title', 'description' => null])

<header {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between') }}>
    <div class="min-w-0 space-y-2">
        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
        @if ($description)
            <flux:text>{{ $description }}</flux:text>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
