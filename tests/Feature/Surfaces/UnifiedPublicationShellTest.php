<?php

namespace Tests\Feature\Surfaces;

use App\Models\FeatureSurfaceGrant;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Services\Surfaces\UnifiedNavigation;
use App\Support\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UnifiedPublicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_sidebar_only_contains_published_facilities(): void
    {
        $user = User::factory()->create();

        FeatureSurfaceGrant::query()->create([
            'user_id' => $user->getKey(),
            'surface_key' => 'planner',
            'granted_at' => now(),
        ]);

        $items = collect(app(UnifiedNavigation::class)->for($user));

        $this->assertTrue($items->contains('key', 'planner'));
        $this->assertFalse($items->contains('key', 'groups'));
        $this->assertFalse($items->contains('key', 'business'));
    }

    public function test_superadmin_sidebar_contains_all_available_facilities_without_grants(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        PlatformAccessGrant::factory()->for($user)->create();

        $this->assertTrue(PlatformAdmin::check($user));

        $items = collect(app(UnifiedNavigation::class)->for($user));

        $this->assertTrue($items->contains('key', 'groups'));
        $this->assertTrue($items->contains('key', 'planner'));
        $this->assertTrue($items->contains('key', 'business'));
    }

    public function test_workspace_compatibility_url_returns_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('workspace.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_superadmin_does_not_bypass_domain_gate_denials(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        PlatformAccessGrant::factory()->for($user)->create();

        Gate::define('publication-root-test', fn (User $candidate) => false);

        $this->assertFalse(
            Gate::forUser($user)->allows('publication-root-test')
        );
    }
}
