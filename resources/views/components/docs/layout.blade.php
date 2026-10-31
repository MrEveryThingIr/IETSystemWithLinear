{{--
    Page frame for the public documentation. Works signed in or out, in every supported language and direction.
    In invite-only mode the pages are not indexed.
--}}
@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'noindex' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ? $title.' — '.__('docs.nav.docs').' — '.config('app.name') : __('docs.nav.docs').' — '.config('app.name') }}</title>
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
            <meta name="referrer" content="no-referrer">
        @endif
        @if ($description)
            <meta name="description" content="{{ $description }}">
            <meta property="og:description" content="{{ $description }}">
        @endif
        @if ($canonical)<link rel="canonical" href="{{ $canonical }}">@endif
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="article">
        @if ($title)<meta property="og:title" content="{{ $title }}">@endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        <style>
            .doc-body { line-height: 1.85; font-size: 1.0625rem; }
            .doc-body > * + * { margin-top: 1rem; }
            .doc-body h2 { font-size: 1.5rem; font-weight: 650; margin-top: 2.25rem; line-height: 1.35; scroll-margin-top: 1rem; }
            .doc-body h3 { font-size: 1.2rem; font-weight: 650; margin-top: 1.5rem; line-height: 1.4; }
            .doc-body ul, .doc-body ol { padding-inline-start: 1.4rem; }
            .doc-body ul { list-style: disc; }
            .doc-body ol { list-style: decimal; }
            .doc-body li + li { margin-top: .4rem; }
            .doc-body a { text-decoration: underline; text-underline-offset: 3px; color: #0369a1; }
            .dark .doc-body a { color: #7dd3fc; }
            .doc-body blockquote { border-inline-start: 4px solid color-mix(in srgb, currentColor 30%, transparent); padding-inline-start: 1rem; font-style: normal; opacity: .9; }
            .doc-body table { width: 100%; border-collapse: collapse; font-size: .95rem; display: block; overflow-x: auto; }
            .doc-body th, .doc-body td { border: 1px solid color-mix(in srgb, currentColor 18%, transparent); padding: .5rem .75rem; text-align: start; vertical-align: top; }
            .doc-body th { background: color-mix(in srgb, currentColor 6%, transparent); }
            .doc-body code { font-size: .9em; padding: .1rem .35rem; border-radius: .35rem; background: color-mix(in srgb, currentColor 9%, transparent); }
            .doc-body hr { border-color: color-mix(in srgb, currentColor 18%, transparent); margin-block: 2rem; }
        </style>
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" data-docs-header>
            <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
                <a href="{{ route('docs.index') }}" class="truncate font-semibold tracking-tight">{{ config('app.name') }} · {{ __('docs.nav.docs') }}</a>
                <a href="{{ route('docs.contribute') }}" class="hidden text-sm text-zinc-600 hover:text-zinc-900 sm:inline dark:text-zinc-300 dark:hover:text-white">{{ __('docs.nav.collaborate') }}</a>
                <flux:spacer />
                <x-app.locale-switcher />
                @auth
                    <flux:button :href="route('dashboard')" size="sm" variant="ghost">{{ __('docs.nav.open_app') }}</flux:button>
                @else
                    <flux:button :href="route('login')" size="sm" variant="ghost">{{ __('docs.nav.login') }}</flux:button>
                @endauth
            </div>
        </header>

        {{ $slot }}

        <footer class="mx-auto max-w-6xl px-4 py-10 text-sm text-zinc-500 sm:px-6">
            <p>{{ __('docs.footer') }}</p>
            <p class="mt-2"><a class="underline underline-offset-4" href="{{ route('docs.contribute') }}">{{ __('docs.nav.collaborate') }}</a></p>
        </footer>

        @fluxScripts
    </body>
</html>
