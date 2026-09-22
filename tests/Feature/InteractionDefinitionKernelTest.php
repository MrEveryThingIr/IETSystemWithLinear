<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Interactions\ActivateInteractionDefinitionVersion;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRevision;
use App\Policies\ContextPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class InteractionDefinitionKernelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_version_configuration_is_normalized_hashed_and_activated_immutably(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $definition = InteractionDefinition::factory()->create([
            'context_id' => $context->id,
            'created_by_actor_id' => $actor->id,
            'name' => 'Skills questionnaire',
        ]);
        $version = InteractionDefinitionVersion::factory()->create([
            'interaction_definition_id' => $definition->id,
            'created_by_actor_id' => $actor->id,
            'purpose_key' => 'Questionnaire',
            'title' => ' Skills questionnaire ',
            'items' => [[
                'key' => 'EXPERIENCE',
                'label' => ' Experience ',
                'type' => 'long_text',
                'required' => true,
                'constraints' => ['max_length' => 5000],
            ]],
        ]);

        $activated = app(ActivateInteractionDefinitionVersion::class)->execute(
            $definition,
            $version,
            $actor->user,
        );

        $version->refresh();

        $this->assertSame(InteractionDefinition::STATUS_ACTIVE, $activated->status);
        $this->assertSame($version->id, $activated->active_version_id);
        $this->assertNotNull($version->published_at);
        $this->assertSame('questionnaire', $version->purpose_key);
        $this->assertSame('Skills questionnaire', $version->title);
        $this->assertSame('experience', $version->items[0]['key']);
        $this->assertSame([], $version->items[0]['options']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $version->content_hash);

        $this->expectException(LogicException::class);
        $version->update(['title' => 'Changed after activation']);
    }

    public function test_definition_lifecycle_cannot_be_changed_directly(): void
    {
        $definition = InteractionDefinition::factory()->create();

        $this->expectException(LogicException::class);
        $definition->update(['status' => InteractionDefinition::STATUS_ACTIVE]);
    }

    public function test_unsupported_response_type_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        InteractionDefinitionVersion::factory()->create([
            'items' => [[
                'key' => 'dangerous',
                'label' => 'Dangerous',
                'type' => 'php',
                'required' => false,
            ]],
        ]);
    }

    public function test_unknown_item_configuration_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        InteractionDefinitionVersion::factory()->create([
            'items' => [[
                'key' => 'answer',
                'label' => 'Answer',
                'type' => 'short_text',
                'required' => true,
                'script' => 'alert(1)',
            ]],
        ]);
    }

    public function test_content_bound_version_requires_the_same_sealed_content_revision_before_activation(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $contentDefinition = SpaceContentDefinition::factory()->create([
            'context_id' => $context->id,
            'group_space_id' => null,
        ]);
        $content = SpaceContent::factory()->create([
            'context_id' => $context->id,
            'group_space_id' => null,
            'space_content_definition_id' => $contentDefinition->id,
            'author_actor_id' => $actor->id,
        ]);
        $revision = SpaceContentRevision::factory()->create([
            'space_content_id' => $content->id,
            'created_by_actor_id' => $actor->id,
        ]);
        $definition = InteractionDefinition::factory()->create([
            'context_id' => $context->id,
            'space_content_id' => $content->id,
            'created_by_actor_id' => $actor->id,
        ]);
        $version = InteractionDefinitionVersion::factory()->create([
            'interaction_definition_id' => $definition->id,
            'space_content_revision_id' => $revision->id,
            'created_by_actor_id' => $actor->id,
        ]);

        try {
            app(ActivateInteractionDefinitionVersion::class)->execute(
                $definition,
                $version,
                $actor->user,
            );
            $this->fail('Unsealed Content must not activate a Content-bound Interaction version.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('sealed revision', $exception->getMessage());
        }

        $canonicalManifest = '{"version":1,"content":"exam"}';
        $revision->sealManifest(
            hash('sha256', $canonicalManifest),
            $canonicalManifest,
            1,
            1,
        );

        $activated = app(ActivateInteractionDefinitionVersion::class)->execute(
            $definition,
            $version,
            $actor->user,
        );

        $this->assertSame($version->id, $activated->active_version_id);
    }

    public function test_admission_context_exposes_separate_submission_and_review_authorization_without_membership(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Application review group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        $policy = app(ContextPolicy::class);

        $this->assertTrue($policy->submitInteractions($candidate->user, $context));
        $this->assertTrue($policy->reviewInteractions($reviewer->user, $context));
        $this->assertFalse($policy->submitInteractions($outsider->user, $context));
        $this->assertFalse($policy->reviewInteractions($candidate->user, $context));
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }
}
