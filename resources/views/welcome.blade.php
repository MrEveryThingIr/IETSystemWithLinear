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
    <x-app.ambient-bar />
    <section class="ambient-home" data-home-clock aria-label="{{ __('ui.ambient.label') }}">
        <div class="flex w-full items-center justify-between gap-4">
            <x-app.versioned-brand />
            <x-app.locale-switcher />
        </div>
        <x-app.ambient-status analog />
        <div class="flex flex-wrap justify-center gap-3">
            @auth
                <flux:button :href="route('dashboard')" wire:navigate variant="primary">{{ __('access.root.open') }}</flux:button>
            @else
                <flux:button :href="route('login')" wire:navigate variant="primary">{{ __('ui.auth.login') }}</flux:button>
            @endauth
            <flux:button href="#home-content" variant="ghost">{{ __('ui.ambient.explore') }}</flux:button>
        </div>
    </section>
    <main id="home-content" class="mx-auto flex max-w-5xl items-start px-4 py-10 sm:px-6">
        <div class="w-full space-y-6">
            <x-app.brand-banner />
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

            @if (! empty($publicCards))
                <section class="space-y-4" data-home-public>
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight">{{ __('public_content.home.title') }}</h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('public_content.home.help') }}</p>
                        </div>
                        <flux:button :href="route('public-content.index')" size="sm" variant="ghost" icon-trailing="arrow-right" class="shrink-0 rtl:[&_svg]:rotate-180">{{ __('public_content.home.all') }}</flux:button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($publicCards as $card)
                            <x-public-content.card :card="$card" />
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </main>
    @livewireScripts
    @fluxScripts
</body>
</html>
