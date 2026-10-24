{{--
    Page frame for Content read by guests (public posts and invitation links).
    Works signed in or out. Invitation pages pass noindex.
--}}
@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'canonical' => null,
    'noindex' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
            <meta name="referrer" content="no-referrer">
        @endif
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($canonical)
            <link rel="canonical" href="{{ $canonical }}">
        @endif
        @unless ($noindex)
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:type" content="article">
            @if ($title)<meta property="og:title" content="{{ $title }}">@endif
            @if ($description)<meta property="og:description" content="{{ $description }}">@endif
            @if ($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
            @if ($image)<meta property="og:image" content="{{ $image }}">@endif
        @endunless
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<x-app.time-bar />
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" data-public-header>
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
                <a href="{{ url('/') }}" class="truncate font-semibold tracking-tight">{{ config('app.name') }}</a>
                @if (config('public_content.index'))
                    <a href="{{ route('public-content.index') }}" class="hidden text-sm text-zinc-600 hover:text-zinc-900 sm:inline dark:text-zinc-300 dark:hover:text-white">{{ __('public_content.index.nav') }}</a>
                @endif
                <flux:spacer />
                <x-app.locale-switcher />
                @auth
                    <flux:button :href="route('dashboard')" size="sm" variant="ghost">{{ __('public_content.open_app') }}</flux:button>
                @else
                    <flux:button :href="route('login')" size="sm" variant="ghost">{{ __('ui.auth.login') }}</flux:button>
                @endauth
            </div>
        </header>

        {{ $slot }}

        <footer class="mx-auto max-w-6xl px-4 py-10 text-xs text-zinc-500 sm:px-6">
            {{ config('app.name') }}
            @if (config('public_content.index'))
                · <a href="{{ route('public-content.index') }}" class="underline-offset-4 hover:underline">{{ __('public_content.index.nav') }}</a>
            @endif
        </footer>

        @fluxScripts
    </body>
</html>
