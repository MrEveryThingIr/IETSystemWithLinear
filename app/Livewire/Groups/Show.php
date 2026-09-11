<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\RemoveGroupMember;
use App\Actions\Groups\ReviewGroupRoleChangeRequest;
use App\Actions\Groups\SubmitGroupRoleChangeRequest;
use App\Actions\Groups\TransferGroupOwnership;
use App\Actions\Groups\TransitionGroupMembership;
use App\Exceptions\CannotLeaveGroupWithoutOwner;
use App\GroupPermission;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Group')]
class Show extends Component
{
    public Group $group;

    public string $name = '';

    public string $description = '';

    public string $newRoleName = '';

    /** @var list<string> */
    public array $newRolePermissions = [];

    public ?int $editingRoleId = null;

    public string $editingRoleName = '';

    /** @var list<string> */
    public array $editingRolePermissions = [];

    /** @var array<int, int|string> */
    public array $requestedRoles = [];

    /** @var array<int, string> */
    public array $requestedRoleTypes = [];

    public string $transferMembershipId = '';

    public function mount(Group $group): void
    {
        Gate::authorize('view', $group);
        $this->group = $group;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
    }

    public function save(): void
    {
        Gate::authorize('update', $this->group);
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $this->group->update(['name' => $data['name'], 'description' => $data['description'] ?: null]);
        session()->flash('status', __('ui.messages.group_updated'));
    }

    public function createRole(GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $data = $this->validate([
            'newRoleName' => ['required', 'string', 'max:80'],
            'newRolePermissions' => ['array'],
            'newRolePermissions.*' => ['string', 'in:'.implode(',', GroupRoleProvisioner::customRolePermissionNames())],
        ]);
        $groupRoles->createRole($this->group, $data['newRoleName'], $data['newRolePermissions']);
        $this->reset('newRoleName', 'newRolePermissions');
        session()->flash('status', __('ui.messages.role_created'));
    }

    public function editRole(int $roleId, GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $role = $groupRoles->role($this->group, $roleId);
        abort_if($role->getAttribute('system_key') !== null, 422);
        $this->editingRoleId = $role->id;
        $this->editingRoleName = $role->name;
        $this->editingRolePermissions = $role->permissions->pluck('name')->all();
    }

    public function updateRole(GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $data = $this->validate([
            'editingRoleId' => ['required', 'integer'],
            'editingRoleName' => ['required', 'string', 'max:80'],
            'editingRolePermissions' => ['array'],
            'editingRolePermissions.*' => ['string', 'in:'.implode(',', GroupRoleProvisioner::customRolePermissionNames())],
        ]);
        $groupRoles->updateRole($this->group, $groupRoles->role($this->group, $data['editingRoleId']), $data['editingRoleName'], $data['editingRolePermissions']);
        $this->reset('editingRoleId', 'editingRoleName', 'editingRolePermissions');
        session()->flash('status', __('ui.messages.role_updated'));
    }

    public function deleteRole(int $roleId, GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $groupRoles->deleteRole($this->group, $groupRoles->role($this->group, $roleId));
        session()->flash('status', __('ui.messages.role_deleted'));
    }

    public function requestRole(int $membershipId, GroupRoleProvisioner $groupRoles, SubmitGroupRoleChangeRequest $submitRequest): void
    {
        Gate::authorize('requestRole', $this->group);
        $membership = GroupMembership::query()->where('group_id', $this->group->id)->where('status', 'active')->findOrFail($membershipId);
        abort_unless((int) $membership->actor_id === (int) $this->actor()->id, 403);
        $roleId = (int) ($this->requestedRoles[$membershipId] ?? 0);
        abort_if($roleId === 0, 422, 'Choose a role first.');
        $role = $groupRoles->role($this->group, $roleId);
        $submitRequest->execute($membership, $this->actor(), $role, $this->requestedRoleTypes[$membershipId] ?? 'grant');
        session()->flash('status', __('ui.messages.role_change_requested'));
    }

    public function reviewRoleRequest(int $requestId, bool $approved, ReviewGroupRoleChangeRequest $reviewRequest): void
    {
        Gate::authorize('approveRoleChanges', $this->group);
        $request = GroupRoleChangeRequest::query()->where('group_id', $this->group->id)->where('status', 'pending')->findOrFail($requestId);
        $reviewRequest->execute($request, $this->actor(), $approved);
        session()->flash('status', $approved ? __('ui.messages.role_change_approved') : __('ui.messages.role_change_rejected'));
    }

