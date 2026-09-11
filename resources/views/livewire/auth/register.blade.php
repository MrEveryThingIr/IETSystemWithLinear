<section class="space-y-6">
    <div class="space-y-2">
        <flux:badge color="indigo">{{ __('ui.auth.invitation_accepted') }}</flux:badge>
        <flux:heading size="xl" level="1">{{ __('ui.auth.join_group', ['group' => $groupName]) }}</flux:heading>
        <flux:text>{{ __('ui.auth.create_account_intro') }}</flux:text>
    </div>

    <x-app.flash-message />

    <form wire:submit="register" class="space-y-4">
        <flux:input wire:model="username" :label="__('ui.auth.username')" type="text" autocomplete="username" required />
        <flux:input wire:model="email" :label="__('ui.auth.email')" type="email" autocomplete="email" required />
        <flux:input wire:model="password" :label="__('ui.auth.password')" type="password" autocomplete="new-password" required />
        <flux:input wire:model="password_confirmation" :label="__('ui.auth.confirm_password')" type="password" autocomplete="new-password" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="register">{{ __('ui.auth.create_account_and_admission') }}</flux:button>
    </form>

    <flux:text>
        {{ __('ui.auth.already_registered') }}
        <flux:link :href="route('invitations.login', ['token' => $invitationToken])">{{ __('ui.auth.login_with_invitation') }}</flux:link>
    </flux:text>
</section>
