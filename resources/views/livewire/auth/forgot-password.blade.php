<section class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('ui.auth.forgot_password_title') }}</flux:heading>
    <x-app.flash-message />
    <form wire:submit="sendResetLink" class="space-y-4">
        <flux:input wire:model="email" :label="__('ui.auth.email')" type="email" autocomplete="email" required />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="sendResetLink">{{ __('ui.auth.send_reset_link') }}</flux:button>
    </form>
    <flux:link :href="route('login')">{{ __('ui.auth.back_to_login') }}</flux:link>
</section>
