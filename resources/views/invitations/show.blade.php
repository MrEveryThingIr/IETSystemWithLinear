<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Group invitation — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-dvh max-w-xl items-center px-4 py-8 sm:px-6">
            <flux:card class="w-full space-y-6">
                <div class="space-y-2">
                    <flux:badge>Private group invitation</flux:badge>
                    <flux:heading size="xl">Join {{ $invitation->group->name }}</flux:heading>
                    <flux:text>{{ $invitation->content ?: $invitation->group->description ?: 'You have been invited to this private group.' }}</flux:text>
                </div>

                @if (session('error'))
                    <flux:callout variant="danger">{{ session('error') }}</flux:callout>
                @endif

                @auth
                    <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" class="w-full">Accept invitation</flux:button>
                    </form>
                @else
                    <div class="space-y-3">
                        <flux:button :href="route('login')" variant="primary" class="w-full">Log in to apply</flux:button>
                        <flux:button :href="route('register')" variant="ghost" class="w-full">Create an account</flux:button>
                    </div>
                @endauth
            </flux:card>
        </main>
        @fluxScripts
    </body>
</html>
