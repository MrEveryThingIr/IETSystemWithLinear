<?php

namespace Tests\Feature\Market;

use App\Livewire\Intents\Composer;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ComposerBusinessCatalogTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_catalog_property_facts_become_filterable_details_without_copying_ownership(): void
    {
        $actor = Actor::factory()->create();
        $owner = $actor->user;
        $this->publishSurfaces($owner, ['business', 'market']);
        $business = app(BusinessService::class)->create($actor, ['name' => 'Filter Office', 'kind' => 'real_estate', 'visibility' => 'public', 'status' => 'active']);
        $listing = app(BusinessCatalogService::class)->createListing($business, $actor, 'property', ['title' => 'Flat for rent'], visibility: 'public');
        $this->actingAs($owner)->put(route('businesses.catalog.listings.update', [$business, $listing]), [
            'title' => 'Flat for rent', 'visibility' => 'public', 'availability_status' => 'available', 'simple_office_mode' => '1',
            'transaction_mode' => 'rent', 'property_class' => 'residential', 'public_area' => 'Valiasr', 'construction_area' => '۱۰۰', 'bedrooms' => '2',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('businesses.catalog.listings.publish', [$business, $listing]))->assertRedirect();
        $this->actingAs($owner)->post(route('businesses.catalog.listings.market.store', [$business, $listing]))->assertRedirect();

        $post = ActorProfileIntent::query()->where('business_listing_id', $listing->id)->sole();
        $version = $post->publishedVersion;
        $this->assertSame(1, $version->version);
        $this->assertSame(['floor_area', 'rooms'], collect($version->snapshot['details'])->pluck('canonical_key')->sort()->values()->all());
        $this->assertDatabaseHas('intent_search_values', ['intent_version_id' => $version->id, 'canonical_key' => 'floor_area', 'value_number' => '100.000000']);
        $this->assertSame($listing->fresh()->publishedVersion->id, (int) $post->business_listing_version_id);

        $viewer = Actor::factory()->create()->user;
        $this->publishSurfaces($viewer, ['market']);
        $this->filter($viewer, 'floor_area', min: '80', max: '120')->assertSee('Flat for rent');
        $this->filter($viewer, 'floor_area', min: '101')->assertDontSee('Flat for rent');
        $this->filter($viewer, 'rooms', min: '۲')->assertSee('Flat for rent');
        $this->filter($viewer, 'brand', text: 'x')->assertDontSee('Flat for rent');
    }

    public function test_catalog_owned_terms_are_read_only_and_a_new_catalog_version_is_explicit(): void
    {
        $actor = Actor::factory()->create();
        $owner = $actor->user;
        $this->publishSurfaces($owner, ['business', 'market']);
        $business = app(BusinessService::class)->create($actor, ['name' => 'Locked Office', 'kind' => 'real_estate', 'visibility' => 'public', 'status' => 'active']);
        $listing = app(BusinessCatalogService::class)->createListing($business, $actor, 'property', ['title' => 'Catalog flat'], visibility: 'public');
        $fields = ['title' => 'Catalog flat', 'visibility' => 'public', 'availability_status' => 'available', 'simple_office_mode' => '1',
            'transaction_mode' => 'rent', 'property_class' => 'residential', 'public_area' => 'Valiasr', 'construction_area' => '90'];
        $this->actingAs($owner)->put(route('businesses.catalog.listings.update', [$business, $listing]), $fields)->assertRedirect();
        $this->actingAs($owner)->post(route('businesses.catalog.listings.publish', [$business, $listing]))->assertRedirect();
        $this->actingAs($owner)->post(route('businesses.catalog.listings.market.store', [$business, $listing]))->assertRedirect();
        $post = ActorProfileIntent::query()->where('business_listing_id', $listing->id)->sole();

        $component = Livewire::actingAs($owner)->test(Composer::class, ['intent' => $post])->assertSee('Catalog flat')
            ->assertSeeHtml('data-catalog-card')->assertDontSeeHtml('wire:click="useNewCatalogVersion"')
            ->set('core.title', 'Tampered')->call('saveDraft');
        $this->assertSame('Catalog flat', $post->fresh()->working_copy['core']['title']);

        $this->actingAs($owner)->put(route('businesses.catalog.listings.update', [$business, $listing]), [...$fields, 'title' => 'Catalog flat v2'])->assertRedirect();
        $this->actingAs($owner)->post(route('businesses.catalog.listings.publish', [$business, $listing]))->assertRedirect();
        $versionBefore = $post->fresh()->business_listing_version_id;
        Livewire::actingAs($owner)->test(Composer::class, ['intent' => $post->fresh()])->assertSeeHtml('wire:click="useNewCatalogVersion"');
        $this->assertSame($versionBefore, $post->fresh()->business_listing_version_id);

        $component->call('useNewCatalogVersion');
        $this->assertSame(2, ActorProfileIntent::query()->where('business_listing_id', $listing->id)->count());
        $this->assertSame('closed', $post->fresh()->status->value);
        $this->assertSame(1, $post->versions()->count());
    }

    public function test_unknown_filter_keys_are_ignored_and_text_wildcards_are_literal(): void
    {
        $viewer = Actor::factory()->create()->user;
        $this->publishSurfaces($viewer, ['market']);
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'password; drop table')->assertOk();
        Livewire::actingAs($viewer)->test(Directory::class)->set('detail', 'brand')->set('detailText', '%')->assertOk();
    }

    private function filter(User $viewer, string $key, string $min = '', string $max = '', string $text = ''): Testable
    {
        return Livewire::actingAs($viewer)->test(Directory::class)->set('detail', $key)->set('detailMin', $min)->set('detailMax', $max)->set('detailText', $text);
    }
}
