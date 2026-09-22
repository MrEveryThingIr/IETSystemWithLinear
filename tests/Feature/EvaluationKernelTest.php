<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Interactions\ActivateInteractionDefinitionVersion;
use App\Actions\Interactions\FinalizeEvaluation;
use App\Actions\Interactions\SaveSubmissionResponse;
use App\Actions\Interactions\StartEvaluation;
use App\Actions\Interactions\StartSubmission;
use App\Actions\Interactions\SubmitSubmission;
use App\Actions\Interactions\UpdateEvaluation;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\Submission;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EvaluationKernelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admission_evaluation_is_private_while_draft_and_visible_to_candidate_when_finalized(): void
    {
        [$reviewer, $candidate, $group, $admission, $submission] = $this->submittedAdmission();

        $evaluation = app(StartEvaluation::class)->execute($submission, $reviewer->user);

        $this->assertTrue(Gate::forUser($reviewer->user)->allows('view', $evaluation));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('view', $evaluation));

        $evaluation = app(UpdateEvaluation::class)->execute(
            $evaluation,
            $reviewer->user,
            'Strong evidence.',
            82,
            [
                ['key' => 'clarity', 'score' => 8, 'feedback' => 'Clear.'],
                ['key' => 'evidence', 'score' => 18.5, 'feedback' => 'Well supported.'],
            ],
        );
        $finalized = app(FinalizeEvaluation::class)->execute($evaluation, $reviewer->user);

        $this->assertSame(Evaluation::STATUS_FINALIZED, $finalized->status);
        $this->assertSame('82.0000', $finalized->score);
        $this->assertSame([
            ['key' => 'clarity', 'score' => '8', 'feedback' => 'Clear.'],
            ['key' => 'evidence', 'score' => '18.5', 'feedback' => 'Well supported.'],
        ], $finalized->criterion_results);
        $this->assertTrue(Gate::forUser($candidate->user)->allows('view', $finalized));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $finalized->evidence_hash);
        $this->assertSame(
            $finalized->evidence_hash,
            hash('sha256', (string) $finalized->canonical_evidence),
        );
        $this->assertSame('under_review', $admission->fresh()->status);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }

    public function test_finalized_evaluation_is_immutable_and_finalize_is_idempotent_for_evaluator(): void
    {
        [$reviewer, , , , $submission] = $this->submittedAdmission();
        $evaluation = app(StartEvaluation::class)->execute($submission, $reviewer->user);
        $evaluation = app(UpdateEvaluation::class)->execute(
            $evaluation,
            $reviewer->user,
            'Final feedback.',
            75,
        );
        $finalized = app(FinalizeEvaluation::class)->execute($evaluation, $reviewer->user);
        $again = app(FinalizeEvaluation::class)->execute($finalized, $reviewer->user);

        $this->assertSame($finalized->id, $again->id);
        $this->assertSame($finalized->evidence_hash, $again->evidence_hash);

        $this->expectException(LogicException::class);
        $finalized->update(['feedback' => 'Changed']);
    }

    public function test_rubric_and_overall_scores_must_remain_inside_immutable_definition_contract(): void
    {
        [$reviewer, , , , $submission] = $this->submittedAdmission();
        $evaluation = app(StartEvaluation::class)->execute($submission, $reviewer->user);

        try {
            app(UpdateEvaluation::class)->execute(
                $evaluation,
                $reviewer->user,
                score: 101,
                criterionResults: [['key' => 'clarity', 'score' => 9]],
            );
            $this->fail('Out-of-range overall score was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('score', $exception->errors());
        }

        try {
            app(UpdateEvaluation::class)->execute(
                $evaluation,
                $reviewer->user,
                score: 90,
                criterionResults: [['key' => 'clarity', 'score' => 11]],
            );
            $this->fail('Out-of-range rubric score was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('criteria.clarity.score', $exception->errors());
        }

        $evaluation->refresh();
        $this->assertNull($evaluation->score);
        $this->assertSame([], $evaluation->criterion_results);
        $this->assertNull($evaluation->feedback);
    }

    public function test_evaluation_mode_none_cannot_start_evaluation(): void
    {
        [$reviewer, $candidate, $group, $admission] = $this->admissionFixture();
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        $version = $this->activeInteraction(
            $context->id,
            $reviewer,
            ['mode' => 'none', 'score_max' => null, 'criteria' => []],
        );
        $submission = $this->submit($version, $candidate);

        try {
            app(StartEvaluation::class)->execute($submission, $reviewer->user);
            $this->fail('An evaluation was started for mode none.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('evaluations', 0);
        $this->assertSame('under_review', $admission->fresh()->status);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }

    public function test_submitter_and_outsider_cannot_evaluate_submission(): void
    {
        [$reviewer, $candidate, , , $submission] = $this->submittedAdmission();
        $outsider = Actor::factory()->create();

        $this->assertTrue(Gate::forUser($reviewer->user)->allows('evaluate', $submission));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('evaluate', $submission));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('evaluate', $submission));
    }

    /** @return array{Actor, Actor, Group, Admission, Submission} */
    private function submittedAdmission(): array
    {
        [$reviewer, $candidate, $group, $admission] = $this->admissionFixture();
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        $version = $this->activeInteraction($context->id, $reviewer);

        return [$reviewer, $candidate, $group, $admission, $this->submit($version, $candidate)];
    }

    /** @return array{Actor, Actor, Group, Admission} */
    private function admissionFixture(): array
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Evaluation group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);

        return [$reviewer, $candidate, $group, $admission];
    }

    /** @param array<string, mixed> $evaluationConfig */
    private function activeInteraction(
        int $contextId,
        Actor $reviewer,
        array $evaluationConfig = [
            'mode' => 'manual',
            'score_max' => 100,
            'criteria' => [
                ['key' => 'clarity', 'label' => 'Clarity', 'score_max' => 10],
                ['key' => 'evidence', 'label' => 'Evidence', 'score_max' => 20],
            ],
        ],
    ): InteractionDefinitionVersion {
        $definition = InteractionDefinition::factory()->create([
            'context_id' => $contextId,
            'created_by_actor_id' => $reviewer->id,
            'name' => 'Evaluated application',
        ]);
        $version = InteractionDefinitionVersion::factory()->create([
            'interaction_definition_id' => $definition->id,
            'created_by_actor_id' => $reviewer->id,
            'purpose_key' => 'application',
            'title' => 'Evaluated application',
            'items' => [[
                'key' => 'answer',
                'label' => 'Answer',
                'type' => 'long_text',
                'required' => true,
            ]],
            'evaluation_config' => $evaluationConfig,
        ]);

        app(ActivateInteractionDefinitionVersion::class)->execute($definition, $version, $reviewer->user);

        return $version->refresh();
    }

    private function submit(InteractionDefinitionVersion $version, Actor $candidate): Submission
    {
        $submission = app(StartSubmission::class)->execute($version, $candidate->user);
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $candidate->user,
            'answer',
            'Candidate response.',
        );

        return app(SubmitSubmission::class)->execute($submission, $candidate->user);
    }
}
