@props(['title', 'description' => null, 'icon' => 'inbox'])

<section {{ $attributes->class('rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700') }}>
    <flux:icon :name="$icon" class="mx-auto mb-4 size-8 text-zinc-400" />
    <flux:heading size="lg" level="2">{{ $title }}</flux:heading>
    @if ($description)
        <flux:text class="mx-auto mt-2 max-w-md">{{ $description }}</flux:text>
    @endif
    @isset($actions)
        <div class="mt-6 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</section>
