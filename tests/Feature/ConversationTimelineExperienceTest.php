<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Contexts\CreateContextContent;
use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Conversations\PostContextMessage;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PostGroupSpaceMessage;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Contexts\Conversation as ContextConversation;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Asset;
use App\Models\Concept;
use App\Models\ConversationMessage;
use App\Models\Relationship;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\RelationshipStatus;
use App\Support\ContextTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConversationTimelineExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationship_conversation_and_timeline_are_reconstructed_without_authoritative_side_effects(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $relationship = $this->activeRelationship($alice, $bob);
        $context = $relationship->contextBinding->context;
        $relationshipEventCount = $relationship->events()->count();

        Livewire::actingAs($alice->user)
            ->test(ContextConversation::class, ['context' => $context])
            ->set('message', 'I agree to everything in this chat.')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee('I agree to everything in this chat.');

        $first = app(ContextTimeline::class)->entries($context, $alice->user);
        $second = app(ContextTimeline::class)->entries($context, $alice->user);

        $this->assertSame($first->pluck('key')->all(), $second->pluck('key')->all());
        $this->assertTrue($first->contains(fn ($entry): bool => $entry->kind === 'message'));
        $this->assertTrue($first->contains(fn ($entry): bool => $entry->kind === 'relationship'));
        $this->assertTrue($first->contains(
            fn ($entry): bool => $entry->kind === 'relationship'
                && $entry->url === route('relationships.show', $relationship),
        ));

        $this->assertSame(RelationshipStatus::Active, $relationship->fresh()->status);
        $this->assertSame($relationshipEventCount, $relationship->events()->count());
        $this->assertFalse(Schema::hasTable('timeline_entries'));

        $this->actingAs($alice->user)
            ->get(route('contexts.timeline', $context))
            ->assertOk()
            ->assertSee(__('collaboration.timeline.projection_notice'));

        $this->actingAs($outsider->user)
            ->get(route('contexts.conversation', $context))
            ->assertForbidden();

        $this->actingAs($outsider->user)
            ->get(route('contexts.timeline', $context))
            ->assertForbidden();
    }

    public function test_message_reuses_existing_context_asset_and_exact_evidence_reference(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $relationship = $this->activeRelationship($alice, $bob);
        $context = $relationship->contextBinding->context;

        $definition = SpaceContentDefinition::factory()->create([
            'context_id' => $context->id,
            'group_space_id' => null,
            'created_by_actor_id' => $alice->id,
            'status' => 'active',
        ]);

        SpaceContentDefinitionVersion::factory()->published()->create([
            'space_content_definition_id' => $definition->id,
            'created_by_actor_id' => $alice->id,
        ]);

        $content = app(CreateContextContent::class)->execute(
            $context,
            $definition,
            $alice->user,
            'Riverside evidence note',
            ['summary' => 'Exact published note'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $alice->user);
        $revision = $content->activeRevisionRecord();
        $this->assertNotNull($revision);

        $reference = app(CreateContentEvidenceReference::class)->execute(
            $content,
            $revision,
            $alice->user,
        );

        $asset = Asset::factory()->create([
            'context_id' => $context->id,
            'group_space_id' => null,
            'uploaded_by_actor_id' => $alice->id,
            'original_filename' => 'riverside-photo.jpg',
        ]);

        Livewire::actingAs($alice->user)
            ->test(ContextConversation::class, ['context' => $context])
            ->set('message', 'Use these exact references.')
            ->set('assetIds', [$asset->id])
            ->set('evidenceReferenceIds', [$reference->id])
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee('riverside-photo.jpg')
            ->assertSee('Riverside evidence note');

        $message = ConversationMessage::query()->sole();

        $this->assertDatabaseHas('conversation_message_assets', [
            'conversation_message_id' => $message->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('conversation_message_evidence_references', [
            'conversation_message_id' => $message->id,
            'content_evidence_reference_id' => $reference->id,
        ]);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('content_evidence_references', 1);
    }

    public function test_admission_and_group_contexts_use_the_same_timeline_projection(): void
    {
        $candidate = Actor::factory()->create();
        $admission = Admission::factory()->create([
            'candidate_actor_id' => $candidate->id,
            'status' => 'draft',
        ]);
        $admissionContext = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);

        $admission->transitionTo('submitted', $candidate, 'Ready for review.');

        $admissionEntries = app(ContextTimeline::class)->entries($admissionContext, $candidate->user);
        $this->assertTrue($admissionEntries->contains(fn ($entry): bool => $entry->kind === 'admission'));

        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Timeline group', null);
        $space = $group->spaces()->sole();
        $context = $space->contextBinding()->with('context')->firstOrFail()->context;

        app(PostGroupSpaceMessage::class)->execute(
            $space,
            $owner->user,
            'Group conversation is now Context-scoped.',
        );

        $groupEntries = app(ContextTimeline::class)->entries($context, $owner->user);
        $this->assertTrue($groupEntries->contains(fn ($entry): bool => $entry->kind === 'message'));

        $this->actingAs($candidate->user)
            ->get(route('admissions.context.timeline', $admission))
            ->assertRedirect(route('contexts.timeline', $admissionContext));

        $this->actingAs($owner->user)
            ->get(route('groups.spaces.show', [$group, $space]))
            ->assertOk()
            ->assertSee(route('contexts.timeline', $context), false);
    }

    public function test_message_cannot_attach_an_asset_from_another_context(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $first = $this->activeRelationship($alice, $bob);
        $second = $this->activeRelationship($alice, $carol);

        $asset = Asset::factory()->create([
            'context_id' => $first->contextBinding->context->id,
            'group_space_id' => null,
            'uploaded_by_actor_id' => $alice->id,
        ]);

        try {
            app(PostContextMessage::class)->execute(
                $second->contextBinding->context,
                $alice->user,
                'Cross-context attachment must fail.',
                assetIds: [$asset->id],
            );

            $this->fail('A cross-Context Asset was attached to a message.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertDatabaseCount('conversation_message_assets', 0);
    }

    private function activeRelationship(Actor $creator, Actor $invitee): Relationship
    {
        $relationship = app(CreateRelationship::class)->execute(
            $creator->user,
            Concept::factory()->create(),
            'participant',
            [['actor' => $invitee, 'role' => 'participant']],
        );

        return app(RespondToRelationship::class)->execute($relationship, $invitee->user, true);
    }
}