    public function removeMember(int $membershipId, RemoveGroupMember $removeGroupMember): void
    {
        Gate::authorize('manageMembers', $this->group);
        $membership = GroupMembership::query()->where('group_id', $this->group->id)->findOrFail($membershipId);

        try {
            $removeGroupMember->handle($membership, $this->actor());
        } catch (CannotLeaveGroupWithoutOwner $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        session()->flash('status', __('ui.messages.member_removed'));
    }

    public function suspendMember(int $membershipId, TransitionGroupMembership $memberships): void
    {
        Gate::authorize('manageMembers', $this->group);
        $membership = GroupMembership::query()->where('group_id', $this->group->id)->findOrFail($membershipId);

        try {
            $memberships->suspend($membership, $this->actor(), 'Suspended by an authorized Group manager.');
        } catch (CannotLeaveGroupWithoutOwner $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }

        session()->flash('status', __('ui.messages.member_suspended'));
    }

    public function reactivateMember(int $membershipId, TransitionGroupMembership $memberships): void
    {
        Gate::authorize('manageMembers', $this->group);
        $membership = GroupMembership::query()->where('group_id', $this->group->id)->findOrFail($membershipId);
        $memberships->reactivate($membership, $this->actor(), 'Reactivated by an authorized Group manager.');
        session()->flash('status', __('ui.messages.member_reactivated'));
    }

    public function transferOwnership(TransferGroupOwnership $transferOwnership): void
    {
        Gate::authorize('transferOwnership', $this->group);
        $data = $this->validate(['transferMembershipId' => ['required', 'integer']]);
        $membership = GroupMembership::query()->where('group_id', $this->group->id)->where('status', 'active')->findOrFail($data['transferMembershipId']);
        $transferOwnership->execute($this->group, $this->actor(), $membership);
        $this->reset('transferMembershipId');
        session()->flash('status', __('ui.messages.ownership_transferred'));
    }

    public function render(GroupRoleProvisioner $groupRoles): View
    {
        Gate::authorize('view', $this->group);
        $memberships = GroupMembership::query()
            ->where('group_id', $this->group->id)
            ->with('actor.user')
            ->whereIn('status', ['active', 'suspended'])
            ->orderBy('id')
            ->get();
        /** @var Collection<int, string> $roles */
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [
            (int) $membership->id => $groupRoles->roleNames($membership->actor, $this->group)->join(', '),
        ]);
        $availableRoles = $groupRoles->roles($this->group);
        $canManageGroup = Gate::allows('update', $this->group);
        $canManageRoles = Gate::allows('manageRoles', $this->group);
        $canManageMembers = Gate::allows('manageMembers', $this->group);
        $canApproveRoleChanges = Gate::allows('approveRoleChanges', $this->group);
        $canTransferOwnership = Gate::allows('transferOwnership', $this->group);
        $pendingRequests = $canApproveRoleChanges
            ? GroupRoleChangeRequest::query()->where('group_id', $this->group->id)->where('status', 'pending')->with(['membership.actor.user', 'requestedRole'])->latest()->get()
            : collect();
        $admissions = Gate::allows('manageAdmissions', $this->group)
            ? Admission::query()
                ->where('group_id', $this->group->id)
                ->whereIn('status', ['submitted', 'under_review', 'clarification_required', 'approved'])
                ->with('candidate.user')
                ->latest('submitted_at')
                ->latest('id')
                ->get()
            : collect();
        $permissionNames = GroupRoleProvisioner::customRolePermissionNames();
        $permissionLabels = collect(GroupPermission::cases())->mapWithKeys(
            fn (GroupPermission $permission): array => [$permission->value => __('ui.permissions.'.$permission->value)],
        );

        return view('livewire.groups.show', compact(
            'memberships',
            'roles',
            'availableRoles',
            'pendingRequests',
            'admissions',
            'permissionNames',
            'permissionLabels',
            'canManageGroup',
            'canManageRoles',
            'canManageMembers',
            'canApproveRoleChanges',
            'canTransferOwnership',
        ));
    }

    private function actor(): Actor
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user->actor;
    }
}
