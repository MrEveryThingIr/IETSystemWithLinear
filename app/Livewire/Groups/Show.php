<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\RemoveGroupMember;
use App\Exceptions\CannotLeaveGroupWithoutOwner;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function mount(Group $group): void
    {
        Gate::authorize('view', $group);
        $this->group = $group;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
    }

    public function save(GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('update', $this->group);
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $this->group->update(['name' => $data['name'], 'description' => $data['description'] ?: null]);
        session()->flash('status', __('ui.messages.group_updated'));
    }

    public function createRole(GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $data = $this->validate(['newRoleName' => ['required', 'string', 'max:80'], 'newRolePermissions' => ['array'], 'newRolePermissions.*' => ['string', 'in:'.implode(',', GroupRoleProvisioner::permissionNames())]]);
        $groupRoles->createRole($this->group, $data['newRoleName'], $data['newRolePermissions']);
        $this->reset('newRoleName', 'newRolePermissions');
        session()->flash('status', __('ui.messages.role_created'));
    }

    public function editRole(int $roleId, GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $role = $groupRoles->role($this->group, $roleId);
        abort_if(in_array($role->name, ['Owner', 'Member'], true), 422);
        $this->editingRoleId = $role->id;
        $this->editingRoleName = $role->name;
        $this->editingRolePermissions = $role->permissions->pluck('name')->all();
    }

    public function updateRole(GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('manageRoles', $this->group);
        $data = $this->validate(['editingRoleId' => ['required', 'integer'], 'editingRoleName' => ['required', 'string', 'max:80'], 'editingRolePermissions' => ['array'], 'editingRolePermissions.*' => ['string', 'in:'.implode(',', GroupRoleProvisioner::permissionNames())]]);
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

    public function requestRole(int $membershipId, GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('requestRole', $this->group);
        /** @var GroupMembership $membership */ $membership = GroupMembership::query()->where('group_id', $this->group->id)->where('status', 'active')->findOrFail($membershipId);
        abort_unless((int) $membership->actor_id === (int) $this->actor()->id, 403);
        $roleId = (int) ($this->requestedRoles[$membershipId] ?? 0);
        abort_if($roleId === 0, 422, 'Choose a role first.');
        $role = $groupRoles->role($this->group, $roleId);
        GroupRoleChangeRequest::updateOrCreate(['membership_id' => $membership->id, 'status' => 'pending'], ['group_id' => $this->group->id, 'requested_role_id' => $role->id]);
        session()->flash('status', __('ui.messages.role_change_requested'));
    }

    public function reviewRoleRequest(int $requestId, bool $approved, GroupRoleProvisioner $groupRoles): void
    {
        Gate::authorize('approveRoleChanges', $this->group);
        /** @var GroupRoleChangeRequest $request */ $request = GroupRoleChangeRequest::query()->where('group_id', $this->group->id)->where('status', 'pending')->findOrFail($requestId);
        /** @var GroupMembership $membership */ $membership = GroupMembership::query()->where('group_id', $this->group->id)->findOrFail($request->membership_id);
        $role = $groupRoles->role($this->group, $request->requested_role_id);
        abort_unless($membership->status === 'active', 422);
        try {
            DB::transaction(function () use ($request, $membership, $role, $approved, $groupRoles): void {
                if ($approved) { /** @var Actor $member */ $member = Actor::query()->findOrFail($membership->actor_id);
                    $groupRoles->assign($member, $this->group, $role);
                } $request->update(['status' => $approved ? 'approved' : 'rejected', 'reviewed_by_actor_id' => $this->actor()->id, 'reviewed_at' => now()]);
            });
        } catch (CannotLeaveGroupWithoutOwner $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        } session()->flash('status', $approved ? __('ui.messages.role_change_approved') : __('ui.messages.role_change_rejected'));
    }

    public function removeMember(int $membershipId, RemoveGroupMember $removeGroupMember): void
    {
        Gate::authorize('manageMembers', $this->group);
        /** @var GroupMembership $membership */ $membership = GroupMembership::query()->where('group_id', $this->group->id)->findOrFail($membershipId);
        try {
            $removeGroupMember->handle($membership);
        } catch (CannotLeaveGroupWithoutOwner $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        } session()->flash('status', __('ui.messages.member_removed'));
    }

    public function render(GroupRoleProvisioner $groupRoles): View
    {
        Gate::authorize('view', $this->group);
        /** @var \Illuminate\Database\Eloquent\Collection<int, GroupMembership> $memberships */ $memberships = GroupMembership::query()->where('group_id', $this->group->id)->with('actor.user')->where('status', 'active')->get();
        /** @var Collection<int, string> $roles */ $roles = $memberships->mapWithKeys(function (GroupMembership $membership) use ($groupRoles): array { /** @var Actor $actor */ $actor = Actor::query()->findOrFail($membership->actor_id);

            return [(int) $membership->id => $groupRoles->roleName($actor, $this->group) ?? 'Member'];
        });
        $isOwner = $groupRoles->hasRole($this->actor(), $this->group, 'Owner');
        $availableRoles = $groupRoles->roles($this->group);
        $pendingRequests = $isOwner ? GroupRoleChangeRequest::query()->where('group_id', $this->group->id)->where('status', 'pending')->with(['membership.actor.user', 'requestedRole'])->latest()->get() : collect();
        $admissions = Gate::allows('manageAdmissions', $this->group)
            ? Admission::query()
                ->where('group_id', $this->group->id)
                ->whereIn('status', ['submitted', 'under_review', 'clarification_required', 'approved'])
                ->with('candidate.user')
                ->latest('submitted_at')
                ->latest('id')
                ->get()
            : collect();
        $permissionNames = GroupRoleProvisioner::permissionNames();

        return view('livewire.groups.show', compact('memberships', 'roles', 'isOwner', 'availableRoles', 'pendingRequests', 'admissions', 'permissionNames'));
    }

    private function actor(): Actor
    { /** @var Actor $actor */ $actor = Actor::query()->where('user_id', auth()->id())->firstOrFail();

        return $actor;
    }
}
