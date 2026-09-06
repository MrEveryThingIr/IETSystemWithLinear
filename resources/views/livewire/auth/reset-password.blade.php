<section class="space-y-6">
    <flux:heading size="xl" level="1">Reset password</flux:heading>
    <x-app.flash-message />
    <form wire:submit="resetPassword" class="space-y-4">
        <flux:input wire:model="email" label="Email" type="email" autocomplete="email" required />
        <flux:input wire:model="password" label="Password" type="password" autocomplete="new-password" required />
        <flux:input wire:model="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">Reset password</flux:button>
    </form>
    <flux:link :href="route('login')">Back to login</flux:link>
</section>
