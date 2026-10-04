<?php

namespace Tests\Feature\Surfaces;

use App\Models\PlatformAccessGrant;
use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\User;
use App\Services\Surfaces\ExperienceNavigation;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRealEstatePublicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_grant_does_not_bypass_surface_publication(): void
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

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertForbidden();
    }

    public function test_published_user_with_portal_access_can_use_real_estate_workspace_and_office(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

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

        app(FeatureSurfaceGrantService::class)
            ->sync($user, ['real-estate'], null);

        $this->actingAs($user)
            ->get(route('workspace.real-estate.index'))
            ->assertOk()
            ->assertSee('دفتر نمونه');

        $this->actingAs($user)
            ->get(route('office.real-estate.index', ['portal' => $portal->uuid]))
            ->assertOk();
    }

    public function test_real_estate_is_a_direct_destination_not_nested_under_business(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        app(FeatureSurfaceGrantService::class)
            ->sync($user, ['real-estate'], null);

        $navigation = app(ExperienceNavigation::class)->for($user);

        $this->assertContains(
            'real-estate',
            collect($navigation['primary'])->pluck('key')->all()
        );

        $organizations = collect($navigation['primary'])->firstWhere('key', 'organizations');
        if ($organizations !== null) {
            $this->assertNotContains(
                'real-estate',
                collect($organizations['items'])->pluck('key')->all()
            );
        }
    }

    public function test_real_estate_manager_cannot_adopt_into_unpublished_business(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        PublicIntakePortalGrant::query()->create([
            'public_intake_portal_id' => $portal->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'manager',
        ]);

        app(FeatureSurfaceGrantService::class)
            ->sync($user, ['real-estate'], null);

        $this->actingAs($user)
            ->post(route('office.real-estate.adopt-business', ['portal' => $portal->uuid]))
            ->assertForbidden();

        $this->assertNull($portal->fresh()->business_id);
    }

    public function test_publication_control_is_hidden_and_forbidden_for_ordinary_users(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        app(FeatureSurfaceGrantService::class)
            ->sync($user, ['real-estate'], null);

        $navigation = app(ExperienceNavigation::class)->for($user);

        $this->assertNotContains(
            'publication-control',
            collect($navigation['admin'])->pluck('key')->all()
        );

        $this->actingAs($user)
            ->get(route('platform.publication.index'))
            ->assertForbidden();
    }

    public function test_superadmin_keeps_publication_control_and_real_estate_access_without_grants(): void
    {
        $admin = User::factory()->create();
        $admin->actor()->create();
        PlatformAccessGrant::factory()->for($admin)->create();

        $navigation = app(ExperienceNavigation::class)->for($admin);

        $this->assertContains(
            'publication-control',
            collect($navigation['admin'])->pluck('key')->all()
        );
        $this->assertContains(
            'real-estate',
            collect($navigation['primary'])->pluck('key')->all()
        );

        $this->actingAs($admin)
            ->get(route('platform.publication.index'))
            ->assertOk();
    }

    public function test_active_public_intake_link_remains_public_by_token(): void
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $this->get(route('public.real-estate.show', $portal))
            ->assertOk()
            ->assertSee('دفتر نمونه');
    }
}
