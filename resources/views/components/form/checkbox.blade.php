@props(['label', 'name', 'value' => '1', 'checked' => false])

<label class="flex min-h-11 min-w-0 cursor-pointer items-start gap-3 text-sm">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes->class('mt-0.5 size-4 shrink-0 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400 dark:border-zinc-600 dark:bg-zinc-900') }}>
    <span class="min-w-0">{{ $label }}{{ $slot }}</span>
</label>
