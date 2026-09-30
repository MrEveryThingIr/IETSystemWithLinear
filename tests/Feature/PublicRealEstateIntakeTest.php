<?php

namespace Tests\Feature;

use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRealEstateIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_visitor_can_open_only_an_active_opaque_portal(): void
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر املاک نمونه',
            'welcome_heading' => 'ثبت ملک و درخواست',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $this->get(route('public.real-estate.show', $portal))
            ->assertOk()
            ->assertSee('ثبت ملک و درخواست')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_inactive_portal_is_not_publicly_available(): void
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر املاک نمونه',
            'locale' => 'fa',
            'is_active' => false,
        ]);

        $this->get(route('public.real-estate.show', $portal))->assertNotFound();
    }

    public function test_anonymous_visitor_can_submit_a_case_and_get_a_one_time_preview(): void
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر املاک نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $response = $this->post(route('public.real-estate.store', $portal), [
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'علی نمونه',
            'phone' => '09121234567',
            'property_class' => 'residential',
            'property_subtype' => 'villa',
            'land_area' => '240',
            'construction_area' => '160',
            'width' => '8',
            'length' => '30',
            'frontage_count' => '2',
            'built_year' => '1398',
            'built_year_calendar' => 'jalali',
            'building_condition' => 'good',
            'bedrooms' => '4',
            'has_parking' => '1',
            'parking_spaces' => '1',
            'asking_price' => '9500000000',
            'price_unit' => 'toman',
        ]);

        $response->assertRedirect();

        $case = PublicRealEstateCase::query()->sole();

        $this->assertSame('new', $case->status);
        $this->assertSame('09121234567', $case->phone);
        $this->assertNotNull($case->preview_token_hash);
        $this->assertNull($case->preview_viewed_at);

        $location = $response->headers->get('Location');

        $this->get($location)
            ->assertOk()
            ->assertSee($case->reference_code);

        $case->refresh();
        $this->assertNotNull($case->preview_viewed_at);

        $this->get($location)->assertNotFound();
    }

    public function test_persian_digits_are_normalized_for_numeric_inputs(): void
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر املاک نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $this->post(route('public.real-estate.store', $portal), [
            'intent' => 'offer',
            'transaction_mode' => 'rent',
            'contact_name' => 'رضا نمونه',
            'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
            'property_class' => 'residential',
            'land_area' => '۲۴۰',
            'built_year' => '۱۴۰۱',
            'built_year_calendar' => 'jalali',
            'deposit_amount' => '۱٬۰۰۰٬۰۰۰٬۰۰۰',
            'monthly_rent_amount' => '۲۰٬۰۰۰٬۰۰۰',
            'price_unit' => 'toman',
        ])->assertRedirect();

        $case = PublicRealEstateCase::query()->sole();

        $this->assertSame('09121234567', $case->phone);
        $this->assertSame('240.00', $case->land_area);
        $this->assertSame('1000000000', $case->deposit_amount);
        $this->assertSame('20000000', $case->monthly_rent_amount);
    }
}
