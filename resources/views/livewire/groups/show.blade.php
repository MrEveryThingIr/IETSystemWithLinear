<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$group->description ?: __('ui.groups.no_description')">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @can('manageAgreements', $group)
                    <flux:button :href="route('groups.agreements', $group)" variant="ghost">{{ __('ui.groups.agreements') }}</flux:button>
                @endcan
                @can('createInvitation', $group)
                    <flux:button :href="route('groups.invitations', $group)" variant="ghost">{{ __('ui.groups.invitations') }}</flux:button>
                @endcan
                <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
            </div>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success" class="break-all">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="break-all">{{ session('error') }}</flux:callout>
    @endif

    @if ($canManageGroup)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.settings') }}</flux:heading>
            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" :label="__('ui.groups.name')" />
                <flux:textarea wire:model="description" :label="__('ui.groups.description')" rows="3" />
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button type="submit" variant="primary">{{ __('ui.groups.save_details') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($canManageRoles)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.roles_permissions') }}</flux:heading>
            <form wire:submit="createRole" class="space-y-3">
                <flux:input wire:model="newRoleName" :label="__('ui.groups.new_role_name')" />
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($permissionNames as $permission)
                        <flux:checkbox wire:model="newRolePermissions" value="{{ $permission }}" label="{{ $permissionLabels[$permission] }}" />
                    @endforeach
                </div>
                <flux:button type="submit" variant="primary">{{ __('ui.groups.create_role') }}</flux:button>
            </form>
            <div class="space-y-3">
                @foreach ($availableRoles as $role)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        @if ($editingRoleId === $role->id)
                            <form wire:submit="updateRole" class="space-y-3">
                                <flux:input wire:model="editingRoleName" :label="__('ui.groups.role_name')" />
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">@foreach ($permissionNames as $permission)<flux:checkbox wire:model="editingRolePermissions" value="{{ $permission }}" label="{{ $permissionLabels[$permission] }}" />@endforeach</div>
                                <div class="flex gap-2"><flux:button type="submit" variant="primary">{{ __('ui.groups.save_role') }}</flux:button><flux:button wire:click="$set('editingRoleId', null)" variant="ghost">{{ __('ui.common.cancel') }}</flux:button></div>
                            </form>
                        @else
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><flux:heading>{{ $role->name }}</flux:heading><div class="flex flex-wrap gap-1">@forelse ($role->permissions as $permission)<flux:badge>{{ $permissionLabels[$permission->name] ?? $permission->name }}</flux:badge>@empty<flux:text>{{ __('ui.groups.no_permissions') }}</flux:text>@endforelse</div></div>@if ($role->getAttribute('system_key') === null)<div class="flex gap-2"><flux:button wire:click="editRole({{ $role->id }})" size="sm" variant="ghost">{{ __('ui.groups.edit') }}</flux:button><flux:button wire:click="deleteRole({{ $role->id }})" size="sm" variant="danger">{{ __('ui.groups.delete') }}</flux:button></div>@endif</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    @can('manageAdmissions', $group)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.groups.admissions_review') }}</flux:heading>
                <flux:text>{{ __('ui.groups.admissions_help') }}</flux:text>
            </div>
            @forelse ($admissions as $admission)
                <div class="flex flex-col gap-3 border-b border-zinc-200 pb-3 last:border-0 last:pb-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <flux:heading>{{ $admission->candidate->user?->username ?? __('ui.groups.unknown_applicant') }}</flux:heading>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge>{{ __('ui.status.'.$admission->status) }}</flux:badge>
                            @if ($admission->submitted_at)
                                <flux:text class="text-sm">{{ __('ui.groups.submitted_ago', ['time' => $admission->submitted_at->diffForHumans()]) }}</flux:text>
                            @endif
                        </div>
                    </div>
                    <flux:button :href="route('admissions.show', $admission)" size="sm" variant="primary">{{ __('ui.groups.review_admission') }}</flux:button>
                </div>
            @empty
                <flux:text>{{ __('ui.groups.no_admissions') }}</flux:text>
            @endforelse
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('ui.groups.members') }}</flux:heading>
        <div class="space-y-3">
            @foreach ($memberships as $membership)
                <div class="flex flex-col gap-3 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1"><flux:heading>{{ $membership->actor->user?->username ?? __('ui.groups.unknown_member') }}</flux:heading><div class="flex flex-wrap gap-2"><flux:text>{{ $roles[$membership->id] }}</flux:text><flux:badge :color="$membership->status === 'active' ? 'green' : 'amber'">{{ __('ui.status.'.$membership->status) }}</flux:badge></div></div>
                    @if ($membership->status === 'active' && $membership->actor_id === auth()->user()->actor->id && $availableRoles->whereNull('system_key')->isNotEmpty())
                        <div class="flex flex-col gap-2 sm:flex-row"><flux:select wire:model="requestedRoleTypes.{{ $membership->id }}" :aria-label="__('ui.groups.role_request_type')"><option value="grant">{{ __('ui.groups.grant_role') }}</option><option value="revoke">{{ __('ui.groups.revoke_role') }}</option></flux:select><flux:select wire:model="requestedRoles.{{ $membership->id }}" :aria-label="__('ui.groups.requested_role')"><option value="">{{ __('ui.groups.request_role') }}</option>@foreach ($availableRoles->whereNull('system_key') as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</flux:select><flux:button wire:click="requestRole({{ $membership->id }})" size="sm" variant="ghost">{{ __('ui.groups.request_change') }}</flux:button></div>
                    @endif
                    @if ($canManageMembers)
                        <div class="flex flex-wrap gap-2">
                            @if ($membership->status === 'active')
                                <flux:button wire:click="suspendMember({{ $membership->id }})" size="sm" variant="ghost">{{ __('ui.groups.suspend') }}</flux:button>
                                <flux:button wire:click="removeMember({{ $membership->id }})" size="sm" variant="danger">{{ __('ui.groups.remove') }}</flux:button>
                            @else
                                <flux:button wire:click="reactivateMember({{ $membership->id }})" size="sm" variant="primary">{{ __('ui.groups.reactivate') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    @if ($canApproveRoleChanges && $pendingRequests->isNotEmpty())
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.pending_role_changes') }}</flux:heading>
            @foreach ($pendingRequests as $request)
                <div class="flex flex-col gap-2 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <flux:text>{{ __('ui.groups.role_request', ['username' => $request->membership->actor->user?->username ?? __('ui.common.unknown_account'), 'type' => __('ui.groups.'.$request->request_type.'_role'), 'role' => $request->requestedRole->name]) }}</flux:text>
                    <div class="flex gap-2">
                        <flux:button wire:click="reviewRoleRequest({{ $request->id }}, true)" size="sm" variant="primary">{{ __('ui.admission.approve') }}</flux:button>
                        <flux:button wire:click="reviewRoleRequest({{ $request->id }}, false)" size="sm" variant="ghost">{{ __('ui.admission.reject') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endif

    @if ($canTransferOwnership)
        <flux:card class="space-y-4">
            <div><flux:heading size="lg">{{ __('ui.groups.transfer_ownership') }}</flux:heading><flux:text>{{ __('ui.groups.transfer_ownership_help') }}</flux:text></div>
            <form wire:submit="transferOwnership" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:select wire:model="transferMembershipId" :label="__('ui.groups.new_owner')">
                    <option value="">{{ __('ui.groups.choose_member') }}</option>
                    @foreach ($memberships->where('status', 'active')->where('actor_id', '!=', auth()->user()->actor->id) as $membership)
                        <option value="{{ $membership->id }}">{{ $membership->actor->user?->username ?? __('ui.groups.unknown_member') }}</option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="danger">{{ __('ui.groups.transfer') }}</flux:button>
            </form>
        </flux:card>
    @endif
</section>
