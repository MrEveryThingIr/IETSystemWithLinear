<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\TransferGroupOwnership;
use App\Models\Admission;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\GroupOwnershipTransferRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groups')]
class Index extends Component
{
    public function respondToOwnershipTransfer(int $requestId, bool $accepted, TransferGroupOwnership $transfers): void
    {
        $actor = auth()->user()->actor;
        $request = GroupOwnershipTransferRequest::query()->where('status', 'pending')->findOrFail($requestId);
        $transfers->respond($request, $actor, $accepted);
        session()->flash('status', $accepted ? __('ui.messages.ownership_transferred') : __('ui.messages.ownership_transfer_rejected'));
    }

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
        $ownershipTransfers = GroupOwnershipTransferRequest::query()
            ->with(['group', 'sourceMembership.actor.user'])
            ->whereHas('targetMembership', fn ($query) => $query->where('actor_id', $actor->id)->where('status', 'active'))
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('livewire.groups.index', compact('memberships', 'roles', 'requiresAgreementAcceptance', 'receivedInvitations', 'sentInvitations', 'ownershipTransfers'));
    }
}
