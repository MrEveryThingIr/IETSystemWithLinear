@props(['title' => null])

<div {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-950') }}>
    <div class="flex flex-wrap items-center gap-2">
        <div class="me-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ $title ?? __('planning_baseline.tools.title') }}
        </div>
        {{ $slot }}
    </div>
</div>
