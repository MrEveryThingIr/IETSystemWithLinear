<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\MembershipAgreementAcceptance;
use App\Support\AgreementEvidence;
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
            $group = Group::query()->lockForUpdate()->findOrFail($admission->group_id);
            abort_unless($admission->status === 'approved', 422, 'Only approved admissions can be finalized.');

            /** @var Actor $actor */
            $actor = Actor::query()->with('user')->lockForUpdate()->findOrFail($admission->candidate_actor_id);
            abort_unless($actor->user !== null && $actor->user->status === 'active', 422, 'Candidate is not eligible for membership.');

            $requiredVersions = GroupAgreementVersion::query()
                ->whereHas('agreement', fn ($query) => $query->where('group_id', $group->id)->where('required_for_admission', true))
                ->with('agreement')
                ->lockForUpdate()
                ->get()
                ->filter(fn (GroupAgreementVersion $version) => $version->isActiveAt());
            $requiredVersionIds = $requiredVersions->pluck('id');
            $acceptances = AgreementAcceptance::query()
                ->where('admission_id', $admission->id)
                ->where('accepted_by_actor_id', $actor->id)
                ->whereIn('group_agreement_version_id', $requiredVersionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('group_agreement_version_id');
            $hasExactEvidence = $requiredVersions->every(function (GroupAgreementVersion $version) use ($acceptances, $actor): bool {
                $acceptance = $acceptances->get($version->id);

                return $acceptance instanceof AgreementAcceptance
                    && AgreementEvidence::matchesAdmissionAcceptance($acceptance, $version, $actor);
            });

            if (! $hasExactEvidence) {
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

            $currentMembershipEvent = $membership->currentParticipationEvent();
            abort_unless($currentMembershipEvent !== null, 422, 'Membership history is incomplete.');

            AgreementAcceptance::query()
                ->where('admission_id', $admission->id)
                ->where('accepted_by_actor_id', $actor->id)
                ->whereIn('group_agreement_version_id', $requiredVersionIds)
                ->each(function (AgreementAcceptance $acceptance) use ($membership, $currentMembershipEvent, $actor): void {
                    MembershipAgreementAcceptance::query()->firstOrCreate(
                        ['source_admission_acceptance_id' => $acceptance->id],
                        [
                            'group_membership_id' => $membership->id,
                            'group_membership_event_id' => $currentMembershipEvent->id,
                            'group_agreement_version_id' => $acceptance->group_agreement_version_id,
                            'group_agreement_id' => $acceptance->group_agreement_id,
                            'version_number' => $acceptance->version_number,
                            'accepted_by_actor_id' => $actor->id,
                            'represented_actor_id' => $acceptance->represented_actor_id,
                            'acting_user_id' => $acceptance->acting_user_id,
                            'accepted_at' => $acceptance->accepted_at,
                            'evidence_hash' => $acceptance->evidence_hash,
                            'hash_algorithm' => $acceptance->hash_algorithm,
                            'version_effective_from' => $acceptance->version_effective_from,
                            'version_effective_until' => $acceptance->version_effective_until,
                            'required_for_admission' => $acceptance->required_for_admission,
                            'reacceptance_required' => $acceptance->reacceptance_required,
                            'evidence_schema_version' => $acceptance->evidence_schema_version,
                        ],
                    );
                });

            $admission->transitionTo('finalized', null, null, ['membership_id' => $membership->id]);

            return $membership;
        });
    }
}
