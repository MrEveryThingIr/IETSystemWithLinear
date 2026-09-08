<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Group;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Group')]
class Show extends Component
{
    public Group $group;

    public function mount(Group $group): void
    {
        abort_unless($group->memberships()->where('actor_id', auth()->user()->actor->id)->where('status', 'active')->exists(), 403);
        $this->group = $group;
    }

    public function changeRole(int $membershipId, string $role, GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);
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

    public function removeMember(int $membershipId, GroupRoleProvisioner $groupRoles): void
    {
        $this->ensureOwner($groupRoles);

        DB::transaction(function () use ($membershipId, $groupRoles): void {
            $membership = $this->group->memberships()->with('actor')->findOrFail($membershipId);
            if ($groupRoles->hasRole($membership->actor, $this->group, 'Owner') && $this->ownerCount($groupRoles) === 1) {
                abort(422, 'A group must retain at least one owner.');
            }
            $membership->update(['status' => 'removed']);
        });
    }

    public function render(GroupRoleProvisioner $groupRoles): View
    {
        $memberships = $this->group->memberships()->with('actor.user')->where('status', 'active')->get();
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [$membership->id => $groupRoles->roleName($membership->actor, $this->group) ?? 'Member']);
        $isOwner = $groupRoles->hasRole(auth()->user()->actor, $this->group, 'Owner');

        return view('livewire.groups.show', compact('memberships', 'roles', 'isOwner'));
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
