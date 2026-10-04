<?php

namespace Tests\Feature\Businesses;

use App\Models\User;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_is_a_standalone_guest_website_without_platform_navigation(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک مهوری',
            'kind' => 'real_estate',
            'visibility' => 'public',
            'status' => 'active',
            'short_intro' => 'مشاور شما برای خرید، فروش، رهن و اجاره.',
        ]);

        $response = $this->get(route('public.businesses.show', $business));

        $response
            ->assertOk()
            ->assertSee('مشاور املاک مهوری')
            ->assertSee('مشاور شما برای خرید، فروش، رهن و اجاره.')
            ->assertSee(route('public.real-estate.show', $business->publicIntakePortals()->sole()), false)
            ->assertDontSee(route('dashboard'), false)
            ->assertDontSee(route('planner.index'), false)
            ->assertDontSee(route('businesses.index'), false);
    }

    public function test_private_business_has_no_public_website(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Private Office',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->get(route('public.businesses.show', $business))->assertNotFound();
    }

    public function test_public_site_only_exposes_published_public_listings(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Public Shop',
            'kind' => 'retail',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $catalog = app(BusinessCatalogService::class);

        $public = $catalog->createListing($business, $owner->actor, 'good', [
            'title' => 'Public item',
        ], visibility: 'public');
        $catalog->publish($public, $public->currentVersion()->firstOrFail(), $owner->actor);

        $private = $catalog->createListing($business, $owner->actor, 'good', [
            'title' => 'Private item',
        ], visibility: 'private');
        $catalog->publish($private, $private->currentVersion()->firstOrFail(), $owner->actor);

        $this->get(route('public.businesses.show', $business))
            ->assertOk()
            ->assertSee('Public item')
            ->assertDontSee('Private item');

        $this->get(route('public.businesses.listings.show', [$business, $public]))
            ->assertOk()
            ->assertSee('Public item');

        $this->get(route('public.businesses.listings.show', [$business, $private]))
            ->assertNotFound();
    }
}
