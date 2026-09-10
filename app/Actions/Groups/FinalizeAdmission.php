<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeAdmission
{
    public function __construct(private GroupRoleProvisioner $roles) {}

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
            $accepted = AgreementAcceptance::query()->where('admission_id', $admission->id)->whereIn('group_agreement_version_id', $requiredVersionIds)->count();
            if ($accepted !== $requiredVersionIds->count()) {
                throw ValidationException::withMessages(['agreements' => 'Every active required agreement version must be accepted before finalization.']);
            }

            /** @var GroupMembership|null $membership */
            $membership = GroupMembership::query()->where('group_id', $group->id)->where('actor_id', $actor->id)->lockForUpdate()->first();
            abort_if($membership !== null && $membership->status === 'active', 422, 'Candidate already has an active membership.');
            $membership ??= new GroupMembership(['group_id' => $group->id, 'actor_id' => $actor->id]);
            $membership->status = 'active';
            $membership->save();

            $roles = $this->roles->provision($group);
            $this->roles->assign($actor, $group, $roles['member']);
            $admission->events()->create(['event' => 'admission.finalized', 'metadata' => ['membership_id' => $membership->id]]);

            return $membership;
        });
    }
}
