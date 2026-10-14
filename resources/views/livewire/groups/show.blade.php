<section class="space-y-6">
    @php
        $groupNeedsAttention = ($canApproveRoleChanges && $pendingRequests->isNotEmpty()) || $admissions->isNotEmpty();
        $activeMembers = $memberships->where('status', 'active');
    @endphp

    <x-app.page-header :title="$group->name" :description="$group->description ?: __('ui.groups.no_description')">
        <x-slot:actions>
            @can('createInvitation', $group)
                <flux:button :href="route('groups.invitations', $group)" variant="primary" icon="user-plus">{{ __('classroom.group.invite') }}</flux:button>
            @endcan
            @if ($canManageAnything)
                <flux:button href="#group-manage" variant="ghost" icon="cog-6-tooth">
                    {{ __('classroom.group.manage') }}
                    @if ($groupNeedsAttention)<flux:badge color="amber" size="sm">!</flux:badge>@endif
                </flux:button>
            @endif
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success" class="break-all">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" class="break-all">{{ session('error') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($pinnedContent->isNotEmpty() && $homeContext)
                @foreach ($pinnedContent as $pinned)
                    <flux:card wire:key="pinned-{{ $pinned->id }}" class="space-y-3 border-indigo-200 bg-indigo-50/60 dark:border-indigo-900 dark:bg-indigo-950/40">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <flux:badge color="indigo" size="sm">{{ __('classroom.group.start_here') }}</flux:badge>
                                <flux:heading size="lg" class="mt-2">{{ $pinned->activeRevision?->title ?? __('classroom.group.untitled') }}</flux:heading>
                            </div>
                            @can('manageSpaces', $group)
                                <flux:button size="sm" variant="ghost" wire:click="togglePin({{ $pinned->id }})">{{ __('classroom.group.unpin') }}</flux:button>
                            @endcan
                        </div>
                        <flux:button :href="route('contexts.contents.show', [$homeContext, $pinned])" variant="primary" icon="play-circle">{{ __('classroom.group.open_guide') }}</flux:button>
                    </flux:card>
                @endforeach
            @endif

            @if ($homeSpace)
                <livewire:groups.space-chat :group="$group" :space="$homeSpace" :embedded="true" :key="'home-chat-'.$homeSpace->id" />
            @else
                <x-app.empty-state :title="__('classroom.group.no_chat')" :description="__('classroom.group.no_chat_help')" />
            @endif
        </div>

        <aside class="min-w-0 space-y-6">
            <flux:card class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="lg">{{ __('classroom.group.content') }}</flux:heading>
                    @if ($homeContext)
                        <flux:button :href="route('contexts.contents.index', $homeContext)" size="sm" variant="ghost">{{ __('classroom.group.all_content') }}</flux:button>
                    @endif
                </div>
                @forelse ($recentContent as $item)
                    <div wire:key="content-{{ $item->id }}" class="flex items-center justify-between gap-2">
                        <a href="{{ route('contexts.contents.show', [$homeContext, $item]) }}" class="min-w-0 truncate text-sm font-medium text-indigo-700 hover:underline dark:text-indigo-300">{{ $item->activeRevision?->title ?? __('classroom.group.untitled') }}</a>
                        @can('manageSpaces', $group)
                            <button type="button" wire:click="togglePin({{ $item->id }})" class="shrink-0 text-xs text-zinc-500 underline underline-offset-2">{{ __('classroom.group.pin') }}</button>
                        @endcan
                    </div>
                @empty
                    <flux:text class="text-sm">{{ __('classroom.group.no_content') }}</flux:text>
                @endforelse
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('classroom.group.members', ['count' => $activeMembers->count()]) }}</flux:heading>
                <ul class="space-y-2">
                    @foreach ($activeMembers->take(12) as $membership)
                        <li wire:key="home-member-{{ $membership->id }}" class="flex items-center justify-between gap-2">
                            <x-app.actor-identity :actor="$membership->actor" size="sm" />
                            <flux:text class="truncate text-xs">{{ $roles[$membership->id] }}</flux:text>
                        </li>
                    @endforeach
                </ul>
                @if ($activeMembers->count() > 12)
                    <a href="#group-members" class="text-sm underline underline-offset-2">{{ __('classroom.group.all_members') }}</a>
                @endif
            </flux:card>

            <x-app.group-space-tabs :group="$group" />
        </aside>
    </div>

    @if ($canManageGroup || $canManageRoles || $canManageMembers || $canApproveRoleChanges || $canTransferOwnership || $admissions->isNotEmpty())
        <details id="group-manage" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950" @if($groupNeedsAttention) open @endif>
            <summary class="cursor-pointer list-none">
                <div>
                    <flux:heading size="lg">{{ __('workflow.group.manage_title') }}</flux:heading>
                    <flux:text>{{ __('workflow.group.manage_help') }}</flux:text>
                </div>
            </summary>
            <div class="mt-5 space-y-6">
                <x-app.workflow-shell
                    :purpose="__('workflow.group.purpose')"
                    :state="__('workflow.group.state')"
                    :next-action="$groupNeedsAttention ? __('workflow.group.next_review') : __('workflow.group.next_community')"
                    :audience="__('workflow.group.audience')"
                    :consequence="__('workflow.group.consequence')"
                    :result="__('workflow.group.result')"
                    :steps="__('workflow.group.sections')"
                    current-step="manage"
                    :progressive="false"
                >
                    <x-slot:help>{{ __('workflow.group.help') }}</x-slot:help>
                </x-app.workflow-shell>
                <div class="flex flex-wrap gap-2">
                    @can('manageAgreements', $group)
                        <flux:button :href="route('groups.agreements', $group)" size="sm" variant="ghost">{{ __('ui.groups.agreements') }}</flux:button>
                    @endcan
                    @can('createInvitation', $group)
                        <flux:button :href="route('groups.invitations', $group)" size="sm" variant="ghost">{{ __('ui.groups.invitations') }}</flux:button>
                    @endcan
                    <flux:button :href="route('groups.community', $group)" size="sm" variant="ghost">{{ __('community.community') }}</flux:button>
                </div>
    @if ($canManageGroup)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.settings') }}</flux:heading>
            <form wire:submit="save" class="space-y-4">
                <flux:input wire:model="name" :label="__('ui.groups.name')" />
                <flux:textarea wire:model="description" :label="__('ui.groups.description')" rows="3" />
                <flux:select wire:model="timezone" :label="__('ui.groups.timezone')" :description="__('ui.groups.timezone_help')" searchable>
                    @foreach ($timezones as $timezoneOption)
                        <option value="{{ $timezoneOption }}">{{ $timezoneOption }}</option>
                    @endforeach
                </flux:select>
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
                        <x-app.actor-identity :actor="$admission->candidate" />
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge>{{ __('ui.status.'.$admission->status) }}</flux:badge>
                            @if ($admission->submitted_at)
                                <flux:text class="text-sm">{{ __('ui.groups.submitted_label') }} <x-app.local-datetime :value="$admission->submitted_at" /></flux:text>
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


            </div>
        </details>
    @endif

    <details id="group-members" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
        <summary class="cursor-pointer"><flux:heading size="lg" class="inline">{{ __('ui.groups.members') }}</flux:heading></summary>
    <flux:card class="mt-4 space-y-4">
        <div class="space-y-3">
            @foreach ($memberships as $membership)
                <div class="flex flex-col gap-3 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1"><x-app.actor-identity :actor="$membership->actor" /><div class="flex flex-wrap gap-2"><flux:text>{{ $roles[$membership->id] }}</flux:text><flux:badge :color="$membership->status === 'active' ? 'green' : 'amber'">{{ __('ui.status.'.$membership->status) }}</flux:badge></div></div>
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
    </details>

    @if ($canApproveRoleChanges && $pendingRequests->isNotEmpty())
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.pending_role_changes') }}</flux:heading>
            @foreach ($pendingRequests as $request)
                <div class="flex flex-col gap-2 border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2"><x-app.actor-identity :actor="$request->membership->actor" size="xs" /><flux:text>{{ __('ui.groups.request_change') }} · {{ __('ui.groups.'.$request->request_type.'_role') }} “{{ $request->requestedRole->name }}”</flux:text></div>
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
                <flux:button type="submit" variant="primary">{{ __('ui.groups.request_transfer') }}</flux:button>
            </form>
        </flux:card>
    @endif
</section>