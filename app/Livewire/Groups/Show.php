<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

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
        abort_unless($group->memberships()->where('actor_id', auth()->user()->actor->id)->where('status', 'active')->exists(), 403);
        $this->group = $group;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
    }

    public function save(GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $this->group->update(['name' => $data['name'], 'description' => $data['description'] ?: null]);
        session()->flash('status', 'Group details updated.');
    }

    public function createInvitation(GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $invitation = GroupInvitation::create(['group_id' => $this->group->id, 'invited_by_actor_id' => auth()->user()->actor->id, 'token' => Str::random(48), 'expires_at' => now()->addDays(14), 'max_uses' => 100]);
        session()->flash('status', 'Invitation link: '.route('invitations.show', $invitation->token));
    }

    public function createRole(GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $data = $this->validate(['newRoleName' => ['required', 'string', 'max:80'], 'newRolePermissions' => ['array'], 'newRolePermissions.*' => ['string', 'in:'.implode(',', GroupRoleProvisioner::permissionNames())]]);
        $groupRoles->createRole($this->group, $data['newRoleName'], $data['newRolePermissions']);
        $this->reset('newRoleName', 'newRolePermissions');
        session()->flash('status', 'Role created.');
    }

    public function editRole(int $roleId, GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $role = $groupRoles->role($this->group, $roleId);
        abort_if(in_array($role->name, ['Owner', 'Member'], true), 422);
        $this->editingRoleId = $role->id;
        $this->editingRoleName = $role->name;
        $this->editingRolePermissions = $role->permissions->pluck('name')->all();
    }

    public function updateRole(GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $data = $this->validate(['editingRoleId' => ['required', 'integer'], 'editingRoleName' => ['required', 'string', 'max:80'], 'editingRolePermissions' => ['array'], 'editingRolePermissions.*' => ['string', 'in:'.implode(',', GroupRoleProvisioner::permissionNames())]]);
        $groupRoles->updateRole($this->group, $groupRoles->role($this->group, $data['editingRoleId']), $data['editingRoleName'], $data['editingRolePermissions']);
        $this->reset('editingRoleId', 'editingRoleName', 'editingRolePermissions');
        session()->flash('status', 'Role updated.');
    }

    public function deleteRole(int $roleId, GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $groupRoles->deleteRole($this->group, $groupRoles->role($this->group, $roleId));
        session()->flash('status', 'Role deleted.');
    }

    public function requestRole(int $membershipId, GroupRoleProvisioner $groupRoles): void
    {
        $membership = $this->group->memberships()->where('status', 'active')->findOrFail($membershipId);
        abort_unless($membership->actor_id === auth()->user()->actor->id, 403);
        $roleId = (int) ($this->requestedRoles[$membershipId] ?? 0);
        abort_if($roleId === 0, 422, 'Choose a role first.');
        $role = $groupRoles->role($this->group, $roleId);
        abort_if(in_array($role->name, ['Owner'], true), 422, 'Owner assignments require an existing Owner to approve the request.');
        GroupRoleChangeRequest::updateOrCreate(['membership_id' => $membership->id, 'status' => 'pending'], ['group_id' => $this->group->id, 'requested_role_id' => $role->id]);
        session()->flash('status', 'Role change requested.');
    }

    public function reviewRoleRequest(int $requestId, bool $approved, GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $request = GroupRoleChangeRequest::query()->where('group_id', $this->group->id)->where('status', 'pending')->with(['membership.actor', 'requestedRole'])->findOrFail($requestId);
        abort_unless($request->membership->status === 'active' && (int) $request->requestedRole->group_id === (int) $this->group->id, 422);
        DB::transaction(function () use ($request, $approved, $groupRoles): void {
            if ($approved) {
                $groupRoles->assign($request->membership->actor, $this->group, $request->requestedRole);
            }
            $request->update(['status' => $approved ? 'approved' : 'rejected', 'reviewed_by_actor_id' => auth()->user()->actor->id, 'reviewed_at' => now()]);
        });
        session()->flash('status', $approved ? 'Role change approved.' : 'Role change rejected.');
    }

    public function removeMember(int $membershipId, GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
        $membership = $this->group->memberships()->with('actor')->findOrFail($membershipId);
        if ($groupRoles->hasRole($membership->actor, $this->group, 'Owner') && $this->ownerCount($groupRoles) === 1) {
            session()->flash('error', 'Add another Owner before removing this member.');
            return;
        }
        $membership->update(['status' => 'removed']);
        session()->flash('status', 'Member removed.');
    }

    public function render(GroupRoleProvisioner $groupRoles): View
    {
        $memberships = $this->group->memberships()->with('actor.user')->where('status', 'active')->get();
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [$membership->id => $groupRoles->roleName($membership->actor, $this->group) ?? 'Member']);
        $isOwner = $groupRoles->hasRole(auth()->user()->actor, $this->group, 'Owner');
        $availableRoles = $groupRoles->roles($this->group);
        $pendingRequests = $isOwner ? GroupRoleChangeRequest::query()->where('group_id', $this->group->id)->where('status', 'pending')->with(['membership.actor.user', 'requestedRole'])->latest()->get() : collect();
        $permissionNames = GroupRoleProvisioner::permissionNames();

        return view('livewire.groups.show', compact('memberships', 'roles', 'isOwner', 'availableRoles', 'pendingRequests', 'permissionNames'));
    }

    private function ensureOwner(GroupRoleProvisioner $groupRoles): void
    {
        abort_unless($groupRoles->hasRole(auth()->user()->actor, $this->group, 'Owner'), 403);
    }

    private function ownerCount(GroupRoleProvisioner $groupRoles): int
    {
        return $this->group->memberships()->with('actor')->where('status', 'active')->get()->filter(fn (GroupMembership $membership): bool => $groupRoles->hasRole($membership->actor, $this->group, 'Owner'))->count();
    }
}
