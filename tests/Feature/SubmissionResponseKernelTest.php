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
use App\Actions\Interactions\WithdrawSubmission;
use App\ContentEvidenceTarget;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\ContentEvidenceReference;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRevision;
use App\Models\Submission;
use App\Models\SubmissionResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SubmissionResponseKernelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_start_is_idempotent_and_max_attempts_count_consumed_attempts(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->activeInteraction($context->id, $actor, [[
            'key' => 'answer',
            'label' => 'Answer',
            'type' => 'long_text',
            'required' => true,
        ]], ['allow_withdrawal' => true, 'max_attempts' => 1]);

        $first = app(StartSubmission::class)->execute($version, $actor->user);
        $again = app(StartSubmission::class)->execute($version, $actor->user);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, $first->attempt_number);

        app(SaveSubmissionResponse::class)->execute($first, $actor->user, 'answer', 'Final answer');
        app(SubmitSubmission::class)->execute($first, $actor->user);
        app(WithdrawSubmission::class)->execute($first, $actor->user);

        try {
            app(StartSubmission::class)->execute($version, $actor->user);
            $this->fail('A second attempt exceeded max_attempts.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_scalar_responses_normalize_and_submit_seals_immutable_evidence(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->activeInteraction($context->id, $actor, [
            [
                'key' => 'years',
                'label' => 'Years',
                'type' => 'number',
                'required' => true,
                'constraints' => ['min' => 0, 'max' => 50],
            ],
            [
                'key' => 'skills',
                'label' => 'Skills',
                'type' => 'multiple_choice',
                'required' => true,
                'options' => ['php', 'laravel', 'sql'],
            ],
        ]);
        $submission = app(StartSubmission::class)->execute($version, $actor->user);

        app(SaveSubmissionResponse::class)->execute($submission, $actor->user, 'years', '7');
        app(SaveSubmissionResponse::class)->execute($submission, $actor->user, 'skills', ['laravel', 'php', 'laravel']);
        $submitted = app(SubmitSubmission::class)->execute($submission, $actor->user);

        $years = $submitted->responses()->where('item_key', 'years')->firstOrFail();
        $skills = $submitted->responses()->where('item_key', 'skills')->firstOrFail();

        $this->assertSame(Submission::STATUS_SUBMITTED, $submitted->status);
        $this->assertSame(7, $years->value);
        $this->assertSame(['laravel', 'php'], $skills->value);
        $this->assertSame(1, $submitted->evidence_schema_version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $submitted->evidence_hash);
        $this->assertSame(
            $submitted->evidence_hash,
            hash('sha256', (string) $submitted->canonical_evidence),
        );

        $this->expectException(LogicException::class);
        $years->update(['value' => 8]);
    }

    public function test_required_response_blocks_submit_without_sealing_draft(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->activeInteraction($context->id, $actor, [[
            'key' => 'statement',
            'label' => 'Statement',
            'type' => 'long_text',
            'required' => true,
        ]]);
        $submission = app(StartSubmission::class)->execute($version, $actor->user);

        try {
            app(SubmitSubmission::class)->execute($submission, $actor->user);
            $this->fail('Submission without its required Response was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('responses.statement', $exception->errors());
        }

        $submission->refresh();
        $this->assertSame(Submission::STATUS_DRAFT, $submission->status);
        $this->assertNull($submission->evidence_hash);
        $this->assertNull($submission->submitted_at);
    }

    public function test_submission_asset_uses_reusable_context_asset_pipeline_and_is_sealed_by_hash(): void
    {
        Storage::fake('local');

        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->activeInteraction($context->id, $actor, [[
            'key' => 'cv',
            'label' => 'CV',
            'type' => 'asset',
            'required' => true,
        ]]);
        $submission = app(StartSubmission::class)->execute($version, $actor->user);

        $asset = app(CreateContextAsset::class)->execute(
            $context,
            $actor->user,
            UploadedFile::fake()->create('cv.pdf', 24, 'application/pdf'),
            'owned',
            'Candidate CV',
        );
        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $actor->user,
            'cv',
            asset: $asset,
        );
        $submitted = app(SubmitSubmission::class)->execute($submission, $actor->user);

        $response = $submitted->responses()->where('item_key', 'cv')->firstOrFail();

        $this->assertSame($context->id, $asset->context_id);
        $this->assertNull($asset->group_space_id);
        $this->assertSame($asset->id, $response->asset_id);
        $this->assertStringContainsString($asset->sha256, (string) $submitted->canonical_evidence);
        Storage::disk('local')->assertExists($asset->storage_key);

        $this->expectException(LogicException::class);
        $asset->delete();
    }

    public function test_content_evidence_may_cross_context_when_submitter_can_view_the_sealed_source(): void
    {
        $actor = Actor::factory()->create();
        $personal = app(EnsurePersonalContext::class)->execute($actor->user);
        $sourceDefinition = SpaceContentDefinition::factory()->create([
            'context_id' => $personal->id,
            'group_space_id' => null,
        ]);
        $content = SpaceContent::factory()->create([
            'context_id' => $personal->id,
            'group_space_id' => null,
            'space_content_definition_id' => $sourceDefinition->id,
            'author_actor_id' => $actor->id,
        ]);
        $revision = SpaceContentRevision::factory()->create([
            'space_content_id' => $content->id,
            'created_by_actor_id' => $actor->id,
        ]);
        $canonicalManifest = '{"version":1,"portfolio":"proof"}';
        $revision->sealManifest(hash('sha256', $canonicalManifest), $canonicalManifest, 1, 1);
        $content->applyLifecycle([
            'status' => 'published',
            'active_revision_id' => $revision->id,
            'draft_revision_id' => null,
            'published_at' => now(),
        ]);
        $reference = ContentEvidenceReference::query()->create([
            'context_id' => $personal->id,
            'space_content_id' => $content->id,
            'space_content_revision_id' => $revision->id,
            'target_type' => ContentEvidenceTarget::Revision,
            'target_uuid' => null,
            'field_key' => null,
            'created_by_actor_id' => $actor->id,
            'metadata' => [],
        ]);

        $reviewer = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Evidence application group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $actor->id,
            'status' => 'under_review',
        ]);
        $admissionContext = app(EnsureAdmissionContext::class)->execute($admission, $actor->user);
        $version = $this->activeInteraction($admissionContext->id, $reviewer, [[
            'key' => 'portfolio',
            'label' => 'Portfolio evidence',
            'type' => 'content_evidence',
            'required' => true,
        ]]);
        $submission = app(StartSubmission::class)->execute($version, $actor->user);

        app(SaveSubmissionResponse::class)->execute(
            $submission,
            $actor->user,
            'portfolio',
            contentEvidence: $reference,
        );
        $submitted = app(SubmitSubmission::class)->execute($submission, $actor->user);

        $response = $submitted->responses()->where('item_key', 'portfolio')->firstOrFail();
        $this->assertSame($reference->id, $response->content_evidence_reference_id);
        $this->assertStringContainsString($reference->uuid, (string) $submitted->canonical_evidence);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_admission_candidate_draft_is_private_until_submit_and_never_grants_group_access(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Structured admission group', null);
        $space = $group->spaces()->sole();
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        $version = $this->activeInteraction($context->id, $reviewer, [[
            'key' => 'why',
            'label' => 'Why apply?',
            'type' => 'long_text',
            'required' => true,
        ]]);

        $submission = app(StartSubmission::class)->execute($version, $candidate->user);

        $this->assertTrue(Gate::forUser($candidate->user)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($reviewer->user)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('view', $submission));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('view', $space));

        app(SaveSubmissionResponse::class)->execute($submission, $candidate->user, 'why', 'I can contribute.');
        $submitted = app(SubmitSubmission::class)->execute($submission, $candidate->user);

        $this->assertTrue(Gate::forUser($reviewer->user)->allows('view', $submitted));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('view', $space));
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }

    public function test_withdraw_is_idempotent_and_preserves_submitted_evidence(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->activeInteraction($context->id, $actor, [[
            'key' => 'answer',
            'label' => 'Answer',
            'type' => 'short_text',
            'required' => true,
        ]]);
        $submission = app(StartSubmission::class)->execute($version, $actor->user);
        app(SaveSubmissionResponse::class)->execute($submission, $actor->user, 'answer', 'One');
        $submitted = app(SubmitSubmission::class)->execute($submission, $actor->user);
        $hash = $submitted->evidence_hash;
        $canonical = $submitted->canonical_evidence;

        $withdrawn = app(WithdrawSubmission::class)->execute($submitted, $actor->user);
        $again = app(WithdrawSubmission::class)->execute($withdrawn, $actor->user);

        $this->assertSame(Submission::STATUS_WITHDRAWN, $again->status);
        $this->assertNotNull($again->withdrawn_at);
        $this->assertSame($hash, $again->evidence_hash);
        $this->assertSame($canonical, $again->canonical_evidence);
        $this->assertSame(1, SubmissionResponse::query()->where('submission_id', $again->id)->count());
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $settings
     */
    private function activeInteraction(
        int $contextId,
        Actor $manager,
        array $items,
        array $settings = ['allow_withdrawal' => true, 'max_attempts' => null],
    ): InteractionDefinitionVersion {
        $definition = InteractionDefinition::factory()->create([
            'context_id' => $contextId,
            'created_by_actor_id' => $manager->id,
            'name' => 'Structured interaction',
        ]);
        $version = InteractionDefinitionVersion::factory()->create([
            'interaction_definition_id' => $definition->id,
            'created_by_actor_id' => $manager->id,
            'purpose_key' => 'application',
            'title' => 'Structured interaction',
            'items' => $items,
            'settings' => $settings,
        ]);

        app(ActivateInteractionDefinitionVersion::class)->execute(
            $definition,
            $version,
            $manager->user,
        );

        return $version->refresh();
    }
}
