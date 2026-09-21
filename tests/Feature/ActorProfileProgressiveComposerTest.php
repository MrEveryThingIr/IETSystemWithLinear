<?php

namespace Tests\Feature;

use App\Actions\Profile\EnsureActorProfile;
use App\Livewire\Profile\Intents;
use App\Livewire\Profile\Semantics;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActorProfileProgressiveComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_need_composer_starts_minimal_and_can_add_and_remove_optional_facets(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        Livewire::actingAs($actor->user)
            ->test(Intents::class, ['profile' => $profile])
            ->assertSet('activeFacets', [])
            ->call('toggleFacet', 'quantity')
            ->assertSet('activeFacets', ['quantity'])
            ->set('quantity', '5')
            ->set('unit', 'kg')
            ->call('toggleFacet', 'quantity')
            ->assertSet('activeFacets', [])
            ->assertSet('quantity', null)
            ->assertSet('unit', null);
    }

    public function test_minimal_need_requires_only_relationship_and_concept(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        Livewire::actingAs($actor->user)
            ->test(Intents::class, ['profile' => $profile])
            ->set('kind', 'need')
            ->set('conceptLabel', 'Bread')
            ->call('save')
            ->assertHasNoErrors();

        $intent = ActorProfileIntent::query()->with('concept.labels')->sole();

        $this->assertSame('Bread', $intent->concept->displayLabel());
        $this->assertNull($intent->title);
        $this->assertNull($intent->description);
        $this->assertNull($intent->quantity);
        $this->assertNull($intent->location_text);
        $this->assertNull($intent->starts_on);
        $this->assertNull($intent->ends_on);
    }

    public function test_user_can_create_new_concept_when_no_suggestion_exists_and_reuse_it_later(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        Livewire::actingAs($actor->user)
            ->test(Semantics::class, ['profile' => $profile])
            ->set('conceptLabel', 'Weekend Robotics')
            ->set('predicate', 'interested_in')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertSame(1, Concept::query()->count());

        Livewire::actingAs($actor->user)
            ->test(Intents::class, ['profile' => $profile])
            ->set('kind', 'offer')
            ->set('conceptLabel', 'Weekend Robotics')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Concept::query()->count());
        $this->assertSame(
            ActorProfileIntent::query()->sole()->concept_id,
            Concept::query()->sole()->id,
        );
    }

    public function test_editing_existing_intent_activates_only_used_facets(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        Livewire::actingAs($actor->user)
            ->test(Intents::class, ['profile' => $profile])
            ->set('conceptLabel', 'Transportation')
            ->call('toggleFacet', 'route')
            ->set('originText', 'A')
            ->set('destinationText', 'B')
            ->call('toggleFacet', 'timing')
            ->set('scheduleKind', 'weekly')
            ->set('recurrenceWeekdays', [6])
            ->call('save')
            ->assertHasNoErrors();

        $intent = ActorProfileIntent::query()->sole();

        Livewire::actingAs($actor->user)
            ->test(Intents::class, ['profile' => $profile])
            ->call('edit', $intent->id)
            ->assertSet('activeFacets', ['route', 'timing']);
    }
}
