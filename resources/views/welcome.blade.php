<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="fixed end-4 top-4 z-10"><x-app.locale-switcher /></div>
    <main class="mx-auto flex min-h-dvh max-w-3xl items-center px-4 py-10 sm:px-6">
        <flux:card class="w-full space-y-6">
            <div class="space-y-3">
                <flux:badge color="indigo">{{ __('access.root.invitation_only') }}</flux:badge>
                <flux:heading size="xl">{{ config('app.name') }}</flux:heading>
                <flux:text>{{ __('access.root.help') }}</flux:text>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" class="w-full">{{ __('access.root.open') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('ui.auth.login') }}</flux:button>
                @endauth
                <flux:button disabled variant="ghost" class="w-full">{{ __('access.root.registration_by_invitation') }}</flux:button>
            </div>
        </flux:card>
    </main>
    @fluxScripts
</body>
</html>
