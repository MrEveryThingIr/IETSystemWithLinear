<?php

namespace Tests\Feature\Businesses;

use App\ContextKind;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Models\BusinessContact;
use App\Models\BusinessPriceVersion;
use App\Models\MonetaryUnit;
use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use App\Services\Business\AdoptRealEstatePortalIntoBusiness;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessService;
use App\Services\Contacts\BusinessContactResolver;
use Database\Seeders\RealEstateBusinessDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BusinessVerticalConsolidationTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_business_gets_shared_context_and_iet_internal_settlement_policy(): void
    {
        $owner = $this->userWithActor();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Unified Services',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $binding = $business->contextBinding()->with('context')->sole();

        $this->assertSame(ContextKind::Business, $binding->context->kind);
        $this->assertTrue(Gate::forUser($owner)->allows('interactContent', $binding->context));
        $this->assertSame('IET', $business->defaultMonetaryUnit()->sole()->code);
        $this->assertSame('IET', data_get($business->settings, 'finance.internal_settlement_unit'));
        $this->assertSame('placeholder', data_get($business->settings, 'finance.external_money_gateways'));
    }

    public function test_business_routine_uses_business_context_and_defaults_expenses_to_iet(): void
    {
        $owner = $this->userWithActor();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Routine Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $context = $business->contextBinding()->with('context')->sole()->context;

        Livewire::actingAs($owner)
            ->withQueryParams(['context' => $context->uuid])
            ->test(PlannerCreate::class)
            ->assertSet('contextUuid', $context->uuid)
            ->call('addExpenseEstimate')
            ->assertSet('expenseEstimates.0.unit_code', 'IET')
            ->set('expenseEstimates.0.label', 'Routine material')
            ->set('expenseEstimates.0.amount', '25')
            ->set('title', 'Daily business routine')
            ->set('frequency', 'once')
            ->set('startsOn', now()->addDay()->toDateString())
            ->set('startTime', '12:00')
            ->set('durationMinutes', 60)
            ->call('save')
            ->assertHasNoErrors();

        $plan = $context->plans()->sole();

        $this->assertSame('business', $plan->origin_type);
        $this->assertSame($business->uuid, $plan->origin_uuid);
        $this->assertSame($context->id, $plan->context_id);
        $this->assertSame('IET', $plan->expenseEstimates()->with('monetaryUnit')->sole()->monetaryUnit->code);
    }

    public function test_business_crm_accepts_unregistered_real_world_client(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'ABC Electrical',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('businesses.clients.store', $business), [
                'display_name' => 'Walk-in Customer',
                'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
                'secondary_phone' => '09120000000',
                'source' => 'walk-in',
                'notes' => 'Needs an electrical inspection.',
                'city' => 'Tehran',
            ])
            ->assertRedirect();

        $contact = BusinessContact::query()->sole();

        $this->assertSame($business->getMorphClass(), $contact->owner_type);
        $this->assertSame($business->id, $contact->owner_id);
        $this->assertNull($contact->claimed_by_id);
        $this->assertCount(2, $contact->contactPoints);
    }

    public function test_business_crm_defaults_missing_source_to_manual(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Source Default Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('businesses.clients.store', $business), [
                'display_name' => 'No Source Client',
                'phone' => '09121111111',
            ])
            ->assertRedirect();

        $this->assertSame('manual', BusinessContact::query()->sole()->source);
    }

    public function test_public_business_does_not_expose_operating_context_crm_or_catalog_to_strangers(): void
    {
        $owner = $this->userWithActor();
        $stranger = $this->userWithActor();
        $this->publishSurfaces($stranger, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Public Identity, Private Operations',
            'kind' => 'services',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $context = $business->contextBinding()->with('context')->sole()->context;

        $this->assertFalse(Gate::forUser($stranger)->allows('view', $context));

        $this->actingAs($stranger)
            ->get(route('businesses.show', $business))
            ->assertOk()
            ->assertDontSee('مشتری / مخاطب')
            ->assertDontSee('برنامه‌های کسب‌وکار');

        $this->actingAs($stranger)
            ->get(route('businesses.clients.index', $business))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('businesses.catalog.index', $business))
            ->assertForbidden();
    }

    public function test_business_catalog_price_entry_uses_exact_minor_units(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Priced Catalog',
            'kind' => 'retail',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $listing = app(BusinessCatalogService::class)->createListing(
            $business,
            $owner->actor,
            'good',
            ['title' => 'Precision Item'],
        );

        $this->actingAs($owner)
            ->post(route('businesses.catalog.listings.prices.store', [$business, $listing]), [
                'unit_code' => 'USD',
                'price_type' => 'retail',
                'amount' => '12.34',
                'basis' => 'per item',
                'visibility' => 'public',
                'reason' => 'Initial price',
            ])
            ->assertRedirect();

        $price = BusinessPriceVersion::query()->sole();

        $this->assertSame(1234, $price->amount_minor);
        $this->assertSame('USD', $price->monetaryUnit->code);
        $this->assertSame('per item', $price->basis);
        $this->assertSame('Initial price', $price->reason);
    }

    public function test_listing_publish_freezes_version_and_price_history_is_separate(): void
    {
        $owner = $this->userWithActor();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Catalog Business',
            'kind' => 'retail',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $catalog = app(BusinessCatalogService::class);
        $category = $catalog->ensureCategory($business, 'Shirts', 'shirts');

        $listing = $catalog->createListing(
            $business,
            $owner->actor,
            'good',
            ['title' => 'Classic Shirt', 'description' => 'Initial description'],
            $category,
        );

        $version = $listing->currentVersion()->sole();

        $catalog->addPrice(
            $listing,
            $owner->actor,
            'IET',
            'retail',
            1200,
            $version,
            basis: 'per item',
            visibility: 'public',
        );

        $catalog->publish($listing, $version, $owner->actor);

        $this->assertNotNull($version->fresh()->published_at);
        $this->assertSame('active', $listing->fresh()->status);
        $this->assertDatabaseCount('business_price_versions', 1);

        $this->expectException(LogicException::class);

        $version->fresh()->update(['title' => 'Silently changed']);
    }

    public function test_existing_real_estate_portal_is_adopted_without_changing_urls_or_cases(): void
    {
        $owner = $this->userWithActor();

        $portal = PublicIntakePortal::query()->create([
            'uuid' => RealEstateBusinessDemoSeeder::PORTAL_UUID,
            'public_token' => RealEstateBusinessDemoSeeder::PUBLIC_TOKEN,
            'type' => 'real_estate',
            'title' => 'Legacy Office',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->id,
            'user_id' => $owner->id,
            'role' => 'manager',
        ]);

        $contact = app(BusinessContactResolver::class)->resolve(
            $portal,
            'Legacy Client',
            '09121234567',
            'legacy',
        );

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $contact->id,
            'reference_code' => 'RE-ABCDEFGHIJ',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'Legacy Client',
            'phone' => '09121234567',
            'property_class' => 'residential',
            'price_unit' => 'toman',
            'status' => 'new',
            'preview_token_hash' => hash('sha256', 'preview'),
        ]);

        $oldAdminUrl = route('office.real-estate.index', ['portal' => $portal->uuid]);
        $oldPublicUrl = route('public.real-estate.show', $portal);

        $business = app(AdoptRealEstatePortalIntoBusiness::class)->execute($portal, $owner);

        $portal->refresh();
        $contact->refresh();
        $case->refresh();

        $this->assertSame('real_estate', $business->kind);
        $this->assertSame($business->id, $portal->business_id);
        $this->assertSame(RealEstateBusinessDemoSeeder::PORTAL_UUID, $portal->uuid);
        $this->assertSame(RealEstateBusinessDemoSeeder::PUBLIC_TOKEN, $portal->public_token);
        $this->assertSame($oldAdminUrl, route('office.real-estate.index', ['portal' => $portal->uuid]));
        $this->assertSame($oldPublicUrl, route('public.real-estate.show', $portal));
        $this->assertSame($case->id, PublicRealEstateCase::query()->sole()->id);
        $this->assertSame($business->getMorphClass(), $contact->owner_type);
        $this->assertSame($business->id, $contact->owner_id);
    }

    public function test_reviewed_property_offer_promotes_into_generic_listing_with_structured_details_and_price_history(): void
    {
        $owner = $this->userWithActor();

        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'Office',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->id,
            'user_id' => $owner->id,
            'role' => 'manager',
        ]);

        $business = app(AdoptRealEstatePortalIntoBusiness::class)->execute($portal, $owner);

        $contact = app(BusinessContactResolver::class)->resolve(
            $business,
            'Ahmad',
            '09121234567',
            'test',
        );

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->id,
            'business_contact_id' => $contact->id,
            'reference_code' => 'RE-ABCDEFGHIJ',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'Ahmad',
            'phone' => '09121234567',
            'public_area' => 'Example Area',
            'property_class' => 'residential',
            'property_subtype' => 'villa',
            'land_area' => 240,
            'construction_area' => 180,
            'bedrooms' => 4,
            'has_parking' => true,
            'asking_price' => 8000000000,
            'price_unit' => 'toman',
            'status' => 'qualified',
            'preview_token_hash' => hash('sha256', 'preview'),
        ]);

        $listing = app(BusinessCatalogService::class)
            ->promoteRealEstateOffer($business, $case, $owner->actor);

        $this->assertSame('property', $listing->listing_type);
        $this->assertSame($listing->id, $case->fresh()->business_listing_id);
        $this->assertSame('villa', $listing->currentVersion->propertyDetails->property_subtype);
        $this->assertSame('180.00', $listing->currentVersion->propertyDetails->construction_area);

        $price = BusinessPriceVersion::query()->sole();
        $unit = MonetaryUnit::query()->findOrFail($price->monetary_unit_id);

        $this->assertSame('IRR', $unit->code);
        $this->assertSame(80000000000, $price->amount_minor);
        $this->assertSame('asking_sale', $price->price_type);
    }

    public function test_local_demo_seeder_preserves_requested_office_urls_and_bootstraps_business_vertical(): void
    {
        $this->seed(RealEstateBusinessDemoSeeder::class);

        $portal = PublicIntakePortal::query()
            ->where('uuid', RealEstateBusinessDemoSeeder::PORTAL_UUID)
            ->sole();

        $this->assertSame(RealEstateBusinessDemoSeeder::PUBLIC_TOKEN, $portal->public_token);
        $this->assertNotNull($portal->business_id);
        $this->assertSame('real_estate', $portal->business->kind);
        $this->assertDatabaseCount('public_real_estate_cases', 2);
        $this->assertDatabaseCount('business_listings', 1);
        $this->assertStringContainsString(
            '/office-admin/'.RealEstateBusinessDemoSeeder::PORTAL_UUID.'/cases',
            route('office.real-estate.index', ['portal' => $portal->uuid]),
        );
        $this->assertStringContainsString(
            '/office/'.RealEstateBusinessDemoSeeder::PUBLIC_TOKEN,
            route('public.real-estate.show', $portal),
        );
    }

    private function userWithActor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->actor()->create();

        return $user->refresh();
    }
}
