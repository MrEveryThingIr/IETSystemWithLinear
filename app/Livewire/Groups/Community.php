<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\User;
use App\Support\GroupCommunityProjection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Community')]
class Community extends Component
{
    public Group $group;

    public function mount(Group $group): void
    {
        Gate::forUser($this->user())->authorize('view', $group);
        $this->group = $group;
    }

    public function render(GroupCommunityProjection $projection): View
    {
        $group = Group::query()->findOrFail($this->group->id);
        Gate::forUser($this->user())->authorize('view', $group);
        $this->group = $group;

        return view('livewire.groups.community', $projection->build($group, $this->user()));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
