@props(['remember' => false, 'label' => null, 'name', 'value' => null, 'rows' => 4])

<label class="block min-w-0">
    @if ($label)<span class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</span>@endif
    <textarea name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->class('block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base text-zinc-900 shadow-xs focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 sm:text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:focus:ring-zinc-700') }}>{{ ($remember ? old($name, $value) : $value) }}</textarea>
    @if ($remember) @error($name)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror @endif
</label>
