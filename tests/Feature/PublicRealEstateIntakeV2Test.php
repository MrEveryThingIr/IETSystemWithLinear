<?php

namespace Tests\Feature;

use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use App\Services\Business\BusinessService;
use App\Services\Surfaces\FeatureSurfaceGrantService;
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


    public function test_one_time_preview_does_not_render_a_locale_action_that_would_reopen_consumed_preview(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک مهوری',
            'kind' => 'real_estate',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $portal = $business->publicIntakePortals()->sole();
        $token = 'preview-token';

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'reference_code' => 'RE-PREVIEW1234',
            'intent' => 'offer',
            'transaction_mode' => 'sale',
            'contact_name' => 'علی نمونه',
            'phone' => '09121234567',
            'property_class' => 'residential',
            'status' => 'new',
            'preview_token_hash' => hash('sha256', $token),
            'preview_expires_at' => now()->addMinutes(15),
        ]);

        $this->get(route('public.real-estate.preview', [
            'case' => $case,
            'token' => $token,
        ]))
            ->assertOk()
            ->assertSee(route('public.businesses.show', ['business' => $business->slug]), false)
            ->assertDontSee(route('locale.update'), false);

        $this->get(route('public.real-estate.preview', [
            'case' => $case,
            'token' => $token,
        ]))->assertNotFound();
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

    public function test_published_and_portal_granted_user_can_see_office_list(): void
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

        app(FeatureSurfaceGrantService::class)->sync($user, ['business'], null);

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
