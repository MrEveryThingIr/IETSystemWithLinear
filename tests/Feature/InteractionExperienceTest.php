<?php

namespace Tests\Feature;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Interactions\ActivateInteractionDefinitionVersion;
use App\Actions\Interactions\SaveSubmissionResponse;
use App\Actions\Interactions\StartSubmission;
use App\Actions\Interactions\SubmitSubmission;
use App\Livewire\Interactions\ReviewQueue;
use App\Livewire\Interactions\ReviewShow;
use App\Livewire\Interactions\SubmissionCard;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Context;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRevision;
use App\Models\Submission;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InteractionExperienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_content_reader_embeds_purpose_specific_submission_card(): void
    {
        $actor = Actor::factory()->create();
        [$context, $content, , $interaction] = $this->publishedInteraction($actor, 'questionnaire');

        $this->actingAs($actor->user)
            ->get(route('contexts.contents.show', [$context, $content]))
            ->assertOk()
            ->assertSee('Structured interaction')
            ->assertSee('Questionnaire')
            ->assertSee($interaction->activeVersion->title);
    }

    public function test_participant_can_start_save_resume_and_submit_from_reusable_card(): void
    {
        $actor = Actor::factory()->create();
        [, , , $interaction] = $this->publishedInteraction($actor, 'application');

        Livewire::actingAs($actor->user)
            ->test(SubmissionCard::class, ['definition' => $interaction])
            ->call('start')
            ->set('answers.statement', 'I would like to contribute.')
            ->call('saveDraft')
            ->assertSee('Draft saved.')
            ->call('submit')
            ->assertSee('Submitted');

        $submission = Submission::query()->sole();

        $this->assertSame(Submission::STATUS_SUBMITTED, $submission->status);
        $this->assertNotNull($submission->evidence_hash);
        $this->assertDatabaseHas('submission_responses', [
            'submission_id' => $submission->id,
            'item_key' => 'statement',
        ]);
    }

    public function test_reviewer_queue_hides_candidate_draft_then_exposes_submitted_attempt(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Review UX group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        [, , , $interaction] = $this->publishedInteraction($reviewer, 'application', $context);
        $version = $interaction->activeVersionRecord();
        $this->assertInstanceOf(InteractionDefinitionVersion::class, $version);

        $draft = app(StartSubmission::class)->execute($version, $candidate->user);

        Livewire::actingAs($reviewer->user)
            ->test(ReviewQueue::class, ['context' => $context])
            ->assertDontSee($version->title);

        app(SaveSubmissionResponse::class)->execute(
            $draft,
            $candidate->user,
            'statement',
            'Candidate response',
        );
        $submitted = app(SubmitSubmission::class)->execute($draft, $candidate->user);

        Livewire::actingAs($reviewer->user)
            ->test(ReviewQueue::class, ['context' => $context])
            ->assertSee($version->title)
            ->assertSee(route('contexts.submissions.show', [$context, $submitted]), false);

        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }

    public function test_reviewer_can_finalize_evaluation_without_admission_side_effects(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Evaluation UX group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        [, , , $interaction] = $this->publishedInteraction(
            $reviewer,
            'application',
            $context,
            [
                'mode' => 'manual',
                'score_max' => 100,
                'criteria' => [
                    ['key' => 'clarity', 'label' => 'Clarity', 'score_max' => 10],
                ],
            ],
        );
        $version = $interaction->activeVersionRecord();
        $this->assertInstanceOf(InteractionDefinitionVersion::class, $version);
        $submission = app(StartSubmission::class)->execute($version, $candidate->user);
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $candidate->user,
            'statement',
            'Candidate response',
        );
        $submission = app(SubmitSubmission::class)->execute($submission, $candidate->user);

        Livewire::actingAs($reviewer->user)
            ->test(ReviewShow::class, ['context' => $context, 'submission' => $submission])
            ->call('startEvaluation')
            ->set('score', '88')
            ->set('criterionScores.clarity', '8')
            ->set('criterionFeedback.clarity', 'Clear evidence.')
            ->set('feedback', 'Ready for the next human decision.')
            ->call('finalizeEvaluation')
            ->assertSee('Evaluation finalized.');

        $this->assertDatabaseHas('evaluations', [
            'submission_id' => $submission->id,
            'status' => 'finalized',
        ]);
        $this->assertSame('under_review', $admission->fresh()->status);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }

    public function test_submission_asset_download_is_available_to_submitter_and_reviewer_but_not_outsider(): void
    {
        Storage::fake('local');

        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Asset review group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        [, , , $interaction] = $this->publishedInteraction(
            $reviewer,
            'application',
            $context,
            ['mode' => 'manual', 'score_max' => null, 'criteria' => []],
            [[
                'key' => 'cv',
                'label' => 'CV',
                'type' => 'asset',
                'required' => true,
            ]],
        );
        $version = $interaction->activeVersionRecord();
        $this->assertInstanceOf(InteractionDefinitionVersion::class, $version);
        $submission = app(StartSubmission::class)->execute($version, $candidate->user);
        $asset = app(CreateContextAsset::class)->execute(
            $context,
            $candidate->user,
            UploadedFile::fake()->create('cv.pdf', 24, 'application/pdf'),
            'owned',
        );
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $candidate->user,
            'cv',
            asset: $asset,
        );
        $submission = app(SubmitSubmission::class)->execute($submission, $candidate->user);
        $url = route('contexts.submissions.assets.download', [$context, $submission, $asset]);

        $this->actingAs($candidate->user)->get($url)->assertOk();
        $this->actingAs($reviewer->user)->get($url)->assertOk();
        $this->actingAs($outsider->user)->get($url)->assertForbidden();
    }

    public function test_farsi_submission_card_uses_rtl_ready_localized_copy(): void
    {
        $actor = Actor::factory()->create(['locale' => 'fa']);
        [, , , $interaction] = $this->publishedInteraction($actor, 'application');

        Livewire::actingAs($actor->user)
            ->test(SubmissionCard::class, ['definition' => $interaction])
            ->assertSee('درخواست')
            ->assertSee('شروع درخواست');
    }

    /**
     * @param  array<string, mixed>  $evaluationConfig
     * @param  list<array<string, mixed>>|null  $items
     * @return array{Context, SpaceContent, SpaceContentRevision, InteractionDefinition}
     */
    private function publishedInteraction(
        Actor $manager,
        string $purpose,
        ?Context $context = null,
        array $evaluationConfig = ['mode' => 'manual', 'score_max' => null, 'criteria' => []],
        ?array $items = null,
    ): array {
        $context ??= app(EnsurePersonalContext::class)->execute($manager->user);

        $definition = SpaceContentDefinition::factory()->create([
            'context_id' => $context->id,
            'group_space_id' => null,
        ]);
        $content = SpaceContent::factory()->create([
            'context_id' => $context->id,
            'group_space_id' => null,
            'space_content_definition_id' => $definition->id,
            'author_actor_id' => $manager->id,
        ]);
        $revision = SpaceContentRevision::factory()->create([
            'space_content_id' => $content->id,
            'created_by_actor_id' => $manager->id,
            'title' => 'Application details',
        ]);
        $canonical = '{"version":1,"interaction":"experience"}';
        $revision->sealManifest(hash('sha256', $canonical), $canonical, 1, 1);
        $content->applyLifecycle([
            'status' => 'published',
            'active_revision_id' => $revision->id,
            'draft_revision_id' => null,
            'published_at' => now(),
        ]);

        $interaction = InteractionDefinition::factory()->create([
            'context_id' => $context->id,
            'space_content_id' => $content->id,
            'created_by_actor_id' => $manager->id,
            'name' => 'Application form',
        ]);
        $version = InteractionDefinitionVersion::factory()->create([
            'interaction_definition_id' => $interaction->id,
            'space_content_revision_id' => $revision->id,
            'created_by_actor_id' => $manager->id,
            'purpose_key' => $purpose,
            'title' => $purpose === 'questionnaire' ? 'Reader questionnaire' : 'Application questions',
            'items' => $items ?? [[
                'key' => 'statement',
                'label' => 'Statement',
                'type' => 'long_text',
                'required' => true,
            ]],
            'evaluation_config' => $evaluationConfig,
        ]);

        app(ActivateInteractionDefinitionVersion::class)->execute(
            $interaction,
            $version,
            $manager->user,
        );

        return [$context, $content->refresh(), $revision, $interaction->refresh()->load('activeVersion')];
    }
}
