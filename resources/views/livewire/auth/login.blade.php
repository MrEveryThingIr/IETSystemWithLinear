<section class="space-y-6">
    <div class="space-y-2">
        <flux:heading size="xl" level="1">{{ $groupName ? __('ui.auth.continue_to_group', ['group' => $groupName]) : __('ui.auth.login') }}</flux:heading>
        @if ($groupName)
            <flux:text>{{ __('ui.auth.invitation_after_login') }}</flux:text>
        @endif
    </div>

    <x-app.flash-message />

    <form wire:submit="login" class="space-y-4">
        <flux:input wire:model="email" :label="__('ui.auth.email')" type="email" autocomplete="email" required />
        <flux:input wire:model="password" :label="__('ui.auth.password')" type="password" autocomplete="current-password" required />
        <flux:checkbox wire:model="remember" :label="__('ui.auth.remember')" />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="login">{{ __('ui.auth.login') }}</flux:button>
    </form>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:link :href="route('password.request')">{{ __('ui.auth.forgot_password') }}</flux:link>
        @if ($invitationToken)
            <flux:link :href="route('invitations.register', ['token' => $invitationToken])">{{ __('ui.auth.create_invited_account') }}</flux:link>
        @else
            <flux:text class="text-sm">{{ __('ui.auth.registration_requires_invitation') }}</flux:text>
        @endif
    </div>
</section>
