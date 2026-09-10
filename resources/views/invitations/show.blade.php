<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Group invitation &mdash; {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto flex min-h-dvh max-w-2xl items-center px-4 py-8 sm:px-6">
            <flux:card class="w-full space-y-6">
                <div class="space-y-3">
                    <flux:badge color="indigo">Private group invitation</flux:badge>
                    <flux:heading size="xl">You are invited to {{ $invitation->group->name }}</flux:heading>
                    <flux:text>
                        {{ $invitation->content ?: $invitation->group->description ?: 'You have been invited to apply to this private group.' }}
                    </flux:text>
                </div>

                <div class="grid gap-3 rounded-xl bg-zinc-100 p-4 text-sm dark:bg-zinc-800 sm:grid-cols-2">
                    <div>
                        <p class="font-medium text-zinc-500 dark:text-zinc-400">Invited by</p>
                        <p class="mt-1 font-semibold">{{ $invitation->inviter->user?->username ?? 'A group member' }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-zinc-500 dark:text-zinc-400">Invitation access</p>
                        <p class="mt-1 font-semibold">{{ $invitation->email ?: 'Anyone with this private link' }}</p>
                    </div>
                </div>

                @if (session('error'))
                    <flux:callout variant="danger">{{ session('error') }}</flux:callout>
                @endif

                @auth
                    @if (auth()->user()->hasVerifiedEmail())
                        <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                            @csrf
                            <flux:button type="submit" variant="primary" class="w-full">Continue to admission</flux:button>
                        </form>
                    @else
                        <div class="space-y-3">
                            <flux:callout variant="warning">Verify your email address before continuing. You will return to this invitation afterward.</flux:callout>
                            <flux:button href="{{ route('verification.notice') }}" variant="primary" class="w-full">Verify email</flux:button>
                        </div>
                    @endif
                @else
                    <div class="space-y-3">
                        <flux:button href="{{ route('invitations.login', ['token' => $invitation->token]) }}" variant="primary" class="w-full">
                            Log in and continue
                        </flux:button>
                        <flux:button href="{{ route('invitations.register', ['token' => $invitation->token]) }}" variant="ghost" class="w-full">
                            Create an invited account
                        </flux:button>
                        <flux:text class="text-center text-sm">New accounts can only be created through a valid group invitation.</flux:text>
                    </div>
                @endauth
            </flux:card>
        </main>
        @fluxScripts
    </body>
</html>
