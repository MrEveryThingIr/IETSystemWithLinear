<?php

namespace Tests\Feature\Businesses;

use App\Models\BusinessListing;
use App\Models\PublicIntakePortalGrant;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use App\Services\Business\BusinessService;
use App\Services\Contacts\BusinessContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BusinessRealEstatePromotionRouteTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_business_owned_real_estate_case_can_be_promoted_through_the_nested_route(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک مهوری',
            'kind' => 'real_estate',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $portal = $business->publicIntakePortals()->sole();
        $contact = app(BusinessContactResolver::class)->resolve(
            $business,
            'Route Seller',
            '09121234567',
            'promotion_route_test',
        );

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $contact->id,
            'reference_code' => 'RE-ROUTETEST1',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'Route Seller',
            'phone' => '09121234567',
            'property_class' => 'residential',
            'property_subtype' => 'apartment',
            'public_area' => 'Test Area',
            'asking_price' => 2500000000,
            'price_unit' => 'toman',
            'status' => 'qualified',
            'preview_token_hash' => hash('sha256', 'route-test'),
        ]);

        $response = $this->actingAs($owner)
            ->post(route('businesses.real-estate.cases.promote', [$business, $portal, $case]));

        $case->refresh();

        $this->assertNotNull($case->business_listing_id);

        $listing = BusinessListing::query()
            ->with('currentVersion')
            ->findOrFail($case->business_listing_id);

        $response->assertRedirect(route('businesses.catalog.listings.edit', [$business, $listing]));

        $this->assertSame('public', $listing->visibility);
        $this->assertSame('draft', $listing->status);
        $this->assertNull($listing->published_version_id);

        $title = $listing->currentVersion->title;

        $this->get(route('public.businesses.show', ['business' => $business->slug]))
            ->assertOk()
            ->assertDontSee($title);

        $this->actingAs($owner)
            ->get(route('businesses.catalog.listings.edit', [$business, $listing]))
            ->assertOk()
            ->assertSee(__('business_listing.editor_help'))
            ->assertSee('owned');

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.publish', [$business, $listing]))
            ->assertRedirect(route('businesses.catalog.listings.edit', [$business, $listing]));

        $listing->refresh();

        $this->assertSame('active', $listing->status);
        $this->assertNotNull($listing->published_version_id);

        $this->get(route('public.businesses.show', ['business' => $business->slug]))
            ->assertOk()
            ->assertSee($title)
            ->assertSee(route('public.businesses.listings.show', [
                'business' => $business->slug,
                'listing' => $listing->uuid,
            ]), false);
    }

    public function test_promotion_route_still_rejects_a_portal_from_another_business(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $first = app(BusinessService::class)->create($owner->actor, [
            'name' => 'First Real Estate',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        $second = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Second Real Estate',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $portal = $second->publicIntakePortals()->sole();
        $contact = app(BusinessContactResolver::class)->resolve(
            $second,
            'Wrong Scope Seller',
            '09120000000',
            'promotion_route_scope_test',
        );

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $contact->id,
            'reference_code' => 'RE-WRONGSCOPE',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'Wrong Scope Seller',
            'phone' => '09120000000',
            'property_class' => 'residential',
            'price_unit' => 'toman',
            'status' => 'qualified',
            'preview_token_hash' => hash('sha256', 'wrong-scope'),
        ]);

        $this->actingAs($owner)
            ->post(route('businesses.real-estate.cases.promote', [$first, $portal, $case]))
            ->assertNotFound();

        $this->assertNull($case->fresh()->business_listing_id);
    }

    private function userWithActor(): User
    {
        $user = User::factory()->create();
        $user->actor()->create();

        return $user->refresh();
    }

    public function test_portal_manager_without_business_membership_does_not_see_dead_business_or_catalog_actions(): void
    {
        $owner = $this->userWithActor();
        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Private Office Workspace',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $portal = $business->publicIntakePortals()->sole();
        $contact = app(BusinessContactResolver::class)->resolve(
            $business,
            'Portal Client',
            '09123334444',
            'portal_manager_visibility_test',
        );

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $contact->id,
            'reference_code' => 'RE-PORTALMGR1',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'Portal Client',
            'phone' => '09123334444',
            'property_class' => 'residential',
            'price_unit' => 'toman',
            'status' => 'qualified',
            'preview_token_hash' => hash('sha256', 'portal-manager'),
        ]);

        $portalManager = $this->userWithActor();
        $this->publishSurfaces($portalManager, ['business']);

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->id,
            'user_id' => $portalManager->id,
            'role' => 'manager',
        ]);

        $response = $this->actingAs($portalManager)
            ->get(route('office.real-estate.show', ['portal' => $portal->uuid, 'case' => $case]))
            ->assertOk();

        $response
            ->assertSee(route('office.real-estate.status', ['portal' => $portal->uuid, 'case' => $case]), false)
            ->assertDontSee(route('businesses.show', $business), false)
            ->assertDontSee(route('businesses.catalog.index', $business), false)
            ->assertDontSee(route('businesses.real-estate.cases.promote', [$business, $portal, $case]), false);
    }
}
