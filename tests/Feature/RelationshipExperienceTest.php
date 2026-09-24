<?php

namespace Tests\Feature;

use App\Livewire\Relationships\Create as RelationshipCreate;
use App\Livewire\Relationships\Show as RelationshipShow;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\Relationship;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RelationshipExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_intent_can_open_a_prefilled_relationship_request_and_activate_after_consent(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $purpose = Concept::factory()->create();

        $profile = ActorProfile::factory()->create([
            'actor_id' => $alice->id,
            'display_name' => 'Alice Morgan',
        ]);

        $intent = ActorProfileIntent::factory()->create([
            'actor_profile_id' => $profile->id,
            'concept_id' => $purpose->id,
            'created_by_actor_id' => $alice->id,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Authenticated,
            'title' => 'Riverside electrical work',
        ]);

        $this->actingAs($bob->user)
            ->get(route('intents.index'))
            ->assertOk()
            ->assertSee(route('relationships.create', ['intent' => $intent->uuid]), false);

        Livewire::actingAs($bob->user)
            ->withQueryParams(['intent' => $intent->uuid])
            ->test(RelationshipCreate::class)
            ->assertSet('purposeLocked', true)
            ->assertSet('participantLocked', true)
            ->assertSet('participantUsername', $alice->user->username)
            ->set('creatorRole', 'service provider')
            ->set('participantRole', 'client')
            ->set('title', 'Riverside electrical work')
            ->call('save');

        $relationship = Relationship::query()->with('participants')->sole();

        $this->assertSame(RelationshipStatus::Proposed, $relationship->status);
        $this->assertSame($intent->id, $relationship->originating_intent_id);
        $this->assertSame(
            RelationshipParticipantStatus::Invited,
            $relationship->participants->firstWhere('actor_id', $alice->id)?->status,
        );

        Livewire::actingAs($alice->user)
            ->test(RelationshipShow::class, ['relationship' => $relationship])
            ->assertSee('Riverside electrical work')
            ->call('accept');

        $this->assertSame(RelationshipStatus::Active, $relationship->fresh()->status);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_direct_relationship_request_uses_known_username_and_selected_purpose(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $carol = Actor::factory()->create();
        $purpose = Concept::factory()->create();

        Livewire::actingAs($alice->user)
            ->test(RelationshipCreate::class)
            ->call('selectPurpose', $purpose->id)
            ->set('participantUsername', $carol->user->username)
            ->set('creatorRole', 'project owner')
            ->set('participantRole', 'capital collaborator')
            ->set('title', 'Riverside capital collaboration')
            ->call('save');

        $relationship = Relationship::query()->with('participants')->sole();

        $this->assertSame($purpose->id, $relationship->purpose_concept_id);
        $this->assertNull($relationship->originating_intent_id);
        $this->assertSame('project owner', $relationship->participants->firstWhere('actor_id', $alice->id)?->role);
        $this->assertSame('capital collaborator', $relationship->participants->firstWhere('actor_id', $carol->id)?->role);
    }

    public function test_relationship_pages_are_participant_only_and_workspace_is_read_only_until_acceptance(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();

        Livewire::actingAs($alice->user)
            ->test(RelationshipCreate::class)
            ->call('selectPurpose', Concept::factory()->create()->id)
            ->set('participantUsername', $bob->user->username)
            ->set('creatorRole', 'client')
            ->set('participantRole', 'provider')
            ->call('save');

        $relationship = Relationship::query()->with('contextBinding.context')->sole();
        $context = $relationship->contextBinding->context;

        $this->actingAs($alice->user)
            ->get(route('relationships.show', $relationship))
            ->assertOk();

        $this->actingAs($outsider->user)
            ->get(route('relationships.show', $relationship))
            ->assertForbidden();

        $this->actingAs($alice->user)
            ->get(route('contexts.contents.index', $context))
            ->assertOk()
            ->assertSee(__('ui.context_content.read_only'));

        Livewire::actingAs($bob->user)
            ->test(RelationshipShow::class, ['relationship' => $relationship])
            ->call('accept');

        $this->actingAs($alice->user)
            ->get(route('contexts.contents.index', $context))
            ->assertOk()
            ->assertSee(__('ui.context_content.create_with_blueprint'));
    }
}
