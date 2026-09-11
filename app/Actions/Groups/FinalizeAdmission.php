<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\MembershipAgreementAcceptance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeAdmission
{
    public function __construct(
        private GroupRoleProvisioner $roles,
        private TransitionGroupMembership $memberships,
    ) {}

    public function execute(Admission $admission): GroupMembership
    {
        return DB::transaction(function () use ($admission): GroupMembership {
            /** @var Admission $admission */
            $admission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            /** @var Group $group */
            $group = Group::query()->findOrFail($admission->group_id);
            abort_unless($admission->status === 'approved', 422, 'Only approved admissions can be finalized.');

            /** @var Actor $actor */
            $actor = Actor::query()->with('user')->lockForUpdate()->findOrFail($admission->candidate_actor_id);
            abort_unless($actor->user !== null && $actor->user->status === 'active', 422, 'Candidate is not eligible for membership.');

            $requiredVersionIds = GroupAgreementVersion::query()
                ->whereHas('agreement', fn ($query) => $query->where('group_id', $group->id)->where('required_for_admission', true))
                ->get()
                ->filter(fn (GroupAgreementVersion $version) => $version->isActiveAt())
                ->pluck('id');
            $accepted = AgreementAcceptance::query()->where('admission_id', $admission->id)->where('accepted_by_actor_id', $actor->id)->whereIn('group_agreement_version_id', $requiredVersionIds)->count();
            if ($accepted !== $requiredVersionIds->count()) {
                throw ValidationException::withMessages(['agreements' => __('ui.messages.required_agreements_unaccepted')]);
            }

            /** @var GroupMembership|null $membership */
            $membership = GroupMembership::query()->where('group_id', $group->id)->where('actor_id', $actor->id)->lockForUpdate()->first();
            abort_if($membership !== null && in_array($membership->status, ['active', 'suspended'], true), 422, 'Candidate already has a current membership.');
            $roles = $this->roles->provision($group);

            if ($membership instanceof GroupMembership) {
                $membership = $this->memberships->readmit($membership, null, 'Membership restored through an approved admission.');
            } else {
                $membership = new GroupMembership(['group_id' => $group->id, 'actor_id' => $actor->id, 'status' => 'active']);
                $membership->save();
                $this->roles->grant($actor, $group, $roles['member']);
                $this->memberships->recordInitial($membership, null, 'Membership created through an approved admission.');
            }

            AgreementAcceptance::query()
                ->where('admission_id', $admission->id)
                ->where('accepted_by_actor_id', $actor->id)
                ->whereIn('group_agreement_version_id', $requiredVersionIds)
                ->each(function (AgreementAcceptance $acceptance) use ($membership, $actor): void {
                    MembershipAgreementAcceptance::query()->firstOrCreate(
                        ['group_membership_id' => $membership->id, 'group_agreement_version_id' => $acceptance->group_agreement_version_id],
                        ['accepted_by_actor_id' => $actor->id, 'accepted_at' => $acceptance->accepted_at, 'evidence_hash' => $acceptance->evidence_hash],
                    );
                });

            $admission->transitionTo('finalized', null, null, ['membership_id' => $membership->id]);

            return $membership;
        });
    }
}
