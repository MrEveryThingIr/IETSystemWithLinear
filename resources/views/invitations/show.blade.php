<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="referrer" content="no-referrer">
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

                <flux:callout>{{ __('ui.invitation.joining_steps') }}</flux:callout>

                <div class="grid gap-3 rounded-xl bg-zinc-100 p-4 text-sm dark:bg-zinc-800 sm:grid-cols-2">
                    <div>
                        <p class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.invitation.invited_by') }}</p>
                        <div class="mt-2"><x-app.actor-identity :actor="$invitation->inviter" size="sm" /></div>
                    </div>
                    <div>
                        <p class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('ui.invitation.access') }}</p>
                        <p class="mt-1 font-semibold">{{ $invitation->maskedEmail() ?: __('ui.invitation.anyone_with_link') }}</p>
                    </div>
                </div>

                @if (session('error'))
                    <flux:callout variant="danger">{{ session('error') }}</flux:callout>
                @endif

                @if ($membership)
                    <flux:callout variant="success">{{ __('ui.invitation.already_member') }}</flux:callout>
                    @if ($membership->status === 'active')
                        <flux:button :href="route('groups.show', $invitation->group)" variant="primary" class="w-full">{{ __('ui.groups.open') }}</flux:button>
                    @endif
                @elseif ($admission)
                    <flux:callout>{{ __('ui.invitation.existing_admission') }}</flux:callout>
                    <flux:button :href="route('admissions.show', $admission)" variant="primary" class="w-full">{{ __('ui.groups.view_admission') }}</flux:button>
                @elseif ($state !== 'available')
                    <flux:callout variant="warning">{{ __('ui.invitation.state_'.$state) }}</flux:callout>
                @elseif ($targetMismatch)
                    <flux:callout variant="warning">{{ __('ui.invitation.wrong_account') }}</flux:callout>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="ghost" class="w-full">{{ __('ui.auth.logout') }}</flux:button>
                    </form>
                @elseif (auth()->check() && ! auth()->user()->actor)
                    <flux:callout variant="danger">{{ __('ui.invitation.identity_recovery') }}</flux:callout>
                @elseif (auth()->check() && auth()->user()->hasVerifiedEmail())
                    <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" class="w-full">{{ __('ui.invitation.continue_to_admission') }}</flux:button>
                    </form>
                @elseif (auth()->check())
                    <div class="space-y-3">
                        <flux:callout variant="warning">{{ __('ui.invitation.verify_before_continue') }}</flux:callout>
                        <flux:button href="{{ route('verification.notice') }}" variant="primary" class="w-full">{{ __('ui.auth.verify_email') }}</flux:button>
                    </div>
                @else
                    <div class="space-y-3">
                        <flux:button href="{{ route('invitations.login', ['token' => $token]) }}" variant="primary" class="w-full">
                            {{ __('ui.invitation.login_and_continue') }}
                        </flux:button>
                        <flux:text class="text-center text-sm">{{ __('access.group_existing_account_required') }}</flux:text>
                    </div>
                @endif
            </flux:card>
        </main>
        @fluxScripts
    </body>
</html>
