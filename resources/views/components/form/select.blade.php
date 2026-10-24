@props(['remember' => false, 'label' => null, 'name', 'options' => [], 'selected' => null, 'placeholder' => null])

{{-- options: value => label --}}
<label class="block min-w-0">
    @if ($label)<span class="mb-1.5 block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</span>@endif
    <select name="{{ $name }}" {{ $attributes->class('block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base text-zinc-900 shadow-xs focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 sm:text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:focus:ring-zinc-700') }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) ($remember ? old($name, $selected) : $selected) === (string) $value)>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if ($remember) @error($name)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror @endif
</label>
