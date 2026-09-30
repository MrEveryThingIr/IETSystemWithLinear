<?php

namespace Tests\Feature\Contacts;

use App\Models\BusinessContact;
use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealEstateBusinessContactBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_submission_creates_an_anonymous_business_contact(): void
    {
        if (
            ! class_exists(PublicIntakePortal::class)
            || ! class_exists(PublicRealEstateCase::class)
        ) {
            $this->markTestSkipped('Real Estate intake is not installed.');
        }

        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $this->post(route('public.real-estate.store', $portal), [
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'رضا نمونه',
            'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
            'property_class' => 'residential',
            'building_age_years' => '۳۰',
            'price_unit' => 'toman',
        ])->assertRedirect();

        $case = PublicRealEstateCase::query()->sole();

        $this->assertNotNull($case->business_contact_id);

        $contact = BusinessContact::query()->findOrFail($case->business_contact_id);

        $this->assertSame('رضا نمونه', $contact->display_name);
        $this->assertSame('real_estate_public_intake', $contact->source);
    }
}
