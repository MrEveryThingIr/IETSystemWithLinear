@props(['remember' => false, 'label' => null, 'name', 'value' => null, 'type' => 'text', 'hint' => null])

<label class="block min-w-0">
    @if ($label)<span class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</span>@endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        @if (! in_array($type, ['file', 'password'], true)) value="{{ ($remember ? old($name, $value) : $value) }}" @endif
        {{ $attributes->class('block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base text-zinc-900 shadow-xs placeholder:text-zinc-400 focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 sm:text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:focus:ring-zinc-700 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm dark:file:bg-zinc-800') }}
    >
    @if ($hint)<span class="mt-1 block text-xs text-zinc-500">{{ $hint }}</span>@endif
    @if ($remember) @error($name)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror @endif
</label>
