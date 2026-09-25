<?php

namespace App\Support;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ContextNotificationRecipients
{
    /** @return Collection<int, User> */
    public function viewers(Context $context, ?int $excludeActorId = null): Collection
    {
        $context = Context::query()->findOrFail($context->id);

        $actors = match ($context->kind) {
            ContextKind::Personal => $this->personalActors($context),
            ContextKind::GroupSpace => $this->groupSpaceActors($context),
            ContextKind::Admission => $this->admissionActors($context),
            ContextKind::Relationship => $this->relationshipActors($context),
            ContextKind::Negotiation => $this->proposalActors($context),
            ContextKind::Contract => $this->contractActors($context),
            ContextKind::Reference => $this->referenceActors($context),
        };

        return $actors
            ->reject(fn (Actor $actor): bool => $excludeActorId !== null && (int) $actor->id === $excludeActorId)
            ->map(fn (Actor $actor): ?User => $actor->user)
            ->filter(fn ($user): bool => $user instanceof User
                && $user->status === 'active'
                && $user->email_verified_at !== null
                && Gate::forUser($user)->allows('view', $context))
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, Actor> */
    private function personalActors(Context $context): Collection
    {
        $context->loadMissing('personalBinding.actor.user');

        return collect([$context->personalBinding?->actor])->filter();
    }

    /** @return Collection<int, Actor> */
    private function groupSpaceActors(Context $context): Collection
    {
        $context->loadMissing('groupSpaceBinding.groupSpace.group');

        $space = $context->groupSpaceBinding?->groupSpace;
        if ($space === null) {
            return collect();
        }

        $membershipActors = $space->group->memberships()
            ->where('status', 'active')
            ->with('actor.user')
            ->get()
            ->pluck('actor');

        $participantActors = $space->participants()
            ->with('actor.user')
            ->get()
            ->pluck('actor');

        return $membershipActors->merge($participantActors)->unique('id')->values();
    }

    /** @return Collection<int, Actor> */
    private function admissionActors(Context $context): Collection
    {
        $context->loadMissing('admissionBinding.admission.candidate.user', 'admissionBinding.admission.group');

        $admission = $context->admissionBinding?->admission;
        if ($admission === null) {
            return collect();
        }

        $members = $admission->group->memberships()
            ->where('status', 'active')
            ->with('actor.user')
            ->get()
            ->pluck('actor');

        return collect([$admission->candidate])->merge($members)->unique('id')->values();
    }

    /** @return Collection<int, Actor> */
    private function relationshipActors(Context $context): Collection
    {
        $context->loadMissing('relationshipBinding.relationship.participants.actor.user');

        return $context->relationshipBinding?->relationship?->participants?->pluck('actor') ?? collect();
    }

    /** @return Collection<int, Actor> */
    private function proposalActors(Context $context): Collection
    {
        $context->loadMissing('proposalBinding.proposal.parties.actor.user');

        return $context->proposalBinding?->proposal?->parties?->pluck('actor') ?? collect();
    }

    /** @return Collection<int, Actor> */
    private function contractActors(Context $context): Collection
    {
        $context->loadMissing('contractBinding.contract');

        $contract = $context->contractBinding?->contract;
        if ($contract === null) {
            return collect();
        }

        $version = $contract->versions()
            ->with('parties.actor.user')
            ->latest('version')
            ->first();

        return $version?->parties->pluck('actor') ?? collect();
    }

    /** @return Collection<int, Actor> */
    private function referenceActors(Context $context): Collection
    {
        $context->loadMissing('referenceBinding.manager.user');

        return collect([$context->referenceBinding?->manager])->filter();
    }
}
