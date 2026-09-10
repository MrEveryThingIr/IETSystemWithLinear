<section class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('ui.auth.verify_email_title') }}</flux:heading>
    <flux:text>{{ __('ui.auth.verify_email_help') }}</flux:text>
    <x-app.flash-message />
    <form wire:submit="resend" class="space-y-3">
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="resend">{{ __('ui.auth.resend_verification') }}</flux:button>
        <flux:error name="resend" />
    </form>
    <flux:link :href="route('dashboard')">{{ __('ui.auth.continue_dashboard') }}</flux:link>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <flux:button type="submit" variant="ghost">{{ __('ui.auth.logout') }}</flux:button>
    </form>
</section>
