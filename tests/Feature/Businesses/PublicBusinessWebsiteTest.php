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

        $response = $this->get(route('public.businesses.show', ['business' => $business->slug]));

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

        $this->get(route('public.businesses.show', ['business' => $business->slug]))->assertNotFound();
    }


    public function test_private_real_estate_business_does_not_expose_its_public_intake_channel(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک خصوصی',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $portal = $business->publicIntakePortals()->sole();

        $this->get(route('public.businesses.show', ['business' => $business->slug]))
            ->assertNotFound();

        $this->get(route('public.real-estate.show', $portal))
            ->assertNotFound();
    }

    public function test_public_real_estate_intake_uses_the_selected_locale_without_platform_chrome(): void
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

        foreach ([
            'en' => 'Have a property to offer, or are you looking for one?',
            'fa' => 'ملکی برای ارائه دارید یا به دنبال ملک هستید؟',
            'ar' => 'هل لديك عقار للعرض أم تبحث عن عقار؟',
            'zh_CN' => '您有房产要提供，还是正在寻找房产？',
        ] as $locale => $heading) {
            $this->withSession(['locale' => $locale])
                ->get(route('public.real-estate.show', $portal))
                ->assertOk()
                ->assertSee('مشاور املاک مهوری')
                ->assertSee($heading)
                ->assertDontSee(route('dashboard'), false)
                ->assertDontSee(route('businesses.index'), false)
                ->assertDontSee(route('planner.index'), false);
        }
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

        $this->get(route('public.businesses.show', ['business' => $business->slug]))
            ->assertOk()
            ->assertSee('Public item')
            ->assertDontSee('Private item');

        $this->get(route('public.businesses.listings.show', ['business' => $business->slug, 'listing' => $public->uuid]))
            ->assertOk()
            ->assertSee('Public item');

        $this->get(route('public.businesses.listings.show', ['business' => $business->slug, 'listing' => $private->uuid]))
            ->assertNotFound();
    }
}
