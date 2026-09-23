<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('access.welcome.title') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="fixed end-4 top-4 z-10"><x-app.locale-switcher /></div>
    <main class="mx-auto flex min-h-dvh max-w-4xl items-center px-4 py-10 sm:px-6">
        <div class="w-full space-y-6">
            <flux:card class="space-y-6">
                <div class="space-y-3">
                    <flux:badge color="indigo">{{ __('access.welcome.invited') }}</flux:badge>
                    <flux:heading size="xl">{{ __('access.welcome.title') }}</flux:heading>
                    <flux:text>{{ __('access.welcome.intro') }}</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl bg-zinc-100 p-4 dark:bg-zinc-800">
                        <div class="font-semibold">{{ __('access.welcome.record_title') }}</div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('access.welcome.record_help') }}</div>
                    </div>
                    <div class="rounded-xl bg-zinc-100 p-4 dark:bg-zinc-800">
                        <div class="font-semibold">{{ __('access.welcome.discover_title') }}</div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('access.welcome.discover_help') }}</div>
                    </div>
                    <div class="rounded-xl bg-zinc-100 p-4 dark:bg-zinc-800">
                        <div class="font-semibold">{{ __('access.welcome.control_title') }}</div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('access.welcome.control_help') }}</div>
                    </div>
                </div>

                @if ($state !== 'available')
                    <flux:callout variant="warning">{{ __('access.states.'.$state) }}</flux:callout>
                @elseif (auth()->check())
                    <flux:button :href="route('getting-started')" variant="primary" class="w-full">{{ __('access.welcome.continue') }}</flux:button>
                @else
                    @if ($invitation->maskedEmail())
                        <flux:callout>{{ __('access.welcome.reserved_for', ['email' => $invitation->maskedEmail()]) }}</flux:callout>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:button :href="route('access-invitations.register', ['token' => $token])" variant="primary" class="w-full">
                            {{ __('access.welcome.create_account') }}
                        </flux:button>
                        <flux:button :href="route('login')" variant="ghost" class="w-full">
                            {{ __('access.welcome.already_registered') }}
                        </flux:button>
                    </div>
                @endif
            </flux:card>
        </div>
    </main>
    @fluxScripts
</body>
</html>
