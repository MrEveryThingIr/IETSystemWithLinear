<?php

namespace App\Actions\Interactions;

use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\User;
use App\Support\EvaluationEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FinalizeEvaluation
{
    public function __construct(private readonly EvaluationEvidence $evidence) {}

    public function execute(Evaluation $evaluation, User $user): Evaluation
    {
        return DB::transaction(function () use ($evaluation, $user): Evaluation {
            $current = Evaluation::query()
                ->with([
                    'submission.context',
                    'submission.definitionVersion.definition',
                ])
                ->lockForUpdate()
                ->findOrFail($evaluation->id);

            Gate::forUser($user)->authorize('finalize', $current);

            if ($current->status === Evaluation::STATUS_FINALIZED) {
                return $current;
            }

            abort_unless(
                $current->submission->status === Submission::STATUS_SUBMITTED,
                409,
                'Only an active submitted attempt can receive a finalized Evaluation.',
            );

            if ($current->feedback === null
                && $current->score === null
                && ($current->criterion_results ?? []) === []) {
                throw ValidationException::withMessages([
                    'evaluation' => 'Add feedback, a score, or criterion results before finalizing.',
                ]);
            }

            $sealed = $this->evidence->seal($current);
            $current->finalizeEvidence(
                $sealed['schema_version'],
                $sealed['hash'],
                $sealed['canonical'],
            );

            return $current->refresh();
        }, 3);
    }
}
