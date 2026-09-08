<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Group;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Group')]
class Create extends Component
{
    public string $name = '';
    public string $description = '';

    public function save(GroupRoleProvisioner $groupRoles): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $group = DB::transaction(function () use ($data, $groupRoles): Group {
            $actor = auth()->user()->actor;
            $group = Group::create(['name' => $data['name'], 'description' => $data['description'] ?: null, 'created_by_actor_id' => $actor->id]);
            $roles = $groupRoles->provision($group);
            $groupRoles->assign($actor, $group, $roles['owner']);
            $group->memberships()->create(['actor_id' => $actor->id, 'status' => 'active']);
            return $group;
        });
        $this->redirectRoute('groups.show', $group);
    }

    public function render(): View
    {
        return view('livewire.groups.create');
    }
}
