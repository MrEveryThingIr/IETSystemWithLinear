<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $business->short_intro ?: $business->description }}">
    <title>@yield('title', $business->name)</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-dvh bg-slate-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('public.businesses.show', ['business' => $business->slug]) }}" class="flex min-w-0 items-center gap-3 text-inherit no-underline">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-emerald-600 to-sky-600 text-lg font-black text-white shadow-sm">
                    {{ mb_substr($business->name, 0, 1) }}
                </span>
                <span class="min-w-0">
                    <strong class="block truncate text-lg">{{ $business->name }}</strong>
                    @if($business->short_intro)
                        <span class="block max-w-xl truncate text-xs text-slate-500">{{ $business->short_intro }}</span>
                    @endif
                </span>
            </a>
            <div class="ms-auto"><x-app.locale-switcher /></div>
        </div>
    </header>
    <main>@yield('content')</main>
    <footer class="mt-16 border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-500 sm:px-6">
            <strong class="text-slate-700">{{ $business->name }}</strong>
            <div class="mt-1">{{ __('public_business.footer') }}</div>
        </div>
    </footer>
    @fluxScripts
</body>
</html>
