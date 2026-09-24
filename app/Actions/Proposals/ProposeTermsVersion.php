<?php

namespace App\Actions\Proposals;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalEvent;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalDecisionKind;
use App\ProposalEventType;
use App\ProposalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProposeTermsVersion
{
    public function __construct(private readonly PublishProposalTerms $terms) {}

    public function execute(
        Proposal $proposal,
        User $user,
        string $title,
        string $terms,
        ?string $summary = null,
        ?string $notes = null,
        ?string $versionNote = null,
    ): ProposalVersion {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('participate', $proposal);

        $title = Str::squish($title);
        abort_if($title === '' || mb_strlen($title) > 255, 422, 'Proposal terms title is invalid.');

        $terms = trim($terms);
        abort_if($terms === '' || mb_strlen($terms) > 50000, 422, 'Proposal terms are required and may not exceed 50000 characters.');

        $versionNote = trim((string) $versionNote);
        abort_if(mb_strlen($versionNote) > 1000, 422, 'Proposal version note may not exceed 1000 characters.');

        return DB::transaction(function () use (
            $proposal,
            $current,
            $title,
            $terms,
            $summary,
            $notes,
            $versionNote,
        ): ProposalVersion {
            $locked = Proposal::query()
                ->with('contextBinding.context')
                ->lockForUpdate()
                ->findOrFail($proposal->id);

            abort_unless($locked->status === ProposalStatus::Negotiating, 422, 'Only a negotiating Proposal can receive a new version.');

            $party = ProposalParty::query()
                ->where('proposal_id', $locked->id)
                ->where('actor_id', $current->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            $context = $locked->contextBinding?->context;
            abort_unless($context !== null, 500, 'Proposal Negotiation Context is missing.');

            $revision = $this->terms->execute(
                $context,
                $current,
                $title,
                $terms,
                $summary,
                $notes,
            );

            abort_unless(
                (int) $revision->content->context_id === (int) $context->id,
                422,
                'Proposal terms must belong to this Negotiation Context.',
            );

            $nextVersion = ((int) $locked->versions()->max('version')) + 1;

            $version = ProposalVersion::query()->create([
                'proposal_id' => $locked->id,
                'version' => $nextVersion,
                'terms_content_revision_id' => $revision->id,
                'proposed_by_actor_id' => $current->actor->id,
                'note' => $versionNote !== '' ? $versionNote : null,
                'proposed_at' => now(),
            ]);

            ProposalDecision::query()->create([
                'proposal_version_id' => $version->id,
                'proposal_party_id' => $party->id,
                'decision' => ProposalDecisionKind::Accepted,
                'note' => null,
                'decided_by_user_id' => $current->id,
                'decided_at' => now(),
            ]);

            ProposalEvent::query()->create([
                'proposal_id' => $locked->id,
                'proposal_version_id' => $version->id,
                'actor_id' => $current->actor->id,
                'event_type' => ProposalEventType::VersionProposed,
                'payload' => [
                    'version' => $nextVersion,
                    'terms_content_revision_uuid' => $revision->uuid,
                ],
            ]);

            return $version->fresh([
                'proposal',
                'termsRevision.content',
                'proposedBy.user',
                'decisions.party.actor.user',
            ]);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
