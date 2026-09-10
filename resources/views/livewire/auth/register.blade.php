<section class="space-y-6">
    <div class="space-y-2">
        <flux:badge color="indigo">Invitation accepted</flux:badge>
        <flux:heading size="xl" level="1">Join {{ $groupName }}</flux:heading>
        <flux:text>Create your account to begin the group admission process.</flux:text>
    </div>

    <x-app.flash-message />

    <form wire:submit="register" class="space-y-4">
        <flux:input wire:model="username" label="Username" type="text" autocomplete="username" required />
        <flux:input wire:model="email" label="Email" type="email" autocomplete="email" required />
        <flux:input wire:model="password" label="Password" type="password" autocomplete="new-password" required />
        <flux:input wire:model="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="register">Create account and admission</flux:button>
    </form>

    <flux:text>
        Already registered?
        <flux:link :href="route('invitations.login', ['token' => $invitationToken])">Log in with this invitation</flux:link>
    </flux:text>
</section>
