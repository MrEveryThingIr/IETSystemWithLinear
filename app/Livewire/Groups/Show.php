<?php

namespace App\Livewire\Groups;

use App\Actions\Administration\GlobalAccess;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function mount(Group $group, GlobalAccess $access): void
    {
        $user = auth()->user();
        $isMember = $group->memberships()->where('actor_id', $user->actor->id)->where('status', 'active')->exists();
        abort_unless($isMember || $access->can($user, 'groups.manage'), 403);
        $this->group = $group;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
    }

    public function save(GroupRoleProvisioner $groupRoles, GlobalAccess $access): void
    {
        $this->ensureGroupManager($groupRoles, $access);
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $this->group->update(['name' => $data['name'], 'description' => $data['description'] ?: null]);
        session()->flash('status', 'Group details updated.');
    }

    public function createInvitation(GroupRoleProvisioner $groupRoles, GlobalAccess $access): void
    {
        $this->ensureGroupManager($groupRoles, $access);
        $invitation = GroupInvitation::create(['group_id' => $this->group->id, 'invited_by_actor_id' => auth()->user()->actor->id, 'token' => Str::random(48), 'expires_at' => now()->addDays(14), 'max_uses' => 100]);
        session()->flash('status', 'Invitation link: '.route('invitations.show', $invitation->token));
    }

    public function changeRole(int $membershipId, string $role, GroupRoleProvisioner $groupRoles, GlobalAccess $access): void
    {
        $this->ensureGroupManager($groupRoles, $access);
        abort_unless(in_array($role, ['Owner', 'Member'], true), 422);
        DB::transaction(function () use ($membershipId, $role, $groupRoles): void {
            $membership = $this->group->memberships()->with('actor')->findOrFail($membershipId);
            if ($groupRoles->hasRole($membership->actor, $this->group, 'Owner') && $role === 'Member' && $this->ownerCount($groupRoles) === 1) {
                abort(422, 'A group must retain at least one owner.');
            }
            $roles = $groupRoles->provision($this->group);
            $groupRoles->assign($membership->actor, $this->group, $roles[strtolower($role)]);
        });
    }

    public function removeMember(int $membershipId, GroupRoleProvisioner $groupRoles, GlobalAccess $access): void
    {
        $this->ensureGroupManager($groupRoles, $access);
        DB::transaction(function () use ($membershipId, $groupRoles): void {
            $membership = $this->group->memberships()->with('actor')->findOrFail($membershipId);
            if ($groupRoles->hasRole($membership->actor, $this->group, 'Owner') && $this->ownerCount($groupRoles) === 1) {
                abort(422, 'A group must retain at least one owner.');
            }
            $membership->update(['status' => 'removed']);
        });
    }

    public function render(GroupRoleProvisioner $groupRoles, GlobalAccess $access): View
    {
        $memberships = $this->group->memberships()->with('actor.user')->where('status', 'active')->get();
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [$membership->id => $groupRoles->roleName($membership->actor, $this->group) ?? 'Member']);
        $isOwner = $groupRoles->hasRole(auth()->user()->actor, $this->group, 'Owner') || $access->can(auth()->user(), 'groups.manage');

        return view('livewire.groups.show', compact('memberships', 'roles', 'isOwner'));
    }

    private function ensureGroupManager(GroupRoleProvisioner $groupRoles, GlobalAccess $access): void
    {
        abort_unless($groupRoles->hasRole(auth()->user()->actor, $this->group, 'Owner') || $access->can(auth()->user(), 'groups.manage'), 403);
    }

    private function ownerCount(GroupRoleProvisioner $groupRoles): int
    {
        return $this->group->memberships()->with('actor')->where('status', 'active')->get()->filter(fn (GroupMembership $membership): bool => $groupRoles->hasRole($membership->actor, $this->group, 'Owner'))->count();
    }
}
