{{-- The site name with the running version next to it, e.g. "Everything 1.5.0"; the version opens What's new. --}}
@php
    $version = \App\Support\Changelog::currentVersion();
@endphp
<div class="flex min-w-0 items-baseline gap-1.5 px-2" dir="ltr" data-versioned-brand>
    <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ config('app.name') }}</a>
    @auth
        <a href="{{ route('whats-new') }}" wire:navigate class="shrink-0 rounded-md bg-zinc-200/70 px-1.5 py-0.5 font-mono text-[0.7rem] font-medium text-zinc-600 hover:bg-zinc-300 hover:text-zinc-900 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white" title="{{ __('changelog.title') }}" data-app-version>{{ $version }}</a>
    @else
        <span class="shrink-0 font-mono text-[0.7rem] text-zinc-500" data-app-version>{{ $version }}</span>
    @endauth
</div>
