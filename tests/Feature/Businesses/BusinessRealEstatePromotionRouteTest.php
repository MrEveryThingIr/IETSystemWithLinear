<?php

namespace Tests\Feature\Businesses;

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

        $this->actingAs($owner)
            ->post(route('businesses.real-estate.cases.promote', [$business, $portal, $case]))
            ->assertRedirect(route('businesses.catalog.index', $business));

        $case->refresh();

        $this->assertNotNull($case->business_listing_id);
        $this->assertDatabaseHas('business_listings', [
            'id' => $case->business_listing_id,
            'business_id' => $business->id,
            'listing_type' => 'property',
        ]);
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
}
