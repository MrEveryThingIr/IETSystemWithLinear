<section class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('ui.auth.reset_password_title') }}</flux:heading>
    <x-app.flash-message />
    <form wire:submit="resetPassword" class="space-y-4">
        <flux:input wire:model="email" :label="__('ui.auth.email')" type="email" autocomplete="email" required />
        <flux:input wire:model="password" :label="__('ui.auth.password')" type="password" autocomplete="new-password" required />
        <flux:input wire:model="password_confirmation" :label="__('ui.auth.confirm_password')" type="password" autocomplete="new-password" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">{{ __('ui.auth.reset_password') }}</flux:button>
    </form>
    <flux:link :href="route('login')">{{ __('ui.auth.back_to_login') }}</flux:link>
</section>
