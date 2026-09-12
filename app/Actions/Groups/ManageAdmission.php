<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\GroupAgreementVersion;
use App\Support\AgreementEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageAdmission
{
    public function accept(Admission $admission, Actor $actor, GroupAgreementVersion $version): void
    {
        DB::transaction(function () use ($admission, $actor, $version): void {
            /** @var Admission $lockedAdmission */
            $lockedAdmission = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            /** @var GroupAgreementVersion $lockedVersion */
            $lockedVersion = GroupAgreementVersion::query()->with('agreement')->lockForUpdate()->findOrFail($version->id);

            abort_unless((int) $lockedAdmission->candidate_actor_id === (int) $actor->id, 403);
            if (in_array($lockedAdmission->status, ['finalized', 'rejected', 'cancelled'], true)) {
                throw ValidationException::withMessages(['agreements' => __('ui.messages.closed_admission_agreement')]);
            }
            abort_unless((int) $lockedVersion->agreement->group_id === (int) $lockedAdmission->group_id && $lockedVersion->agreement->required_for_admission && $lockedVersion->isActiveAt(), 422);

            $acceptance = AgreementAcceptance::query()
                ->where('admission_id', $lockedAdmission->id)
                ->where('group_agreement_version_id', $lockedVersion->id)
                ->lockForUpdate()
                ->first();

            if ($acceptance instanceof AgreementAcceptance) {
                return;
            }

            $acceptance = AgreementAcceptance::create([
                'admission_id' => $lockedAdmission->id,
                'group_agreement_version_id' => $lockedVersion->id,
                ...AgreementEvidence::forAcceptance($lockedVersion, $actor),
            ]);
            $lockedAdmission->events()->create([
                'actor_id' => $actor->id,
                'event' => 'agreement.accepted',
                'metadata' => [
                    'version_id' => $lockedVersion->id,
                    'acceptance_id' => $acceptance->id,
                    'evidence_hash' => $acceptance->evidence_hash,
                    'evidence_schema_version' => $acceptance->evidence_schema_version,
                ],
            ]);
        }, attempts: 3);
    }

    public function candidateTransition(Admission $admission, Actor $actor, string $status, ?string $note = null): void
    {
        abort_unless(in_array($status, ['submitted', 'cancelled'], true), 422);
        DB::transaction(function () use ($admission, $actor, $status, $note): void {
            /** @var Admission $locked */
            $locked = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            abort_unless((int) $locked->candidate_actor_id === (int) $actor->id, 403);
            $locked->transitionTo($status, $actor, $note);
        });
    }

    public function review(Admission $admission, Actor $reviewer, string $status, ?string $note = null): void
    {
        abort_unless(in_array($status, ['clarification_required', 'under_review', 'approved', 'rejected'], true), 422);
        DB::transaction(function () use ($admission, $reviewer, $status, $note): void { /** @var Admission $locked */ $locked = Admission::query()->lockForUpdate()->findOrFail($admission->id);
            $locked->transitionTo($status, $reviewer, $note);
        });
    }
}
