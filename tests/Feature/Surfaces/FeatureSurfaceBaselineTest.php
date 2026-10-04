<?php

namespace Tests\Feature\Surfaces;

use App\Models\FeatureSurfaceGrant;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use App\Services\Surfaces\FeatureSurfaceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FeatureSurfaceBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_publication_includes_complete_dependencies(): void
    {
        $closure = app(FeatureSurfaceRegistry::class)
            ->dependencyClosure(['real-estate']);

        $this->assertEqualsCanonicalizing(
            ['profile', 'business', 'real-estate'],
            $closure
        );
    }

    public function test_deals_publication_includes_market_money_and_profile(): void
    {
        $closure = app(FeatureSurfaceRegistry::class)
            ->dependencyClosure(['deals']);

        $this->assertEqualsCanonicalizing(
            ['profile', 'market', 'money', 'deals'],
            $closure
        );
    }

    public function test_business_grant_sync_persists_dependency_closure(): void
    {
        $subject = User::factory()->create();
        $actor = User::factory()->create();

        $resolved = app(FeatureSurfaceGrantService::class)
            ->sync($subject, ['business'], $actor);

        $this->assertEqualsCanonicalizing(
            ['profile', 'business'],
            $resolved
        );

        $this->assertEqualsCanonicalizing(
            $resolved,
            FeatureSurfaceGrant::query()
                ->where('user_id', $subject->getKey())
                ->pluck('surface_key')
                ->all()
        );
    }

    public function test_real_estate_compatibility_surface_cannot_be_granted_directly(): void
    {
        $subject = User::factory()->create();
        $actor = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(FeatureSurfaceGrantService::class)
            ->sync($subject, ['real-estate'], $actor);
    }

    public function test_unpublished_profile_is_blocked_by_direct_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertForbidden();
    }

    public function test_published_profile_is_reachable(): void
    {
        $user = User::factory()->create();
        $user->actor()->create([]);

        app(FeatureSurfaceGrantService::class)
            ->sync($user, ['profile'], null);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_suspended_user_cannot_use_an_existing_surface_grant(): void
    {
        $user = User::factory()->suspended()->create();

        FeatureSurfaceGrant::query()->create([
            'user_id' => $user->getKey(),
            'surface_key' => 'business',
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('businesses.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_use_an_existing_surface_grant(): void
    {
        $user = User::factory()->unverified()->create();

        FeatureSurfaceGrant::query()->create([
            'user_id' => $user->getKey(),
            'surface_key' => 'business',
            'granted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('businesses.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_publication_admin_rejects_non_grantable_surfaces(): void
    {
        $admin = User::factory()->create();
        $subject = User::factory()->create();

        PlatformAccessGrant::factory()->for($admin)->create();

        $this->actingAs($admin)
            ->put(route('platform.publication.update', $subject), [
                'surfaces' => ['publication-control'],
            ])
            ->assertSessionHasErrors('surfaces.0');

        $this->assertDatabaseMissing('feature_surface_grants', [
            'user_id' => $subject->getKey(),
            'surface_key' => 'publication-control',
        ]);
    }

    public function test_domain_authorized_deep_links_are_not_surface_gated(): void
    {
        $registry = app(FeatureSurfaceRegistry::class);

        foreach ([
            'actors.avatar',
            'actors.profile.reference',
            'profiles.show',
            'profiles.images.show',
            'profiles.shares.show',
            'content-evidence.show',
            'contexts.contents.show',
            'contexts.contents.index',
            'contexts.contents.studio',
            'contexts.contents.assets.show',
            'groups.spaces.contents.studio',
            'contexts.conversation',
            'contexts.timeline',
            'contexts.submissions.show',
            'contexts.submissions.assets.download',
            'admissions.show',
            'admissions.context.contents',
            'admissions.context.conversation',
            'admissions.context.timeline',
            'platform.access',

            'relationships.show',
            'proposals.show',
            'contracts.show',
            'commitments.show',
            'financial-obligations.show',

            'planner.show',

            'groups.show',
            'groups.community',
            'groups.spaces.show',

        ] as $routeName) {
            $this->assertNull(
                $registry->routeSurface($routeName),
                "Route [{$routeName}] must remain domain-authorized rather than publication-gated."
            );
        }

        // Actual facility surfaces remain strict.
        $this->assertSame('profile', $registry->routeSurface('profile.edit'));
        $this->assertSame('content', $registry->routeSurface('contexts.personal'));
        $this->assertSame('content', $registry->routeSurface('contexts.submissions.index'));
        $this->assertSame('actors', $registry->routeSurface('actors.index'));
        $this->assertSame(
            'publication-control',
            $registry->routeSurface('platform.publication.index')
        );
        $this->assertSame('business', $registry->routeSurface('workspace.real-estate.index'));
        $this->assertSame('business', $registry->routeSurface('office.real-estate.index'));
        $this->assertSame('business', $registry->routeSurface('office.real-estate.show'));
        $this->assertSame('business', $registry->routeSurface('office.real-estate.media.stream'));
    }
}
