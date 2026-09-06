<section class="space-y-6">
    <flux:heading size="xl" level="1">Verify your email</flux:heading>
    <flux:text>Use the link in your verification email to access your dashboard.</flux:text>
    <x-app.flash-message />
    <form wire:submit="resend" class="space-y-3">
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="resend">Resend verification email</flux:button>
        <flux:error name="resend" />
    </form>
    <flux:link :href="route('dashboard')">Continue to dashboard</flux:link>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <flux:button type="submit" variant="ghost">Log out</flux:button>
    </form>
</section>
