<section class="space-y-6">
    <flux:heading size="xl" level="1">Forgot password</flux:heading>
    <x-app.flash-message />
    <form wire:submit="sendResetLink" class="space-y-4">
        <flux:input wire:model="email" label="Email" type="email" autocomplete="email" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="sendResetLink">Send reset link</flux:button>
    </form>
    <flux:link :href="route('login')">Back to login</flux:link>
</section>
