<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\RevokeGroupInvitation;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Invitations extends Component
{
    use WithPagination;

    public Group $group;

    public string $email = '';

    public ?int $maxUses = 1;

    public ?string $createdInvitationUrl = null;

    public function mount(Group $group): void
    {
        Gate::authorize('createInvitation', $group);
        $this->group = $group;
    }

    public function create(): void
    {
        Gate::authorize('createInvitation', $this->group);
        $data = $this->validate(['email' => ['required', 'email', 'exists:users,email']]);
        $target = User::query()->where('email', $data['email'])->firstOrFail();
        abort_unless($target->status === 'active' && $target->email_verified_at !== null, 422, __('access.group_existing_verified_only'));
        $actor = $this->actor();
        $token = GroupInvitation::issueToken();
        GroupInvitation::create(['group_id' => $this->group->id, 'invited_by_actor_id' => $actor->id, 'email' => $data['email'] ?: null, 'token' => $token, 'expires_at' => now()->addDays(14), 'max_uses' => 1, 'uses_count' => 0]);
        $this->createdInvitationUrl = route('invitations.show', $token);
        $this->reset('email');
        $this->maxUses = 1;
        $this->resetPage();
        session()->flash('status', __('ui.messages.invitation_created'));
    }

    public function revoke(int $id, RevokeGroupInvitation $revoker): void
    {
        Gate::authorize('createInvitation', $this->group);
        /** @var GroupInvitation $invitation */
        $invitation = GroupInvitation::query()->where('group_id', $this->group->id)->findOrFail($id);
        $revoker->execute($invitation);
    }

    public function render(): View
    {
        Gate::authorize('createInvitation', $this->group);

        return view('livewire.groups.invitations', [
            'invitations' => GroupInvitation::query()
                ->with(['admissions.candidate.user'])
                ->where('group_id', $this->group->id)
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    private function actor(): Actor
    { /** @var Actor $actor */ $actor = Actor::query()->where('user_id', auth()->id())->firstOrFail();

        return $actor;
    }
}
