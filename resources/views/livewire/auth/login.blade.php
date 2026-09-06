<section class="space-y-6">
    <flux:heading size="xl" level="1">Log in</flux:heading>
    <x-app.flash-message />
    <form wire:submit="login" class="space-y-4">
        <flux:input wire:model="email" label="Email" type="email" autocomplete="email" required />
        <flux:input wire:model="password" label="Password" type="password" autocomplete="current-password" required />
        <flux:checkbox wire:model="remember" label="Remember me" />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="login">Log in</flux:button>
    </form>
    <div class="flex flex-wrap justify-between gap-3"><flux:link :href="route('password.request')">Forgot password?</flux:link><flux:link :href="route('register')">Create account</flux:link></div>
</section>
