<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function __construct(private readonly ContextPolicy $contexts) {}

    public function view(User $user, Submission $submission): bool
    {
        $submission->loadMissing('context');

        if (! $this->contexts->view($user, $submission->context)) {
            return false;
        }

        $actor = $this->actor($user);
        if (! $actor instanceof Actor) {
            return false;
        }

        if ((int) $submission->submitted_by_actor_id === (int) $actor->id) {
            return true;
        }

        return $submission->status !== Submission::STATUS_DRAFT
            && $this->contexts->reviewInteractions($user, $submission->context);
    }

    public function update(User $user, Submission $submission): bool
    {
        $submission->loadMissing('context');

        return $submission->status === Submission::STATUS_DRAFT
            && $this->owns($user, $submission)
            && $this->contexts->submitInteractions($user, $submission->context);
    }

    public function submit(User $user, Submission $submission): bool
    {
        return $this->update($user, $submission);
    }

    public function withdraw(User $user, Submission $submission): bool
    {
        $submission->loadMissing('definitionVersion', 'context');

        return $submission->status === Submission::STATUS_SUBMITTED
            && $this->owns($user, $submission)
            && (bool) ($submission->definitionVersion->settings['allow_withdrawal'] ?? true)
            && $this->contexts->submitInteractions($user, $submission->context);
    }

    public function evaluate(User $user, Submission $submission): bool
    {
        $submission->loadMissing('context');
        $actor = $this->actor($user);

        return $submission->status === Submission::STATUS_SUBMITTED
            && $actor instanceof Actor
            && (int) $submission->submitted_by_actor_id !== (int) $actor->id
            && $this->contexts->reviewInteractions($user, $submission->context);
    }

    private function owns(User $user, Submission $submission): bool
    {
        $actor = $this->actor($user);

        return $actor instanceof Actor
            && (int) $submission->submitted_by_actor_id === (int) $actor->id;
    }

    private function actor(User $user): ?Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        return $current?->actor;
    }
}
