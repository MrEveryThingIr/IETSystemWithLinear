<?php

namespace App\Policies;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Context;
use App\Models\GroupSpace;
use App\Models\User;

class ContextPolicy
{
    public function __construct(
        private readonly GroupSpacePolicy $spaces,
        private readonly GroupPolicy $groups,
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
        };
    }

    public function interactContent(User $user, Context $context): bool
    {
        return $this->createContent($user, $context);
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
        };
    }

    public function manageDefinitions(User $user, Context $context): bool
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
