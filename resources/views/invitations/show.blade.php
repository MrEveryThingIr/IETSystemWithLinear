<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ __('ui.invitation.title') }} &mdash; {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="fixed end-4 top-4 z-10">
            <x-app.locale-switcher />
        </div>
        <main class="mx-auto flex min-h-dvh max-w-2xl items-center px-4 py-8 sm:px-6">
            <flux:card class="w-full space-y-6">
                <div class="space-y-3">
                    <flux:badge color="indigo">{{ __('ui.invitation.private') }}</flux:badge>
                    <flux:heading size="xl">{{ __('ui.invitation.invited_to', ['group' => $invitation->group->name]) }}</flux:heading>
                    <flux:text>
                        {{ $invitation->content ?: $invitation->group->description ?: __('ui.invitation.default_intro') }}
                    </flux:text>
                </div>

                <div class="grid gap-3 rounded-xl bg-zinc-100 p-4 text-sm dark:bg-zinc-800 sm:grid-cols-2">
                    <div>
                        <p class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.invitation.invited_by') }}</p>
                        <p class="mt-1 font-semibold">{{ $invitation->inviter->user?->username ?? __('ui.invitation.group_member') }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.invitation.access') }}</p>
                        <p class="mt-1 font-semibold">{{ $invitation->maskedEmail() ?: __('ui.invitation.anyone_with_link') }}</p>
                    </div>
                </div>

                @if (session('error'))
                    <flux:callout variant="danger">{{ session('error') }}</flux:callout>
                @endif

                @auth
                    @if (auth()->user()->hasVerifiedEmail())
                        <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}">
                            @csrf
                            <flux:button type="submit" variant="primary" class="w-full">{{ __('ui.invitation.continue_to_admission') }}</flux:button>
                        </form>
                    @else
                        <div class="space-y-3">
                            <flux:callout variant="warning">{{ __('ui.invitation.verify_before_continue') }}</flux:callout>
                            <flux:button href="{{ route('verification.notice') }}" variant="primary" class="w-full">{{ __('ui.auth.verify_email') }}</flux:button>
                        </div>
                    @endif
                @else
                    <div class="space-y-3">
                        <flux:button href="{{ route('invitations.login', ['token' => $token]) }}" variant="primary" class="w-full">
                            {{ __('ui.invitation.login_and_continue') }}
                        </flux:button>
                        <flux:button href="{{ route('invitations.register', ['token' => $token]) }}" variant="ghost" class="w-full">
                            {{ __('ui.auth.create_invited_account') }}
                        </flux:button>
                        <flux:text class="text-center text-sm">{{ __('ui.invitation.new_accounts_only') }}</flux:text>
                    </div>
                @endauth
            </flux:card>
        </main>
        @fluxScripts
    </body>
</html>
