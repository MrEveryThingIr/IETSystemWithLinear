<?php

namespace Tests\Feature\Businesses;

use App\Models\PublicIntakePortal;
use App\Models\User;
use App\Services\Business\BusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class RealEstateBusinessFreshStartTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_new_real_estate_business_immediately_gets_the_legacy_complete_intake_channel(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک مهوری',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $portal = PublicIntakePortal::query()
            ->where('business_id', $business->id)
            ->where('type', 'real_estate')
            ->sole();

        $this->assertSame('مشاور املاک مهوری', $portal->title);
        $this->assertTrue($portal->is_active);
        $this->assertSame('real_estate', data_get($business->fresh()->settings, 'vertical.key'));
        $this->assertTrue((bool) data_get($business->fresh()->settings, 'vertical.simple_office_mode'));

        $this->assertEqualsCanonicalizing(
            ['properties', 'residential', 'commercial', 'office', 'industrial-warehouse', 'agricultural-garden', 'land'],
            $business->categories()->pluck('slug')->all(),
        );

        $this->withoutVite()
            ->get(route('public.real-estate.show', $portal))
            ->assertNotFound();

        $business->update(['visibility' => 'public']);

        $response = $this->withoutVite()->get(route('public.real-estate.show', $portal));
        $response->assertOk();

        foreach ($this->legacyVisibleFieldNames() as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $this->actingAs($owner)
            ->get(route('businesses.show', $business))
            ->assertOk()
            ->assertSee(__('business.show.real_estate.title'))
            ->assertSee('مشاور املاک مهوری')
            ->assertSee(route('office.real-estate.index', ['portal' => $portal->uuid]), false);
    }

    /** @return list<string> */
    private function legacyVisibleFieldNames(): array
    {
        return [
            'website',
            'intent',
            'transaction_mode',
            'contact_name',
            'phone',
            'property_class',
            'property_subtype',
            'exact_address',
            'public_area',
            'land_area',
            'construction_area',
            'bedrooms',
            'width',
            'length',
            'frontage_count',
            'built_year',
            'built_year_calendar',
            'building_age_years',
            'building_condition',
            'cabinet_type',
            'has_false_ceiling',
            'heating_system',
            'cooling_system',
            'flooring_type',
            'yard_finish',
            'has_parking',
            'parking_type',
            'parking_spaces',
            'car_capacity',
            'motorbike_capacity',
            'parking_note',
            'roof_finish',
            'roof_has_parapet',
            'has_western_toilet',
            'has_iranian_toilet',
            'price_unit',
            'asking_price',
            'deposit_amount',
            'monthly_rent_amount',
            'images[]',
            'videos[]',
            'audios[]',
            'recorded_audio',
            'recorded_video',
            'notes',
        ];
    }
}
