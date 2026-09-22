<?php

namespace App\Actions\Interactions;

use App\Models\Actor;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StartEvaluation
{
    public function execute(Submission $submission, User $user): Evaluation
    {
        return DB::transaction(function () use ($submission, $user): Evaluation {
            $current = Submission::query()
                ->with(['context', 'definitionVersion'])
                ->lockForUpdate()
                ->findOrFail($submission->id);

            Gate::forUser($user)->authorize('evaluate', $current);
            abort_unless($current->status === Submission::STATUS_SUBMITTED, 409, 'Only submitted work can be evaluated.');
            abort_unless(
                ($current->definitionVersion->evaluation_config['mode'] ?? 'manual') === 'manual',
                422,
                'This interaction does not accept evaluations.',
            );

            $actor = $this->actor($user);
            Actor::query()->lockForUpdate()->findOrFail($actor->id);

            $existing = Evaluation::query()
                ->where('submission_id', $current->id)
                ->where('evaluator_actor_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Evaluation) {
                return $existing;
            }

            return Evaluation::query()->create([
                'submission_id' => $current->id,
                'evaluator_actor_id' => $actor->id,
                'status' => Evaluation::STATUS_DRAFT,
                'score' => null,
                'criterion_results' => [],
                'feedback' => null,
            ]);
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
