<?php

namespace Tests\Feature;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Interactions\ActivateInteractionDefinitionVersion;
use App\Actions\Interactions\FinalizeEvaluation;
use App\Actions\Interactions\SaveSubmissionResponse;
use App\Actions\Interactions\StartEvaluation;
use App\Actions\Interactions\StartSubmission;
use App\Actions\Interactions\SubmitSubmission;
use App\Actions\Interactions\UpdateEvaluation;
use App\Models\ContentEvidenceReference;
use App\Models\Group;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\SpaceContent;
use App\Models\User;
use Database\Seeders\Phase7InteractionDemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7ClosureProofTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_builds_both_phase_7_proof_cases(): void
    {
        $this->seed(Phase7InteractionDemoSeeder::class);
        $this->seed(Phase7InteractionDemoSeeder::class);

        $school = Group::query()->where('name', 'Phase 7 School Interaction Lab')->sole();
        $hiring = Group::query()->where('name', 'Phase 7 Employment Application Lab')->sole();
        $learner = User::query()->where('email', 'phase7.learner@example.com')->sole();
        $candidate = User::query()->where('email', 'phase7.candidate@example.com')->sole();

        $exam = $this->contentByTitle('Phase 7 — Laravel Fundamentals Exam');
        $application = $this->contentByTitle('Phase 7 — Backend Developer Application');

        $this->assertSame('published', $exam->status);
        $this->assertSame('published', $application->status);
        $this->assertSame(1, InteractionDefinition::query()->where('name', 'School exam')->count());
        $this->assertSame(1, InteractionDefinition::query()->where('name', 'Employment application')->count());
        $this->assertSame(1, $school->memberships()->where('actor_id', $learner->actor()->firstOrFail()->id)->count());
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $hiring->id,
            'actor_id' => $candidate->actor()->firstOrFail()->id,
        ]);
        $this->assertSame('fa', $candidate->locale);
        $this->assertSame(1, ContentEvidenceReference::query()->count());
    }

    public function test_school_exam_submission_and_evaluation_survive_newer_content_and_interaction_versions(): void
    {
        $this->seed(Phase7InteractionDemoSeeder::class);

        $owner = User::query()->where('email', 'test@example.com')->sole();
        $learner = User::query()->where('email', 'phase7.learner@example.com')->sole();
        $exam = $this->contentByTitle('Phase 7 — Laravel Fundamentals Exam');
        $interaction = InteractionDefinition::query()->where('name', 'School exam')->sole();
        $versionOne = $interaction->activeVersionRecord();
        $this->assertInstanceOf(InteractionDefinitionVersion::class, $versionOne);

        $submission = app(StartSubmission::class)->execute($versionOne, $learner);
        app(SaveSubmissionResponse::class)->execute($submission, $learner, 'http_method', 'POST');
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $learner,
            'explanation',
            'Authorization must be enforced on the server because clients cannot be trusted to enforce access rules.',
        );
        app(SaveSubmissionResponse::class)->execute($submission, $learner, 'confidence', 92);
        $submission = app(SubmitSubmission::class)->execute($submission, $learner);
        $submissionHash = $submission->evidence_hash;
        $submissionEvidence = $submission->canonical_evidence;

        $evaluation = app(StartEvaluation::class)->execute($submission, $owner);
        $evaluation = app(UpdateEvaluation::class)->execute(
            $evaluation,
            $owner,
            'Correct answer with a clear security explanation.',
            94,
            [
                ['key' => 'correctness', 'score' => 68, 'feedback' => 'Correct.'],
                ['key' => 'reasoning', 'score' => 26, 'feedback' => 'Good explanation.'],
            ],
        );
        $evaluation = app(FinalizeEvaluation::class)->execute($evaluation, $owner);

        $oldRevisionId = $submission->space_content_revision_id;
        $exam = app(ReviseSpaceContent::class)->execute(
            $exam,
            $owner,
            'Phase 7 — Laravel Fundamentals Exam · Edition 2',
            $exam->activeRevisionRecord()?->payload ?? [],
        );
        $exam = app(PublishSpaceContent::class)->execute($exam, $owner);
        $newRevision = $exam->activeRevisionRecord();
        $this->assertNotNull($newRevision);
        $this->assertNotSame($oldRevisionId, $newRevision->id);

        $versionTwo = $interaction->versions()->create([
            'version' => 2,
            'purpose_key' => 'exam',
            'title' => 'Laravel Fundamentals Exam · Edition 2',
            'instructions' => 'A later edition that must not reinterpret the first submitted attempt.',
            'items' => [
                [
                    'key' => 'http_method',
                    'label' => 'Which HTTP method is normally used to create a resource?',
                    'type' => 'single_choice',
                    'required' => true,
                    'options' => ['GET', 'POST', 'DELETE'],
                ],
                [
                    'key' => 'explanation',
                    'label' => 'Explain why authorization belongs on the server.',
                    'type' => 'long_text',
                    'required' => true,
                    'constraints' => ['min_length' => 20, 'max_length' => 1500],
                ],
                [
                    'key' => 'reflection',
                    'label' => 'Optional reflection',
                    'type' => 'long_text',
                    'required' => false,
                ],
            ],
            'settings' => ['allow_withdrawal' => false, 'max_attempts' => 1],
            'evaluation_config' => [
                'mode' => 'manual',
                'score_max' => 100,
                'criteria' => [
                    ['key' => 'correctness', 'label' => 'Correctness', 'score_max' => 70],
                    ['key' => 'reasoning', 'label' => 'Reasoning', 'score_max' => 30],
                ],
            ],
            'space_content_revision_id' => $newRevision->id,
            'created_by_actor_id' => $owner->actor()->firstOrFail()->id,
        ]);

        app(ActivateInteractionDefinitionVersion::class)->execute($interaction, $versionTwo, $owner);

        $submission->refresh();
        $evaluation->refresh();
        $interaction->refresh();

        $this->assertSame($versionOne->id, $submission->interaction_definition_version_id);
        $this->assertSame($oldRevisionId, $submission->space_content_revision_id);
        $this->assertSame($submissionHash, $submission->evidence_hash);
        $this->assertSame($submissionEvidence, $submission->canonical_evidence);
        $this->assertSame('finalized', $evaluation->status);
        $this->assertSame($submissionHash, $evaluation->submission->evidence_hash);
        $this->assertSame($versionTwo->id, $interaction->active_version_id);

        $newAttempt = app(StartSubmission::class)->execute($versionTwo, $learner);
        $this->assertSame($versionTwo->id, $newAttempt->interaction_definition_version_id);
        $this->assertSame($newRevision->id, $newAttempt->space_content_revision_id);
    }

    public function test_employment_application_uses_asset_and_personal_evidence_without_approving_admission_or_membership(): void
    {
        Storage::fake('local');
        $this->seed(Phase7InteractionDemoSeeder::class);

        $owner = User::query()->where('email', 'test@example.com')->sole();
        $candidate = User::query()->where('email', 'phase7.candidate@example.com')->sole();
        $candidateActor = $candidate->actor()->firstOrFail();
        $group = Group::query()->where('name', 'Phase 7 Employment Application Lab')->sole();
        $admission = $candidateActor->admissions()->where('group_id', $group->id)->sole();
        $interaction = InteractionDefinition::query()->where('name', 'Employment application')->sole();
        $version = $interaction->activeVersionRecord();
        $this->assertInstanceOf(InteractionDefinitionVersion::class, $version);
        $portfolio = ContentEvidenceReference::query()->sole();

        $submission = app(StartSubmission::class)->execute($version, $candidate);
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $candidate,
            'motivation',
            'I want to contribute reliable Laravel engineering and collaborate through explicit, auditable domain actions.',
        );
        app(SaveSubmissionResponse::class)->execute($submission, $candidate, 'experience_years', 5);
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $candidate,
            'availability',
            ['Monday', 'Wednesday', 'Saturday'],
        );

        $asset = app(CreateContextAsset::class)->execute(
            $submission->context,
            $candidate,
            UploadedFile::fake()->create('phase7-cv.pdf', 32, 'application/pdf'),
            'owned',
        );
        app(SaveSubmissionResponse::class)->execute($submission, $candidate, 'cv', asset: $asset);
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $candidate,
            'portfolio',
            contentEvidence: $portfolio,
        );

        $submission = app(SubmitSubmission::class)->execute($submission, $candidate);

        $this->assertSame('under_review', $admission->fresh()->status);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidateActor->id,
        ]);
        $this->assertStringContainsString($asset->sha256, (string) $submission->canonical_evidence);
        $this->assertStringContainsString($portfolio->uuid, (string) $submission->canonical_evidence);
        Storage::disk('local')->assertExists($asset->storage_key);

        $evaluation = app(StartEvaluation::class)->execute($submission, $owner);
        $evaluation = app(UpdateEvaluation::class)->execute(
            $evaluation,
            $owner,
            'Strong evidence. This evaluation is advisory and must not finalize Admission.',
            86,
            [
                ['key' => 'experience', 'score' => 35, 'feedback' => 'Relevant background.'],
                ['key' => 'evidence', 'score' => 26, 'feedback' => 'Useful portfolio and CV.'],
                ['key' => 'communication', 'score' => 25, 'feedback' => 'Clear response.'],
            ],
        );
        $evaluation = app(FinalizeEvaluation::class)->execute($evaluation, $owner);

        $this->assertSame('finalized', $evaluation->status);
        $this->assertNotNull($evaluation->evidence_hash);
        $this->assertSame('under_review', $admission->fresh()->status);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidateActor->id,
        ]);

        $space = $group->spaces()->orderBy('id')->firstOrFail();
        $this->actingAs($candidate)->get(route('groups.spaces.show', [$group, $space]))->assertForbidden();

        $this->actingAs($candidate)
            ->get(route('contexts.contents.show', [$submission->context, $this->contentByTitle('Phase 7 — Backend Developer Application')]))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('contexts.submissions.show', [$submission->context, $submission]))
            ->assertOk()
            ->assertSee('Strong evidence.');
    }

    private function contentByTitle(string $title): SpaceContent
    {
        return SpaceContent::query()
            ->whereHas('revisions', fn ($query) => $query->where('title', $title))
            ->firstOrFail();
    }
}
