<?php

namespace App\Actions\Groups;

use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use Illuminate\Support\Facades\DB;

/**
 * A join link (GroupInvitation with join_mode=direct) admits its holder without
 * a manual review. The admission still records every lifecycle step, so the
 * audit trail is the same as a reviewed admission; only the reviewer is the
 * join link instead of a person.
 */
class CompleteJoinLinkAdmission
{
    public function __construct(
        private readonly ManageAdmission $admissions,
        private readonly FinalizeAdmission $finalize,
        private readonly GroupRoleProvisioner $roles,
    ) {}

    /** Returns the new membership, or null when the candidate must still act (for example accept agreements). */
    public function complete(Admission $admission): ?GroupMembership
    {
        return DB::transaction(function () use ($admission): ?GroupMembership {
            /** @var Admission $admission */
            $admission = Admission::query()->with('sourceInvitation')->lockForUpdate()->findOrFail($admission->id);
            $invitation = $admission->sourceInvitation;

            if (! $invitation instanceof GroupInvitation || ! $invitation->admitsDirectly()) {
                return null;
            }

            /** @var Actor $candidate */
            $candidate = Actor::query()->findOrFail($admission->candidate_actor_id);

            if ($admission->status === 'draft') {
                if ($this->hasRequiredAgreements((int) $admission->group_id)) {
                    return null;
                }

                $this->admissions->candidateTransition($admission, $candidate, 'submitted', 'Joined through a group join link.');
                $admission->refresh();
            }

            if ($admission->status === 'submitted') {
                $admission->transitionTo('under_review', null, 'Join link admits directly.', ['join_link_invitation_id' => $invitation->id]);
                $admission->transitionTo('approved', null, 'Join link admits directly.', ['join_link_invitation_id' => $invitation->id]);
            }

            if ($admission->status !== 'approved') {
                return null;
            }

            $membership = $this->finalize->execute($admission);
            $this->grantInvitationRole($invitation, $candidate);

            return $membership;
        });
    }

    private function hasRequiredAgreements(int $groupId): bool
    {
        return GroupAgreementVersion::query()
            ->with('agreement')
            ->whereHas('agreement', fn ($query) => $query->where('group_id', $groupId)->where('required_for_admission', true))
            ->get()
            ->contains(fn (GroupAgreementVersion $version): bool => $version->isActiveAt());
    }

    private function grantInvitationRole(GroupInvitation $invitation, Actor $candidate): void
    {
        if ($invitation->granted_role_id === null) {
            return;
        }

        /** @var Group $group */
        $group = Group::query()->findOrFail($invitation->group_id);
        $role = $this->roles->role($group, (int) $invitation->granted_role_id);

        // A link can never hand out ownership; built-in Member is granted anyway.
        if (in_array($role->getAttribute('system_key'), [GroupRoleKey::Owner->value, GroupRoleKey::Member->value], true)) {
            return;
        }

        $this->roles->grant($candidate, $group, $role);
    }
}
