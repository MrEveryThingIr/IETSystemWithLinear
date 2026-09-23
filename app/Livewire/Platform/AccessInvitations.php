<?php

namespace App\Livewire\Platform;

use App\Actions\Access\IssueAccessInvitation;
use App\Actions\Access\RevokeAccessInvitation;
use App\Models\AccessInvitation;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Access invitations')]
class AccessInvitations extends Component
{
    use WithPagination;

    public string $email = '';

    public int $maxUses = 1;

    public int $expiresInDays = 14;

    public ?string $createdInvitationUrl = null;

    public function mount(): void
    {
        $this->authorizeUser();
    }

    public function create(IssueAccessInvitation $issue): void
    {
        $user = $this->authorizeUser();

        $data = $this->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'maxUses' => ['required', 'integer', 'min:1', 'max:1000'],
            'expiresInDays' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $invitation = $issue->execute(
            $user,
            filled($data['email']) ? $data['email'] : null,
            (int) $data['maxUses'],
            (int) $data['expiresInDays'],
        );

        $token = $invitation->plainTextToken();
        abort_unless(is_string($token), 500);

        $this->createdInvitationUrl = route('access-invitations.show', ['token' => $token]);
        $this->reset('email');
        $this->maxUses = 1;
        $this->expiresInDays = 14;
        $this->resetPage();

        session()->flash('status', __('access.admin.created'));
    }

    public function revoke(int $id, RevokeAccessInvitation $revoke): void
    {
        $user = $this->authorizeUser();
        $invitation = AccessInvitation::query()->findOrFail($id);
        $revoke->execute($user, $invitation);

        session()->flash('status', __('access.admin.revoked'));
    }

    public function render(): View
    {
        $this->authorizeUser();

        return view('livewire.platform.access-invitations', [
            'invitations' => AccessInvitation::query()
                ->with(['inviter.user', 'acceptances.user'])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    private function authorizeUser(): User
    {
        $user = request()->user();

        abort_unless(
            $user instanceof User && $user->hasPlatformCapability(PlatformCapability::ManageUsers),
            403,
        );

        return $user;
    }
}
