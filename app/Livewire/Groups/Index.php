<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Admission;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
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
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [
            $membership->group_id => $groupRoles->roleNames($actor, $membership->group)->join(', '),
        ]);
        $requiresAgreementAcceptance = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [
            $membership->group_id => Gate::forUser(auth()->user())->denies('view', $membership->group),
        ]);
        $receivedInvitations = Admission::query()
            ->with(['group', 'sourceInvitation.inviter.user'])
            ->where('candidate_actor_id', $actor->id)
            ->whereNotNull('source_invitation_id')
            ->latest()
            ->get();
        $sentInvitations = GroupInvitation::query()
            ->with(['group', 'admissions.candidate.user'])
            ->withCount('acceptances')
            ->where('invited_by_actor_id', $actor->id)
            ->latest()
            ->get();

        return view('livewire.groups.index', compact('memberships', 'roles', 'requiresAgreementAcceptance', 'receivedInvitations', 'sentInvitations'));
    }
}
