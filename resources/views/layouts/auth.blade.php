<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? __('ui.account') }} &mdash; {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="fixed end-4 top-4 z-10">
            <x-app.locale-switcher />
        </div>
        <main class="flex min-h-dvh items-center justify-center px-4 py-10 sm:px-6">
            <div class="w-full max-w-md space-y-8">
                <div class="flex justify-center">
                    <flux:brand :href="route('dashboard')" :name="config('app.name')" />
                </div>
                <div class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                    {{ $slot ?? '' }}
                </div>
            </div>
        </main>
        @livewireScripts
        @fluxScripts
    </body>
</html>
