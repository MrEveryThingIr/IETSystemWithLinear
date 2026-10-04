<?php

namespace Tests\Feature\Surfaces;

use App\Models\PlatformAccessGrant;
use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\User;
use App\Services\Business\AdoptRealEstatePortalIntoBusiness;
use App\Services\Surfaces\ExperienceNavigation;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRealEstatePublicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_grant_does_not_bypass_business_publication(): void
    {
        $user = User::factory()->create();
        $portal = $this->portal('دفتر نمونه');

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'viewer',
        ]);

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertForbidden();
    }

    public function test_business_published_user_with_portal_access_can_use_real_estate_office(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();
        $portal = $this->portal('دفتر نمونه');

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'viewer',
        ]);

        app(FeatureSurfaceGrantService::class)->sync($user, ['business'], null);

        $this->actingAs($user)
            ->get(route('workspace.real-estate.index'))
            ->assertRedirect(route('businesses.index', ['kind' => 'real_estate']));

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertOk();
    }

    public function test_real_estate_is_inside_businesses_not_a_primary_destination(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        app(FeatureSurfaceGrantService::class)->sync($user, ['business'], null);

        $navigation = app(ExperienceNavigation::class)->for($user);
        $primary = collect($navigation['primary']);

        $this->assertNotContains('real-estate', $primary->pluck('key')->all());

        $organizations = $primary->firstWhere('key', 'organizations');
        $this->assertNotNull($organizations);
        $this->assertContains('business', collect($organizations['items'])->pluck('key')->all());
        $this->assertNotContains('real-estate', collect($organizations['items'])->pluck('key')->all());
    }

    public function test_real_estate_office_is_presented_through_its_business_identity(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $user->actor()->create();
        $portal = $this->portal('مشاور املاک مهوری');

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'manager',
        ]);

        app(FeatureSurfaceGrantService::class)->sync($user, ['business'], null);
        $business = app(AdoptRealEstatePortalIntoBusiness::class)->execute($portal, $user);

        $this->assertSame('real_estate', $business->kind);
        $this->assertSame('مشاور املاک مهوری', $business->name);

        $this->actingAs($user)
            ->get(route('businesses.index', ['kind' => 'real_estate']))
            ->assertOk()
            ->assertSee('مشاور املاک مهوری');

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertOk()
            ->assertSee('Business: مشاور املاک مهوری');
    }

    public function test_publication_control_is_hidden_and_forbidden_for_ordinary_users(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        app(FeatureSurfaceGrantService::class)->sync($user, ['business'], null);

        $navigation = app(ExperienceNavigation::class)->for($user);

        $this->assertNotContains(
            'publication-control',
            collect($navigation['admin'])->pluck('key')->all()
        );

        $this->actingAs($user)
            ->get(route('platform.publication.index'))
            ->assertForbidden();
    }

    public function test_superadmin_sees_businesses_without_a_standalone_real_estate_destination(): void
    {
        $admin = User::factory()->create();
        $admin->actor()->create();
        PlatformAccessGrant::factory()->for($admin)->create();

        $navigation = app(ExperienceNavigation::class)->for($admin);
        $primary = collect($navigation['primary']);

        $this->assertContains(
            'publication-control',
            collect($navigation['admin'])->pluck('key')->all()
        );
        $this->assertNotContains('real-estate', $primary->pluck('key')->all());

        $organizations = $primary->firstWhere('key', 'organizations');
        $this->assertNotNull($organizations);
        $this->assertContains('business', collect($organizations['items'])->pluck('key')->all());

        $this->actingAs($admin)
            ->get(route('platform.publication.index'))
            ->assertOk();
    }

    public function test_active_public_intake_link_remains_public_by_token(): void
    {
        $portal = $this->portal('دفتر نمونه');

        $this->get(route('public.real-estate.show', $portal))
            ->assertOk()
            ->assertSee('دفتر نمونه');
    }

    private function portal(string $title): PublicIntakePortal
    {
        return PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => $title,
            'locale' => 'fa',
            'is_active' => true,
        ]);
    }
}
