<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\User;

class EvaluationPolicy
{
    public function __construct(private readonly ContextPolicy $contexts) {}

    public function view(User $user, Evaluation $evaluation): bool
    {
        $evaluation->loadMissing('submission.context');
        $submission = $evaluation->submission;

        if (! $this->contexts->view($user, $submission->context)) {
            return false;
        }

        $actor = $this->actor($user);
        if (! $actor instanceof Actor) {
            return false;
        }

        if ((int) $evaluation->evaluator_actor_id === (int) $actor->id) {
            return true;
        }

        if ($evaluation->status !== Evaluation::STATUS_FINALIZED) {
            return false;
        }

        return (int) $submission->submitted_by_actor_id === (int) $actor->id
            || $this->contexts->reviewInteractions($user, $submission->context);
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        $evaluation->loadMissing('submission.context');

        return $evaluation->status === Evaluation::STATUS_DRAFT
            && $evaluation->submission->status === Submission::STATUS_SUBMITTED
            && $this->owns($user, $evaluation)
            && $this->contexts->reviewInteractions($user, $evaluation->submission->context);
    }

    public function finalize(User $user, Evaluation $evaluation): bool
    {
        if ($evaluation->status === Evaluation::STATUS_FINALIZED) {
            return $this->owns($user, $evaluation);
        }

        return $this->update($user, $evaluation);
    }

    private function owns(User $user, Evaluation $evaluation): bool
    {
        $actor = $this->actor($user);

        return $actor instanceof Actor
            && (int) $evaluation->evaluator_actor_id === (int) $actor->id;
    }

    private function actor(User $user): ?Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        return $current?->actor;
    }
}
