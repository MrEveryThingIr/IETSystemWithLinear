<?php

namespace App\Livewire\Groups;

use App\Actions\Administration\GlobalAccess;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Group;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groups')]
class Index extends Component
{
    public function render(GroupRoleProvisioner $groupRoles, GlobalAccess $access): View
    {
        $user = auth()->user();
        $actor = $user->actor;
        $isGlobalManager = $access->can($user, 'groups.manage');
        $groups = $isGlobalManager
            ? Group::query()->latest()->get()
            : Group::query()->whereHas('memberships', fn ($query) => $query->where('actor_id', $actor->id)->where('status', 'active'))->latest()->get();

        $roles = $groups->mapWithKeys(function (Group $group) use ($actor, $groupRoles, $isGlobalManager): array {
            $role = $groupRoles->roleName($actor, $group);

            return [$group->id => $role ?? ($isGlobalManager ? 'Global manager' : 'Member')];
        });

        return view('livewire.groups.index', compact('groups', 'roles'));
    }
}
