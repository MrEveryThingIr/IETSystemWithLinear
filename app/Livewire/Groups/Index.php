<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\TransferGroupOwnership;
use App\Actions\Platform\ReviewPlatformAccessRequest;
use App\Models\Admission;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\GroupOwnershipTransferRequest;
use App\Models\PlatformAccessRequest;
use App\Models\User;
use App\PlatformCapability;
use App\PlatformRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groups')]
class Index extends Component
{
    /** @var array<int, string> */
    public array $accessReviewNotes = [];

    public function respondToOwnershipTransfer(int $requestId, bool $accepted, TransferGroupOwnership $transfers): void
    {
        $actor = auth()->user()->actor;
        $request = GroupOwnershipTransferRequest::query()->where('status', 'pending')->findOrFail($requestId);
        $transfers->respond($request, $actor, $accepted);
        session()->flash('status', $accepted ? __('ui.messages.ownership_transferred') : __('ui.messages.ownership_transfer_rejected'));
    }

    public function reviewGroupCreationAccess(int $requestId, bool $approved, ReviewPlatformAccessRequest $reviewAccess): void
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->hasPlatformCapability(PlatformCapability::ManagePlatformAccess), 403);

        $data = $this->validate([
            "accessReviewNotes.{$requestId}" => ['nullable', 'string', 'max:2000'],
        ]);

        $accessRequest = PlatformAccessRequest::query()
            ->where('status', 'pending')
            ->where('role', PlatformRole::GroupCreator->value)
            ->findOrFail($requestId);

        $reviewAccess->execute(
            $accessRequest,
            $user,
            $approved,
            $data['accessReviewNotes'][$requestId] ?? null,
        );

        unset($this->accessReviewNotes[$requestId]);
        session()->flash('status', $approved ? 'Group-creation access approved.' : 'Group-creation access request rejected.');
    }

    public function render(GroupRoleProvisioner $groupRoles): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $actor = $user->actor;
        $memberships = GroupMembership::query()->with('group')->where('actor_id', $actor->id)->where('status', 'active')->latest()->get();
        $roles = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [
            $membership->group_id => $groupRoles->roleNames($actor, $membership->group)->join(', '),
        ]);
        $requiresAgreementAcceptance = $memberships->mapWithKeys(fn (GroupMembership $membership): array => [
            $membership->group_id => Gate::forUser($user)->denies('view', $membership->group),
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

        $myPendingGroupCreationRequest = PlatformAccessRequest::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::GroupCreator->value)
            ->where('status', 'pending')
            ->first();

        $canManagePlatformAccess = $user->hasPlatformCapability(PlatformCapability::ManagePlatformAccess);
        $pendingGroupCreationRequests = $canManagePlatformAccess
            ? PlatformAccessRequest::query()
                ->where('role', PlatformRole::GroupCreator->value)
                ->where('status', 'pending')
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->oldest()
                ->get()
            : collect();

        return view('livewire.groups.index', compact(
            'memberships',
            'roles',
            'requiresAgreementAcceptance',
            'receivedInvitations',
            'sentInvitations',
            'ownershipTransfers',
            'myPendingGroupCreationRequest',
            'canManagePlatformAccess',
            'pendingGroupCreationRequests',
        ));
    }
}
