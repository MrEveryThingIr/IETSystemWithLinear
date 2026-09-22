<?php

namespace App\Actions\Interactions;

use App\Models\Evaluation;
use App\Models\User;
use App\Support\EvaluationDataNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateEvaluation
{
    public function __construct(private readonly EvaluationDataNormalizer $normalizer) {}

    /**
     * @param  list<mixed>  $criterionResults
     */
    public function execute(
        Evaluation $evaluation,
        User $user,
        ?string $feedback = null,
        int|float|string|null $score = null,
        array $criterionResults = [],
    ): Evaluation {
        return DB::transaction(function () use (
            $evaluation,
            $user,
            $feedback,
            $score,
            $criterionResults,
        ): Evaluation {
            $current = Evaluation::query()
                ->with('submission.definitionVersion')
                ->lockForUpdate()
                ->findOrFail($evaluation->id);

            Gate::forUser($user)->authorize('update', $current);

            $normalized = $this->normalizer->normalize(
                $current->submission->definitionVersion,
                $feedback,
                $score,
                $criterionResults,
            );

            $current->applyDraft(
                $normalized['feedback'],
                $normalized['score'],
                $normalized['criterion_results'],
            );

            return $current->refresh();
        }, 3);
    }
}
