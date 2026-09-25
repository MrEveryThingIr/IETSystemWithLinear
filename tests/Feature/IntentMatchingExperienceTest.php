<?php

namespace Tests\Feature;

use App\Livewire\Intents\Matches;
use App\Livewire\Relationships\Create as RelationshipCreate;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\Relationship;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntentMatchingExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_profile_identity_stays_hidden_while_explicit_intent_can_match(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $concept = Concept::factory()->create();

        $source = $this->intent($alice, $concept, ProfileIntentKind::Need, 'Construction worker needed');
        $candidate = $this->intent($bob, $concept, ProfileIntentKind::Offer, 'Construction service available');

        $this->assertSame('private', $candidate->profile->visibility->value);

        Livewire::actingAs($alice->user)
            ->test(Matches::class, ['intent' => $source])
            ->assertSee('Construction service available')
            ->assertSee('Participant identity remains private at this visibility level.')
            ->assertDontSee($bob->user->username)
            ->assertSee(e(route('relationships.create', [
                'intent' => $source->uuid,
                'match' => $candidate->uuid,
            ])), false);
    }

    public function test_match_handoff_creates_only_a_proposed_relationship_with_both_intent_origins(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $concept = Concept::factory()->create();

        $source = $this->intent($alice, $concept, ProfileIntentKind::Need, 'Riverside construction worker needed');
        $candidate = $this->intent($bob, $concept, ProfileIntentKind::Offer, 'Bob construction work');

        Livewire::actingAs($alice->user)
            ->withQueryParams([
                'intent' => $source->uuid,
                'match' => $candidate->uuid,
            ])
            ->test(RelationshipCreate::class)
            ->assertSet('purposeLocked', true)
            ->assertSet('participantLocked', true)
            ->assertSet('participantUsername', '')
            ->assertSee('Participant identity remains private at this visibility level.')
            ->assertDontSee($bob->user->username)
            ->set('creatorRole', 'client')
            ->set('participantRole', 'service provider')
            ->set('title', 'Riverside construction work')
            ->call('save')
            ->assertHasNoErrors();

        $relationship = Relationship::query()
            ->with(['participants', 'events'])
            ->sole();

        $this->assertSame($source->id, $relationship->originating_intent_id);
        $this->assertSame($candidate->id, $relationship->matched_intent_id);
        $this->assertSame('proposed', $relationship->status->value);
        $this->assertSame(
            $candidate->id,
            $relationship->events->first()->payload['matched_intent_id'],
        );
        $this->assertTrue(
            $relationship->participants->contains(
                fn ($participant): bool => (int) $participant->actor_id === (int) $bob->id,
            ),
        );

        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('commitments', 0);
        $this->assertDatabaseCount('financial_obligations', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_tampered_incompatible_match_is_rejected_before_relationship_creation(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $source = $this->intent(
            $alice,
            Concept::factory()->create(),
            ProfileIntentKind::Need,
            'Electrical work needed',
        );
        $candidate = $this->intent(
            $bob,
            Concept::factory()->create(),
            ProfileIntentKind::Offer,
            'Masonry available',
        );

        $this->actingAs($alice->user)
            ->get(route('relationships.create', [
                'intent' => $source->uuid,
                'match' => $candidate->uuid,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('relationships', 0);
    }

    public function test_direct_foreign_intent_handoff_no_longer_exposes_private_profile_username(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $intent = $this->intent(
            $alice,
            Concept::factory()->create(),
            ProfileIntentKind::Need,
            'Private-profile service need',
        );

        Livewire::actingAs($bob->user)
            ->withQueryParams(['intent' => $intent->uuid])
            ->test(RelationshipCreate::class)
            ->assertSet('participantLocked', true)
            ->assertSet('participantUsername', '')
            ->assertSee('Participant identity remains private at this visibility level.')
            ->assertDontSee($alice->user->username);
    }

    private function intent(
        Actor $actor,
        Concept $concept,
        ProfileIntentKind $kind,
        string $title,
    ): ActorProfileIntent {
        $profile = ActorProfile::factory()->create([
            'actor_id' => $actor->id,
            'visibility' => 'private',
        ]);

        return ActorProfileIntent::factory()->create([
            'actor_profile_id' => $profile->id,
            'concept_id' => $concept->id,
            'created_by_actor_id' => $actor->id,
            'kind' => $kind,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Authenticated,
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => $title,
            'starts_on' => null,
        ]);
    }
}
