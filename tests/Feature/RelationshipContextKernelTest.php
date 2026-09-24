<?php

namespace Tests\Feature;

use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\EndRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\ContextKind;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use App\RelationshipEventType;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RelationshipContextKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationship_requires_explicit_acceptance_before_context_collaboration(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $purpose = Concept::factory()->create();

        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            $purpose,
            'client',
            [['actor' => $bob, 'role' => 'service provider']],
            title: 'Riverside electrical work',
        );

        $context = $relationship->contextBinding->context;

        $this->assertSame(RelationshipStatus::Proposed, $relationship->status);
        $this->assertSame(ContextKind::Relationship, $context->kind);
        $this->assertSame(RelationshipParticipantStatus::Active, $relationship->participants->firstWhere('actor_id', $alice->id)?->status);
        $this->assertSame(RelationshipParticipantStatus::Invited, $relationship->participants->firstWhere('actor_id', $bob->id)?->status);
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $relationship));
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($alice->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($bob->user)->allows('createContent', $context));
        $this->assertDatabaseCount('group_memberships', 0);

        $relationship = app(RespondToRelationship::class)->execute($relationship, $bob->user, true);
        $context = $relationship->contextBinding->context;

        $this->assertSame(RelationshipStatus::Active, $relationship->status);
        $this->assertTrue(Gate::forUser($alice->user)->allows('createContent', $context));
        $this->assertTrue(Gate::forUser($bob->user)->allows('createContent', $context));
        $this->assertTrue(Gate::forUser($alice->user)->allows('manageContent', $context));
        $this->assertFalse(Gate::forUser($bob->user)->allows('manageContent', $context));
        $this->assertDatabaseHas('relationship_events', [
            'relationship_id' => $relationship->id,
            'event_type' => RelationshipEventType::Activated->value,
        ]);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_relationship_context_is_isolated_and_becomes_read_only_when_ended(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            Concept::factory()->create(),
            'client',
            [['actor' => $bob, 'role' => 'provider']],
        );

        $relationship = app(RespondToRelationship::class)->execute($relationship, $bob->user, true);
        $context = $relationship->contextBinding->context;

        $this->assertFalse(Gate::forUser($carol->user)->allows('view', $relationship));
        $this->assertFalse(Gate::forUser($carol->user)->allows('view', $context));

        $relationship = app(EndRelationship::class)->execute($relationship, $alice->user);
        $context = $relationship->contextBinding->context;

        $this->assertSame(RelationshipStatus::Ended, $relationship->status);
        $this->assertTrue(Gate::forUser($alice->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($alice->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($bob->user)->allows('interactContent', $context));
        $this->assertFalse(Gate::forUser($alice->user)->allows('manageDefinitions', $context));
    }

    public function test_declining_a_relationship_request_cancels_the_proposal_without_creating_membership(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            Concept::factory()->create(),
            'requester',
            [['actor' => $bob, 'role' => 'collaborator']],
        );

        $relationship = app(RespondToRelationship::class)->execute($relationship, $bob->user, false);
        $context = $relationship->contextBinding->context;

        $this->assertSame(RelationshipStatus::Cancelled, $relationship->status);
        $this->assertSame(
            RelationshipParticipantStatus::Declined,
            $relationship->participants->firstWhere('actor_id', $bob->id)?->status,
        );
        $this->assertTrue(Gate::forUser($alice->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($alice->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($bob->user)->allows('createContent', $context));
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_visible_originating_intent_can_seed_relationship_purpose_without_becoming_contract_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $purpose = Concept::factory()->create();

        $profile = ActorProfile::factory()->create(['actor_id' => $alice->id]);
        $intent = ActorProfileIntent::factory()->create([
            'actor_profile_id' => $profile->id,
            'concept_id' => $purpose->id,
            'created_by_actor_id' => $alice->id,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Authenticated,
        ]);

        $relationship = app(CreateRelationship::class)->execute(
            $bob->user,
            $purpose,
            'service provider',
            [['actor' => $alice, 'role' => 'client']],
            $intent,
            'Riverside service discussion',
        );

        $this->assertSame($intent->id, $relationship->originating_intent_id);
        $this->assertSame($purpose->id, $relationship->purpose_concept_id);
        $this->assertSame(RelationshipStatus::Proposed, $relationship->status);
        $this->assertDatabaseCount('relationships', 1);
        $this->assertDatabaseCount('relationship_contexts', 1);
        $this->assertDatabaseCount('group_memberships', 0);
    }
}
