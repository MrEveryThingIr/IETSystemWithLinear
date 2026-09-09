<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$group->description ?: 'No description yet.'">
        <x-slot:actions><flux:button :href="route('groups.index')" variant="ghost">All groups</flux:button></x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success" class="break-all">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="break-all">{{ session('error') }}</flux:callout>
    @endif

    @if ($isOwner)
        <flux:card class="space-y-4">
            <flux:heading size="lg">Group settings</flux:heading>
            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" label="Group name" />
                <flux:textarea wire:model="description" label="Description" rows="3" />
                <div class="flex flex-col gap-2 sm:flex-row"><flux:button type="submit" variant="primary">Save details</flux:button><flux:button wire:click="createInvitation" variant="ghost">Create invitation link</flux:button></div>
            </form>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">Roles and permissions</flux:heading>
            <form wire:submit="createRole" class="space-y-3">
                <flux:input wire:model="newRoleName" label="New role name" />
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($permissionNames as $permission)
                        <flux:checkbox wire:model="newRolePermissions" value="{{ $permission }}" label="{{ str($permission)->replace('_', ' ')->title() }}" />
                    @endforeach
                </div>
                <flux:button type="submit" variant="primary">Create role</flux:button>
            </form>
            <div class="space-y-3">
                @foreach ($availableRoles as $role)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        @if ($editingRoleId === $role->id)
                            <form wire:submit="updateRole" class="space-y-3">
                                <flux:input wire:model="editingRoleName" label="Role name" />
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">@foreach ($permissionNames as $permission)<flux:checkbox wire:model="editingRolePermissions" value="{{ $permission }}" label="{{ str($permission)->replace('_', ' ')->title() }}" />@endforeach</div>
                                <div class="flex gap-2"><flux:button type="submit" variant="primary">Save role</flux:button><flux:button wire:click="$set('editingRoleId', null)" variant="ghost">Cancel</flux:button></div>
                            </form>
                        @else
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><flux:heading>{{ $role->name }}</flux:heading><flux:text>{{ $role->permissions->pluck('name')->join(', ') ?: 'No permissions' }}</flux:text></div>@unless (in_array($role->name, ['Owner', 'Member'], true))<div class="flex gap-2"><flux:button wire:click="editRole({{ $role->id }})" size="sm" variant="ghost">Edit</flux:button><flux:button wire:click="deleteRole({{ $role->id }})" size="sm" variant="danger">Delete</flux:button></div>@endunless</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <flux:heading size="lg">Members</flux:heading>
        <div class="space-y-3">
            @foreach ($memberships as $membership)
                <div class="flex flex-col gap-3 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div><flux:heading>{{ $membership->actor->user?->username ?? 'Unknown member' }}</flux:heading><flux:text>{{ $roles[$membership->id] }}</flux:text></div>
                    @if ($membership->actor_id === auth()->user()->actor->id && $roles[$membership->id] !== 'Owner')
                        <div class="flex flex-col gap-2 sm:flex-row"><flux:select wire:model="requestedRoles.{{ $membership->id }}" aria-label="Requested role"><option value="">Request a role</option>@foreach ($availableRoles->where('name', '!=', 'Owner') as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</flux:select><flux:button wire:click="requestRole({{ $membership->id }})" size="sm" variant="ghost">Request change</flux:button></div>
                    @endif
                    @if ($isOwner)
                        <flux:button wire:click="removeMember({{ $membership->id }})" size="sm" variant="danger">Remove</flux:button>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    @if ($isOwner && $pendingRequests->isNotEmpty())
        <flux:card class="space-y-4"><flux:heading size="lg">Pending role changes</flux:heading>@foreach ($pendingRequests as $request)<div class="flex flex-col gap-2 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between"><flux:text>{{ $request->membership->actor->user?->username }} requested {{ $request->requestedRole->name }}</flux:text><div class="flex gap-2"><flux:button wire:click="reviewRoleRequest({{ $request->id }}, true)" size="sm" variant="primary">Approve</flux:button><flux:button wire:click="reviewRoleRequest({{ $request->id }}, false)" size="sm" variant="ghost">Reject</flux:button></div></div>@endforeach</flux:card>
    @endif
</section>
