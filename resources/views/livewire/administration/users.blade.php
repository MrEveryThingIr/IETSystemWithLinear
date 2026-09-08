<section class="space-y-6">
    <x-app.page-header title="Users" description="Manage account status without changing group-scoped memberships or roles.">
        <x-slot:actions><flux:button :href="route('administration.index')" variant="ghost">Global access</flux:button></x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($users as $user)
            <flux:card class="space-y-3" wire:key="user-{{ $user->id }}">
                <div class="min-w-0"><flux:heading class="truncate">{{ $user->username }}</flux:heading><flux:text class="break-all">{{ $user->email }}</flux:text></div>
                <flux:badge>{{ $user->actor ? 'Actor linked' : 'No actor' }}</flux:badge>
                <div class="flex flex-wrap gap-2">
                    @foreach (['active', 'suspended', 'closed'] as $status)
                        <flux:button wire:click="updateStatus({{ $user->id }}, '{{ $status }}')" size="sm" :variant="$user->status === $status ? 'primary' : 'ghost'">{{ ucfirst($status) }}</flux:button>
                    @endforeach
                </div>
            </flux:card>
        @endforeach
    </div>
    {{ $users->links() }}
</section>
