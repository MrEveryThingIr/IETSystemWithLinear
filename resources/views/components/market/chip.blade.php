@props(['active' => null, 'dashed' => false])
<button type="button" {{ $attributes->class([
    'inline-flex min-h-10 items-center gap-1 rounded-full border px-3.5 py-1.5 text-sm transition focus-visible:outline-2 focus-visible:outline-indigo-500',
    'border-indigo-600 bg-indigo-600 text-white' => $active === true,
    'border-zinc-300 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800' => $active !== true,
    'border-dashed text-zinc-700 dark:text-zinc-300' => $dashed,
]) }} @if($active !== null) aria-pressed="{{ $active ? 'true' : 'false' }}" @endif>{{ $slot }}</button>
