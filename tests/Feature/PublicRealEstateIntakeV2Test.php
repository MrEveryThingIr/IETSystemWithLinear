<?php

namespace Tests\Feature;

use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRealEstateIntakeV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_accepts_age_30_without_fake_construction_year(): void
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $this->post(route('public.real-estate.store', $portal), [
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'نمونه',
            'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
            'property_class' => 'residential',
            'building_age_years' => '۳۰',
            'price_unit' => 'toman',
        ])->assertRedirect();

        $case = PublicRealEstateCase::query()->sole();

        $this->assertSame(30, $case->building_age_years);
        $this->assertNull($case->built_year);
    }

    public function test_ungranted_authenticated_user_cannot_see_office_list(): void
    {
        $user = User::factory()->create();
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertForbidden();
    }

    public function test_granted_user_can_see_office_list(): void
    {
        $user = User::factory()->create();
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'viewer',
        ]);

        PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'reference_code' => 'RE-ABCDEFGHIJ',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'علی نمونه',
            'phone' => '09121234567',
            'property_class' => 'residential',
            'status' => 'new',
            'preview_token_hash' => hash('sha256', 'x'),
        ]);

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertOk()
            ->assertSee('RE-ABCDEFGHIJ')
            ->assertSee('علی نمونه');
    }
}
