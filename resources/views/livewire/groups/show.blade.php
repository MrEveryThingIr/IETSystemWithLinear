<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$group->description ?: 'No description yet.'">
        <x-slot:actions><flux:button :href="route('groups.index')" variant="ghost">All groups</flux:button></x-slot:actions>
    </x-app.page-header>

    <flux:card class="space-y-4">
        <flux:heading size="lg">Members</flux:heading>
        <div class="space-y-3">
            @foreach ($memberships as $membership)
                <div class="flex flex-col gap-3 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div><flux:heading>{{ $membership->actor->user?->username ?? 'Unknown member' }}</flux:heading><flux:text>{{ $roles[$membership->id] }}</flux:text></div>
                    @if ($isOwner)
                        <div class="flex flex-wrap gap-2">
                            @if ($roles[$membership->id] === 'Member')
                                <flux:button wire:click="changeRole({{ $membership->id }}, 'Owner')" size="sm" variant="ghost">Make owner</flux:button>
                            @else
                                <flux:button wire:click="changeRole({{ $membership->id }}, 'Member')" size="sm" variant="ghost">Make member</flux:button>
                            @endif
                            <flux:button wire:click="removeMember({{ $membership->id }})" size="sm" variant="danger">Remove</flux:button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>
</section>
