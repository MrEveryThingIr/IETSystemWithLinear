<?php

namespace Tests\Feature;

use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Relationships\Create as RelationshipCreate;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\Plan;
use App\Models\Relationship;
use App\Support\DomainBlueprintCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DomainBlueprintExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_journeys_catalog_exposes_six_proven_compositions(): void
    {
        $alice = Actor::factory()->create();

        $this->actingAs($alice->user)
            ->get(route('journeys.index'))
            ->assertOk()
            ->assertSee('Simple Sale')
            ->assertSee('Rental')
            ->assertSee('Service Job')
            ->assertSee('Employment / Paid Work')
            ->assertSee('Construction Partnership')
            ->assertSee('Personal Activity')
            ->assertSee(__('journeys.boundary'));
    }

    public function test_relationship_recipe_prefills_roles_and_persists_exact_source_version(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $purpose = Concept::factory()->create(['slug' => 'service']);
        $version = app(DomainBlueprintCatalog::class)->version('service-job');

        Livewire::actingAs($alice->user)
            ->withQueryParams(['blueprint' => 'service-job'])
            ->test(RelationshipCreate::class)
            ->assertSet('blueprintSlug', 'service-job')
            ->assertSet('creatorRole', 'client')
            ->assertSet('participantRole', 'service provider')
            ->assertSet('purposeSearch', 'service')
            ->assertSee('Service Job')
            ->call('selectPurpose', $purpose->id)
            ->set('participantUsername', $bob->user->username)
            ->set('title', 'Riverside electrical service')
            ->call('save')
            ->assertHasNoErrors();

        $relationship = Relationship::query()
            ->with('domainBlueprintVersion.blueprint')
            ->sole();

        $this->assertSame($version->id, $relationship->domain_blueprint_version_id);
        $this->assertSame('Service Job', $relationship->domainBlueprintVersion->blueprint->name);

        $this->actingAs($alice->user)
            ->get(route('relationships.show', $relationship))
            ->assertOk()
            ->assertSee('Service Job')
            ->assertSee(__('journeys.capabilities.contract'));
    }

    public function test_personal_activity_recipe_guides_planner_and_persists_exact_source_version(): void
    {
        $bob = Actor::factory()->create();
        $version = app(DomainBlueprintCatalog::class)->version('personal-activity');

        Livewire::actingAs($bob->user)
            ->withQueryParams(['blueprint' => 'personal-activity'])
            ->test(PlannerCreate::class)
            ->assertSet('blueprintSlug', 'personal-activity')
            ->assertSet('frequency', 'once')
            ->assertSet('durationMinutes', 60)
            ->assertSee('Personal Activity')
            ->set('title', 'Study mathematics')
            ->set('startsOn', '2026-09-26')
            ->set('startTime', '18:00')
            ->set('reminderOffsets', '15')
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::query()
            ->with('domainBlueprintVersion.blueprint')
            ->sole();

        $this->assertSame($version->id, $plan->domain_blueprint_version_id);
        $this->assertSame('Personal Activity', $plan->domainBlueprintVersion->blueprint->name);

        $this->actingAs($bob->user)
            ->get(route('planner.show', $plan))
            ->assertOk()
            ->assertSee('Personal Activity')
            ->assertSee(__('journeys.capabilities.planner'));
    }
}
