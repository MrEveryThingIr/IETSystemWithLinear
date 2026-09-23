<div class="space-y-6">
    <div class="space-y-2 text-center">
        <flux:heading size="xl">{{ __('access.register.title') }}</flux:heading>
        <flux:text>{{ __('access.register.help') }}</flux:text>
    </div>

    @if ($targetEmailHint)
        <flux:callout>{{ __('access.register.reserved_for', ['email' => $targetEmailHint]) }}</flux:callout>
    @endif

    <form wire:submit="register" class="space-y-4">
        <flux:input wire:model="username" :label="__('ui.auth.username')" autocomplete="username" maxlength="255" />
        <flux:input wire:model="email" type="email" :label="__('ui.auth.email')" autocomplete="email" maxlength="255" />
        <flux:input wire:model="password" type="password" :label="__('ui.auth.password')" autocomplete="new-password" />
        <flux:input wire:model="password_confirmation" type="password" :label="__('ui.auth.confirm_password')" autocomplete="new-password" />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            {{ __('access.register.create') }}
        </flux:button>
    </form>
</div>
