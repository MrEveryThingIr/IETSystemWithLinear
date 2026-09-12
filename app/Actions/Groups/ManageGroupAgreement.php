<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\AgreementAcceptance;
use App\Models\AgreementEvent;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\MembershipAgreementAcceptance;
use App\Support\AgreementEvidence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManageGroupAgreement
{
    public function create(Group $group, Actor $actor, string $name, bool $required, string $content): GroupAgreement
    {
        return DB::transaction(function () use ($group, $actor, $name, $required, $content): GroupAgreement {
            $agreement = GroupAgreement::create(['group_id' => $group->id, 'name' => $name, 'required_for_admission' => $required]);
            $version = $agreement->versions()->create(['version' => 1, 'content' => $content, 'status' => 'draft', 'created_by_actor_id' => $actor->id]);
            $this->event($agreement, $version, $actor, 'agreement.version.created');

            return $agreement;
        });
    }

    public function revise(GroupAgreement $agreement, Actor $actor, string $content, string $rationale, bool $reacceptanceRequired): GroupAgreementVersion
    {
        return DB::transaction(function () use ($agreement, $actor, $content, $rationale, $reacceptanceRequired): GroupAgreementVersion {
            /** @var GroupAgreement $lockedAgreement */
            $lockedAgreement = GroupAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            $next = ((int) $lockedAgreement->versions()->max('version')) + 1;
            $version = $lockedAgreement->versions()->create(compact('content', 'rationale') + ['version' => $next, 'status' => 'draft', 'reacceptance_required' => $reacceptanceRequired, 'created_by_actor_id' => $actor->id]);
            $this->event($lockedAgreement, $version, $actor, 'agreement.revision.created', ['rationale' => $rationale]);

            return $version;
        });
    }

    public function propose(GroupAgreementVersion $version, Actor $actor): void
    {
        $this->transition($version, $actor, 'draft', 'proposed', 'agreement.revision.proposed');
    }

    public function requestClarification(GroupAgreementVersion $version, Actor $actor, string $note): void
    {
        $this->transition($version, $actor, 'proposed', 'clarification_requested', 'agreement.revision.clarification_requested', ['note' => $note], ['decision_note' => $note]);
    }

    public function approve(GroupAgreementVersion $version, Actor $actor): void
    {
        $this->transition($version, $actor, 'proposed', 'approved', 'agreement.revision.approved', [], ['approved_by_actor_id' => $actor->id, 'approved_at' => now()]);
    }

    public function reject(GroupAgreementVersion $version, Actor $actor, string $note): void
    {
        $this->transition($version, $actor, ['proposed', 'clarification_requested'], 'rejected', 'agreement.revision.rejected', ['note' => $note], ['decision_note' => $note]);
    }

    public function schedule(GroupAgreementVersion $version, Actor $actor, \DateTimeInterface $from, ?\DateTimeInterface $until = null): void
    {
        abort_unless($from > now(), 422, 'Approved revisions must be scheduled in the future.');
        abort_if($until !== null && $until <= $from, 422, 'The effective period is invalid.');
        $this->transition($version, $actor, 'approved', 'scheduled', 'agreement.revision.scheduled', ['effective_from' => $from->format(DATE_ATOM), 'effective_until' => $until?->format(DATE_ATOM)], ['effective_from' => $from, 'effective_until' => $until, 'published_at' => now()]);
    }

    public function activate(GroupAgreementVersion $version, Actor $actor): void
    {
        DB::transaction(function () use ($version, $actor): void {
            /** @var GroupAgreementVersion $version */
            $version = GroupAgreementVersion::query()->lockForUpdate()->findOrFail($version->id);
            abort_unless(in_array($version->status, ['approved', 'scheduled'], true), 422, 'Only an approved or scheduled agreement version can be activated.');
            abort_if($version->status === 'scheduled' && $version->effective_from?->isFuture(), 422, 'A scheduled agreement cannot be activated before its effective time.');

            $agreement = $this->agreementForVersion($version);
            /** @var GroupAgreementVersion|null $active */
            $active = GroupAgreementVersion::query()
                ->where('group_agreement_id', $agreement->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                $active->applyLifecycleTransition(['status' => 'superseded', 'effective_until' => now(), 'superseded_by_version_id' => $version->id]);
            }

            $version->applyLifecycleTransition([
                'status' => 'active',
                'effective_from' => $version->status === 'scheduled' ? $version->effective_from : now(),
                'published_at' => $version->published_at ?? now(),
                'activated_at' => now(),
            ]);
            $this->event($agreement, $version, $actor, 'agreement.revision.activated', ['superseded_version_id' => $active?->id]);
        });
    }

    public function activateDue(?\DateTimeInterface $at = null): int
    {
        $at = Carbon::instance($at ?? now());

        return DB::transaction(function () use ($at): int {
            $versions = GroupAgreementVersion::query()->where('status', 'scheduled')->where('effective_from', '<=', $at)->orderBy('effective_from')->orderBy('version')->orderBy('id')->lockForUpdate()->get();
            foreach ($versions as $version) {
                $agreement = $this->agreementForVersion($version);
                /** @var GroupAgreementVersion|null $active */ $active = GroupAgreementVersion::query()->where('group_agreement_id', $agreement->id)->where('status', 'active')->lockForUpdate()->first();
                if ($active !== null) {
                    $active->applyLifecycleTransition(['status' => 'superseded', 'effective_until' => $at, 'superseded_by_version_id' => $version->id]);
                }
                $version->applyLifecycleTransition(['status' => 'active', 'activated_at' => $at]);
                $this->event($agreement, $version, null, 'agreement.revision.activated', ['superseded_version_id' => $active?->id]);
            }

            return $versions->count();
        });
    }

    public function accept(AgreementAcceptance $acceptance, Actor $actor): void
    { /** @var GroupAgreementVersion $version */ $version = GroupAgreementVersion::query()->findOrFail($acceptance->group_agreement_version_id);
        $this->event($this->agreementForVersion($version), $version, $actor, 'agreement.accepted', ['admission_id' => $acceptance->admission_id, 'acceptance_id' => $acceptance->id, 'evidence_hash' => $acceptance->evidence_hash]);
    }

    public function acceptMembership(GroupMembership $membership, GroupAgreementVersion $version, Actor $actor): MembershipAgreementAcceptance
    {
        return DB::transaction(function () use ($membership, $version, $actor): MembershipAgreementAcceptance {
            /** @var GroupMembership $lockedMembership */
            $lockedMembership = GroupMembership::query()->lockForUpdate()->findOrFail($membership->id);
            /** @var GroupAgreementVersion $lockedVersion */
            $lockedVersion = GroupAgreementVersion::query()->with('agreement')->lockForUpdate()->findOrFail($version->id);
            abort_unless($lockedMembership->status === 'active' && $lockedMembership->actor_id === $actor->id && $lockedVersion->isActiveAt() && $lockedVersion->reacceptance_required, 422, 'This agreement version cannot be accepted.');
            abort_unless($lockedVersion->agreement->group_id === $lockedMembership->group_id, 422, 'Agreement does not belong to this membership.');
            $currentMembershipEvent = $lockedMembership->currentParticipationEvent();
            abort_unless($currentMembershipEvent !== null, 422, 'Membership history is incomplete.');

            $acceptance = MembershipAgreementAcceptance::query()
                ->where('group_membership_id', $lockedMembership->id)
                ->where('group_agreement_version_id', $lockedVersion->id)
                ->where('group_membership_event_id', $currentMembershipEvent->id)
                ->lockForUpdate()
                ->first();

            if ($acceptance instanceof MembershipAgreementAcceptance) {
                return $acceptance;
            }

            $acceptance = MembershipAgreementAcceptance::create([
                'group_membership_id' => $lockedMembership->id,
                'group_membership_event_id' => $currentMembershipEvent->id,
                'group_agreement_version_id' => $lockedVersion->id,
                ...AgreementEvidence::forAcceptance($lockedVersion, $actor),
            ]);
            $this->event($lockedVersion->agreement, $lockedVersion, $actor, 'agreement.membership_accepted', [
                'membership_id' => $lockedMembership->id,
                'acceptance_id' => $acceptance->id,
                'evidence_hash' => $acceptance->evidence_hash,
                'evidence_schema_version' => $acceptance->evidence_schema_version,
            ]);

            return $acceptance;
        }, attempts: 3);
    }

    /**
     * @param  string|list<string>  $from
     * @param  array<string, mixed>  $metadata
     * @param  array{
     *     decision_note?: string,
     *     approved_by_actor_id?: int,
     *     approved_at?: \DateTimeInterface,
     *     effective_from?: \DateTimeInterface,
     *     effective_until?: \DateTimeInterface|null,
     *     published_at?: \DateTimeInterface
     * }  $attributes
     */
    private function transition(GroupAgreementVersion $version, Actor $actor, string|array $from, string $to, string $event, array $metadata = [], array $attributes = []): void
    {
        DB::transaction(function () use ($version, $actor, $from, $to, $event, $metadata, $attributes): void { /** @var GroupAgreementVersion $version */ $version = GroupAgreementVersion::query()->lockForUpdate()->findOrFail($version->id);
            abort_unless(in_array($version->status, (array) $from, true), 422, 'Invalid agreement lifecycle transition.');
            $version->applyLifecycleTransition(['status' => $to] + $attributes);
            $this->event($this->agreementForVersion($version), $version, $actor, $event, $metadata);
        });
    }

    private function agreementForVersion(GroupAgreementVersion $version): GroupAgreement
    { /** @var GroupAgreement $agreement */ $agreement = GroupAgreement::query()->findOrFail($version->group_agreement_id);

        return $agreement;
    }

    private function event(GroupAgreement $agreement, ?GroupAgreementVersion $version, ?Actor $actor, string $event, array $metadata = []): void
    {
        AgreementEvent::create(['group_agreement_id' => $agreement->id, 'group_agreement_version_id' => $version?->id, 'actor_id' => $actor?->id, 'event' => $event, 'metadata' => $metadata]);
    }
}
