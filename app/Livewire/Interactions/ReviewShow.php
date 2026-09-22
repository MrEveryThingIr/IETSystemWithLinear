<?php

namespace App\Livewire\Interactions;

use App\Actions\Interactions\FinalizeEvaluation;
use App\Actions\Interactions\StartEvaluation;
use App\Actions\Interactions\UpdateEvaluation;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Review submission')]
class ReviewShow extends Component
{
    public Context $context;
    public Submission $submission;
    public ?string $evaluationUuid = null;
    public string $feedback = '';
    public string $score = '';

    /** @var array<string, string> */
    public array $criterionScores = [];

    /** @var array<string, string> */
    public array $criterionFeedback = [];

    public function mount(Context $context, Submission $submission): void
    {
        abort_unless((int) $submission->context_id === (int) $context->id, 404);

        Gate::forUser($this->user())->authorize('reviewInteractions', $context);
        Gate::forUser($this->user())->authorize('view', $submission);

        $this->context = $context;
        $this->submission = $submission;
        $this->hydrateEvaluation();
    }

    public function startEvaluation(StartEvaluation $start): void
    {
        $evaluation = $start->execute($this->submission, $this->user());
        $this->evaluationUuid = $evaluation->uuid;
        $this->hydrateEvaluation();
    }

    public function saveEvaluation(UpdateEvaluation $update): void
    {
        $evaluation = $this->evaluation();
        abort_unless($evaluation instanceof Evaluation, 404);

        $evaluation = $update->execute(
            $evaluation,
            $this->user(),
            $this->feedback,
            $this->score === '' ? null : $this->score,
            $this->criterionResults(),
        );

        $this->evaluationUuid = $evaluation->uuid;
        $this->hydrateEvaluation();
        session()->flash('evaluation-status', __('structured_interactions.evaluation_saved'));
    }

    public function finalizeEvaluation(
        UpdateEvaluation $update,
        FinalizeEvaluation $finalize,
    ): void {
        $evaluation = $this->evaluation();
        abort_unless($evaluation instanceof Evaluation, 404);

        if ($evaluation->status === Evaluation::STATUS_DRAFT) {
            $evaluation = $update->execute(
                $evaluation,
                $this->user(),
                $this->feedback,
                $this->score === '' ? null : $this->score,
                $this->criterionResults(),
            );
        }

        $evaluation = $finalize->execute($evaluation, $this->user());
        $this->evaluationUuid = $evaluation->uuid;
        $this->hydrateEvaluation();
        session()->flash('evaluation-status', __('structured_interactions.evaluation_finalized'));
    }

    public function render(): View
    {
        $submission = Submission::query()
            ->with([
                'context',
                'submitter.user',
                'definitionVersion.definition',
                'responses.asset',
                'responses.contentEvidenceReference',
                'evaluations.evaluator.user',
            ])
            ->findOrFail($this->submission->id);

        abort_unless((int) $submission->context_id === (int) $this->context->id, 404);
        Gate::forUser($this->user())->authorize('reviewInteractions', $submission->context);
        Gate::forUser($this->user())->authorize('view', $submission);
        $this->submission = $submission;

        $evaluation = $this->evaluation();
        $canEvaluate = Gate::forUser($this->user())->allows('evaluate', $submission)
            && ($submission->definitionVersion->evaluation_config['mode'] ?? 'manual') === 'manual';
        $canUpdateEvaluation = $evaluation instanceof Evaluation
            && Gate::forUser($this->user())->allows('update', $evaluation);
        $canFinalizeEvaluation = $evaluation instanceof Evaluation
            && Gate::forUser($this->user())->allows('finalize', $evaluation);

        return view('livewire.interactions.review-show', compact(
            'evaluation',
            'canEvaluate',
            'canUpdateEvaluation',
            'canFinalizeEvaluation',
        ));
    }

    private function hydrateEvaluation(): void
    {
        $evaluation = $this->evaluation();

        if (! $evaluation instanceof Evaluation) {
            $this->reset('feedback', 'score', 'criterionScores', 'criterionFeedback');

            return;
        }

        $this->feedback = $evaluation->feedback ?? '';
        $this->score = $evaluation->score !== null ? (string) $evaluation->score : '';
        $this->criterionScores = [];
        $this->criterionFeedback = [];

        foreach ($evaluation->criterion_results ?? [] as $result) {
            if (! is_array($result) || ! is_string($result['key'] ?? null)) {
                continue;
            }

            $key = $result['key'];
            $this->criterionScores[$key] = isset($result['score']) ? (string) $result['score'] : '';
            $this->criterionFeedback[$key] = isset($result['feedback']) ? (string) $result['feedback'] : '';
        }
    }

    /** @return list<array{key: string, score: string, feedback: ?string}> */
    private function criterionResults(): array
    {
        $criteria = $this->submission->definitionVersion->evaluation_config['criteria'] ?? [];
        $results = [];

        foreach ($criteria as $criterion) {
            if (! is_array($criterion) || ! is_string($criterion['key'] ?? null)) {
                continue;
            }

            $key = $criterion['key'];
            $score = trim($this->criterionScores[$key] ?? '');
            $feedback = trim($this->criterionFeedback[$key] ?? '');

            if ($score === '' && $feedback === '') {
                continue;
            }

            $results[] = [
                'key' => $key,
                'score' => $score,
                'feedback' => $feedback === '' ? null : $feedback,
            ];
        }

        return $results;
    }

    private function evaluation(): ?Evaluation
    {
        $actor = $this->actor();

        if ($this->evaluationUuid !== null) {
            return Evaluation::query()
                ->where('uuid', $this->evaluationUuid)
                ->where('submission_id', $this->submission->id)
                ->where('evaluator_actor_id', $actor->id)
                ->first();
        }

        $evaluation = Evaluation::query()
            ->where('submission_id', $this->submission->id)
            ->where('evaluator_actor_id', $actor->id)
            ->first();

        $this->evaluationUuid = $evaluation?->uuid;

        return $evaluation;
    }

    private function actor(): Actor
    {
        $current = User::query()->with('actor')->find($this->user()->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
