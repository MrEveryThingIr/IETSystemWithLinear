<?php

namespace App\Livewire\Groups;

use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groups')]
class Index extends Component
{
    public function createInvitation(int $groupId): void
    {
        $actor = auth()->user()->actor;
        $membership = GroupMembership::query()
            ->where('group_id', $groupId)
            ->where('actor_id', $actor->id)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->firstOrFail();

        $invitation = GroupInvitation::create([
            'group_id' => $membership->group_id,
            'invited_by_actor_id' => $actor->id,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(14),
        ]);

        session()->flash('status', 'Invitation link: '.route('invitations.show', ['token' => $invitation->token]));
    }

    public function render(): View
    {
        $actor = auth()->user()->actor;

        return view('livewire.groups.index', [
            'memberships' => GroupMembership::query()
                ->with('group')
                ->where('actor_id', $actor->id)
                ->where('status', 'active')
                ->latest()
                ->get(),
        ]);
    }
}
