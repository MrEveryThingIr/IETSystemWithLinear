<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Support\Facades\DB;

class ManageAdmission
{
    public function accept(Admission $admission, Actor $actor, GroupAgreementVersion $version): void
    {
        /** @var GroupAgreement $agreement */
        $agreement = GroupAgreement::query()->findOrFail($version->group_agreement_id);
        abort_unless((int) $admission->candidate_actor_id === (int) $actor->id, 403);
        abort_unless((int) $agreement->group_id === (int) $admission->group_id && $version->isActiveAt(), 422);
        AgreementAcceptance::firstOrCreate(['admission_id' => $admission->id, 'group_agreement_version_id' => $version->id], ['accepted_by_actor_id' => $actor->id, 'accepted_at' => now(), 'evidence_hash' => hash('sha256', $version->content)]);
        $admission->events()->create(['actor_id' => $actor->id, 'event' => 'agreement.accepted', 'metadata' => ['version_id' => $version->id]]);
    }
    public function candidateTransition(Admission $admission, Actor $actor, string $status, ?string $note = null): void { abort_unless((int) $admission->candidate_actor_id === (int) $actor->id, 403); abort_unless(in_array($status, ['submitted', 'cancelled'], true), 422); $admission->transitionTo($status, $actor, $note); }
    public function review(Admission $admission, Actor $reviewer, string $status, ?string $note = null): void { abort_unless(in_array($status, ['clarification_required', 'under_review', 'approved', 'rejected'], true), 422); DB::transaction(function () use ($admission, $reviewer, $status, $note): void { /** @var Admission $locked */ $locked = Admission::query()->lockForUpdate()->findOrFail($admission->id); $locked->transitionTo($status, $reviewer, $note); }); }
}
