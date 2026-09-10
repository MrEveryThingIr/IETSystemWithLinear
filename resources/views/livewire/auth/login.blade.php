<section class="space-y-6">
    <div class="space-y-2">
        <flux:heading size="xl" level="1">{{ $groupName ? 'Continue to '.$groupName : 'Log in' }}</flux:heading>
        @if ($groupName)
            <flux:text>Your invitation will be applied after you log in.</flux:text>
        @endif
    </div>

    <x-app.flash-message />

    <form wire:submit="login" class="space-y-4">
        <flux:input wire:model="email" label="Email" type="email" autocomplete="email" required />
        <flux:input wire:model="password" label="Password" type="password" autocomplete="current-password" required />
        <flux:checkbox wire:model="remember" label="Remember me" />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="login">Log in</flux:button>
    </form>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:link :href="route('password.request')">Forgot password?</flux:link>
        @if ($invitationToken)
            <flux:link :href="route('invitations.register', ['token' => $invitationToken])">Create an invited account</flux:link>
        @else
            <flux:text class="text-sm">Registration requires a group invitation.</flux:text>
        @endif
    </div>
</section>
