<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Conversations\PostContextMessage;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Concept;
use App\Models\Conversation;
use App\Models\Relationship;
use App\RelationshipStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConversationKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationship_message_is_collaboration_evidence_not_lifecycle_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            Concept::factory()->create(),
            'client',
            [['actor' => $bob, 'role' => 'provider']],
        );

        $relationship = app(RespondToRelationship::class)->execute($relationship, $bob->user, true);
        $context = $relationship->contextBinding->context;
        $eventCount = $relationship->events()->count();

        $message = app(PostContextMessage::class)->execute(
            $context,
            $bob->user,
            'I agree to everything discussed here.',
        );

        $this->assertSame('I agree to everything discussed here.', $message->body);
        $this->assertSame(RelationshipStatus::Active, $relationship->fresh()->status);
        $this->assertSame($eventCount, $relationship->events()->count());
        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_proposed_relationship_context_is_readable_but_does_not_accept_messages(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            Concept::factory()->create(),
            'client',
            [['actor' => $bob, 'role' => 'provider']],
        );

        try {
            app(PostContextMessage::class)->execute(
                $relationship->contextBinding->context,
                $alice->user,
                'This must not activate collaboration.',
            );

            $this->fail('A proposed Relationship Context accepted a message.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertSame(RelationshipStatus::Proposed, $relationship->fresh()->status);
    }

    public function test_admission_context_uses_the_same_conversation_kernel(): void
    {
        $candidate = Actor::factory()->create();
        $admission = Admission::factory()->create([
            'candidate_actor_id' => $candidate->id,
            'status' => 'draft',
        ]);

        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);

        $message = app(PostContextMessage::class)->execute(
            $context,
            $candidate->user,
            'Here is a clarification for my application.',
        );

        $this->assertSame($context->id, $message->conversation->context_id);
        $this->assertDatabaseHas('conversation_messages', [
            'id' => $message->id,
            'author_actor_id' => $candidate->id,
        ]);
    }

    public function test_reply_must_stay_inside_the_same_context_conversation(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $first = $this->activeRelationship($alice, $bob);
        $second = $this->activeRelationship($alice, $carol);

        $firstMessage = app(PostContextMessage::class)->execute(
            $first->contextBinding->context,
            $alice->user,
            'First relationship message.',
        );

        try {
            app(PostContextMessage::class)->execute(
                $second->contextBinding->context,
                $alice->user,
                'Invalid cross-context reply.',
                $firstMessage->id,
            );

            $this->fail('A reply crossed the Context boundary.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_each_context_gets_one_main_conversation_even_across_multiple_messages(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $relationship = $this->activeRelationship($alice, $bob);
        $context = $relationship->contextBinding->context;

        app(PostContextMessage::class)->execute($context, $alice->user, 'One');
        app(PostContextMessage::class)->execute($context, $bob->user, 'Two');

        $this->assertSame(1, Conversation::query()
            ->where('context_id', $context->id)
            ->where('key', 'main')
            ->count());
        $this->assertDatabaseCount('conversation_messages', 2);
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
