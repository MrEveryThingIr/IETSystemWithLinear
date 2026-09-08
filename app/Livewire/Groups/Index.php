<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groups')]
class Index extends Component
{
    public function render(GroupRoleProvisioner $groupRoles): View
    {
        $actor = auth()->user()->actor;
        $memberships = GroupMembership::query()->with('group')->where('actor_id', $actor->id)->where('status', 'active')->latest()->get();
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [$membership->group_id => $groupRoles->roleName($actor, $membership->group) ?? 'Member']);

        return view('livewire.groups.index', compact('memberships', 'roles'));
    }
}
