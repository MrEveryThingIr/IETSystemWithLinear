<?php

namespace Tests\Feature\Businesses;

use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
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
            ->assertSee(route('public.businesses.real-estate.show', ['business' => $business->slug]), false)
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
    public function test_every_public_business_exposes_its_standalone_site_prominently_from_the_operator_workspace(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        $owner->actor()->create();
        app(FeatureSurfaceGrantService::class)->sync($owner, ['business'], null);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Mehvari Workshop',
            'kind' => 'services',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $publicUrl = route('public.businesses.show', ['business' => $business->slug]);

        $this->actingAs($owner)
            ->get(route('businesses.show', $business))
            ->assertOk()
            ->assertSee($publicUrl, false)
            ->assertSee(__('business.public_site.open'));

        $this->get($publicUrl)
            ->assertOk()
            ->assertSee('Mehvari Workshop')
            ->assertDontSee(__('public_business.business'))
            ->assertDontSee('Everything for Everyone');
    }

    public function test_private_business_workspace_never_renders_a_dead_public_website_link(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        $owner->actor()->create();
        app(FeatureSurfaceGrantService::class)->sync($owner, ['business'], null);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Private Workshop',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $publicUrl = route('public.businesses.show', ['business' => $business->slug]);

        $this->actingAs($owner)
            ->get(route('businesses.show', $business))
            ->assertOk()
            ->assertDontSee('href="'.$publicUrl.'"', false)
            ->assertSee(__('business.public_site.not_live'));
    }

    public function test_business_can_explicitly_advertise_other_public_businesses_without_exposing_private_ones(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        $owner->actor()->create();
        app(FeatureSurfaceGrantService::class)->sync($owner, ['business'], null);

        $host = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Host Business',
            'kind' => 'services',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $recommended = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Recommended Business',
            'kind' => 'retail',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $private = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Private Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->put(route('businesses.public-site.update', $host), [
                'featured_business_ids' => [$recommended->id, $private->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$recommended->id],
            data_get($host->fresh()->settings, 'public_site.featured_business_ids'),
        );

        $this->get(route('public.businesses.show', ['business' => $host->slug]))
            ->assertOk()
            ->assertSee('Recommended Business')
            ->assertSee(route('public.businesses.show', ['business' => $recommended->slug]), false)
            ->assertDontSee('Private Business');
    }

    public function test_business_scoped_real_estate_intake_is_the_canonical_public_path_while_legacy_token_url_still_works(): void
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

        $this->get(route('public.businesses.real-estate.show', ['business' => $business->slug]))
            ->assertOk()
            ->assertSee('مشاور املاک مهوری')
            ->assertSee(route('public.businesses.real-estate.store', ['business' => $business->slug]), false);

        $this->get(route('public.real-estate.show', $portal))
            ->assertOk()
            ->assertSee('مشاور املاک مهوری');
    }

}
