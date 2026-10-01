<?php

namespace Tests\Feature\Surfaces;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinglePublicationAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_planning_release_profile_does_not_block_a_published_workflow(): void
    {
        config()->set('release.profile', 'planning_baseline');

        $user = User::factory()->create();
        $user->actor()->create();

        app(FeatureSurfaceGrantService::class)
            ->sync($user, ['business'], null);

        $this->actingAs($user)
            ->get(route('businesses.index'))
            ->assertOk();
    }

    public function test_superadmin_bypasses_publication_without_surface_grants(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        PlatformAccessGrant::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('businesses.index'))
            ->assertOk();
    }
}
