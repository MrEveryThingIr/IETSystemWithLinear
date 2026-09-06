<section class="space-y-6">
    <flux:heading size="xl" level="1">Create your account</flux:heading>
    <x-app.flash-message />
    <form wire:submit="register" class="space-y-4">
        <flux:input wire:model="username" label="Username" type="text" autocomplete="username" required />
        <flux:input wire:model="email" label="Email" type="email" autocomplete="email" required />
        <flux:input wire:model="password" label="Password" type="password" autocomplete="new-password" required />
        <flux:input wire:model="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="register">Create account</flux:button>
    </form>
    <flux:text>Already registered? <flux:link :href="route('login')">Log in</flux:link></flux:text>
</section>
