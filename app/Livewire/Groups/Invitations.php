<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\RevokeGroupInvitation;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Invitations extends Component
{
    public Group $group;

    public string $email = '';

    public ?int $maxUses = 1;

    public function mount(Group $group): void
    {
        Gate::authorize('createInvitation', $group);
        $this->group = $group;
    }

    public function create(): void
    {
        $data = $this->validate(['email' => ['nullable', 'email'], 'maxUses' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $actor = $this->actor();
        GroupInvitation::create(['group_id' => $this->group->id, 'invited_by_actor_id' => $actor->id, 'email' => $data['email'] ?: null, 'token' => Str::random(48), 'expires_at' => now()->addDays(14), 'max_uses' => $data['maxUses'], 'uses_count' => 0]);
        $this->reset('email');
    }

    public function revoke(int $id, RevokeGroupInvitation $revoker): void
    { /** @var GroupInvitation $invitation */ $invitation = GroupInvitation::query()->where('group_id', $this->group->id)->findOrFail($id);
        $revoker->execute($invitation);
    }

    public function render(): View
    {
        return view('livewire.groups.invitations', ['invitations' => GroupInvitation::query()->where('group_id', $this->group->id)->latest()->get()]);
    }

    private function actor(): Actor
    { /** @var Actor $actor */ $actor = Actor::query()->where('user_id', auth()->id())->firstOrFail();

        return $actor;
    }
}
