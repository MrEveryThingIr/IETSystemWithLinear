<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? __('ui.profile.title') }} &mdash; {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
                <flux:brand :href="auth()->check() ? route('dashboard') : url('/')" :name="config('app.name')" />
                <flux:spacer />
                <x-app.locale-switcher />
                @auth
                    <flux:button :href="route('profile.edit')" size="sm" variant="ghost">{{ __('ui.profile.edit_yours') }}</flux:button>
                @else
                    <flux:button :href="route('login')" size="sm" variant="ghost">{{ __('ui.auth.login') }}</flux:button>
                @endauth
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
            {{ $slot ?? '' }}
        </main>

        @livewireScripts
        @fluxScripts
    </body>
</html>
