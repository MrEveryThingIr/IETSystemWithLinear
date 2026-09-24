<?php

namespace App\Policies;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Context;
use App\Models\GroupSpace;
use App\Models\ReferenceContext;
use App\Models\Relationship;
use App\Models\User;

class ContextPolicy
{
    public function __construct(
        private readonly GroupSpacePolicy $spaces,
        private readonly GroupPolicy $groups,
        private readonly RelationshipPolicy $relationships,
    ) {}

    public function view(User $user, Context $context): bool
    {
        $current = $this->currentUser($user);

        if (! $current instanceof User) {
            return false;
        }

        return match ($context->kind) {
            ContextKind::Personal => $this->ownsPersonalContext($current, $context),
            ContextKind::GroupSpace => ($space = $this->groupSpace($context)) instanceof GroupSpace
                && $this->spaces->view($current, $space),
            ContextKind::Admission => ($admission = $this->admission($context)) instanceof Admission
                && $this->canViewAdmissionContext($current, $admission),
            ContextKind::Relationship => ($relationship = $this->relationship($context)) instanceof Relationship
                && $this->relationships->view($current, $relationship),
            ContextKind::Reference => $this->reference($context) instanceof ReferenceContext,
        };
    }

    public function createContent(User $user, Context $context): bool
    {
        $current = $this->currentUser($user);

        if (! $current instanceof User) {
            return false;
        }

        return match ($context->kind) {
            ContextKind::Personal => $this->ownsPersonalContext($current, $context),
            ContextKind::GroupSpace => ($space = $this->groupSpace($context)) instanceof GroupSpace
                && $this->spaces->post($current, $space),
            ContextKind::Admission => ($admission = $this->admission($context)) instanceof Admission
                && $this->admissionIsMutable($admission)
                && $this->canViewAdmissionContext($current, $admission),
            ContextKind::Relationship => ($relationship = $this->relationship($context)) instanceof Relationship
                && $this->relationships->participate($current, $relationship),
            ContextKind::Reference => $this->isReferenceManager($current, $context),
        };
    }

    public function interactContent(User $user, Context $context): bool
    {
        if ($context->kind === ContextKind::Reference) {
            return $this->view($user, $context);
        }

        return $this->createContent($user, $context);
    }

    public function reviewContent(User $user, Context $context): bool
    {
        $current = $this->currentUser($user);

        if (! $current instanceof User) {
            return false;
        }

        return match ($context->kind) {
            ContextKind::Personal => $this->ownsPersonalContext($current, $context),
            ContextKind::GroupSpace => ($space = $this->groupSpace($context)) instanceof GroupSpace
                && $this->spaces->manage($current, $space),
            ContextKind::Admission => ($admission = $this->admission($context)) instanceof Admission
                && $this->isAdmissionReviewer($current, $admission),
            ContextKind::Relationship => ($relationship = $this->relationship($context)) instanceof Relationship
                && $this->relationships->manage($current, $relationship),
            ContextKind::Reference => $this->isReferenceManager($current, $context),
        };
    }

    public function manageContent(User $user, Context $context): bool
    {
        $current = $this->currentUser($user);

        if (! $current instanceof User) {
            return false;
        }

        return match ($context->kind) {
            ContextKind::Personal => $this->ownsPersonalContext($current, $context),
            ContextKind::GroupSpace => ($space = $this->groupSpace($context)) instanceof GroupSpace
                && $this->spaces->manage($current, $space),
            ContextKind::Admission => ($admission = $this->admission($context)) instanceof Admission
                && $this->admissionIsMutable($admission)
                && $this->isAdmissionReviewer($current, $admission),
            ContextKind::Relationship => ($relationship = $this->relationship($context)) instanceof Relationship
                && $this->relationships->manage($current, $relationship),
            ContextKind::Reference => $this->isReferenceManager($current, $context),
        };
    }

    public function manageDefinitions(User $user, Context $context): bool
    {
        return $this->manageContent($user, $context);
    }

    public function submitInteractions(User $user, Context $context): bool
    {
        return $this->createContent($user, $context);
    }

    public function reviewInteractions(User $user, Context $context): bool
    {
        return $this->reviewContent($user, $context);
    }

    public function manageInteractions(User $user, Context $context): bool
    {
        return $this->manageContent($user, $context);
    }

    private function currentUser(User $user): ?User
    {
        $current = User::query()->with('actor')->find($user->id);

        if (
            ! $current instanceof User
            || $current->status !== 'active'
            || $current->email_verified_at === null
            || ! $current->actor instanceof Actor
            || $current->actor->status !== 'active'
        ) {
            return null;
        }

        return $current;
    }

    private function ownsPersonalContext(User $user, Context $context): bool
    {
        $context->loadMissing('personalBinding');

        return $context->personalBinding !== null
            && (int) $context->personalBinding->actor_id === (int) $user->actor?->id;
    }

    private function groupSpace(Context $context): ?GroupSpace
    {
        $context->loadMissing('groupSpaceBinding.groupSpace');

        return $context->groupSpaceBinding?->groupSpace;
    }

    private function admission(Context $context): ?Admission
    {
        $context->loadMissing('admissionBinding.admission.group');

        return $context->admissionBinding?->admission;
    }

    private function relationship(Context $context): ?Relationship
    {
        $context->loadMissing('relationshipBinding.relationship');

        return $context->relationshipBinding?->relationship;
    }

    private function reference(Context $context): ?ReferenceContext
    {
        $context->loadMissing('referenceBinding');

        return $context->referenceBinding;
    }

    private function isReferenceManager(User $user, Context $context): bool
    {
        $reference = $this->reference($context);

        return $reference instanceof ReferenceContext
            && (int) $reference->managed_by_actor_id === (int) $user->actor?->id;
    }

    private function canViewAdmissionContext(User $user, Admission $admission): bool
    {
        return (int) $user->actor?->id === (int) $admission->candidate_actor_id
            || $this->isAdmissionReviewer($user, $admission);
    }

    private function isAdmissionReviewer(User $user, Admission $admission): bool
    {
        $admission->loadMissing('group');

        return $this->groups->manageAdmissions($user, $admission->group);
    }

    private function admissionIsMutable(Admission $admission): bool
    {
        return ! in_array($admission->status, ['finalized', 'rejected', 'cancelled'], true);
    }
}
