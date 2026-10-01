<?php

namespace Tests\Feature\Surfaces;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformCapability;
use App\Services\Access\SuperAdminBootstrapper;
use App\Support\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class SuperAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_can_become_canonical_bootstrap_superadmin(): void
    {
        $user = User::factory()->create(['email' => 'root@example.test']);

        $admin = app(SuperAdminBootstrapper::class)->ensure('root@example.test');

        $this->assertSame($user->getKey(), $admin->getKey());
        $this->assertDatabaseHas('platform_access_grants', [
            'user_id' => $user->getKey(),
            'role' => 'superadmin',
            'revoked_at' => null,
        ]);
        $this->assertTrue(PlatformAdmin::check($user));
    }

    public function test_initialization_refuses_zero_superadmins_without_configured_existing_user(): void
    {
        config()->set('iet_bootstrap.superadmin_identifier', null);

        $this->expectException(RuntimeException::class);

        app(SuperAdminBootstrapper::class)->ensure();
    }

    public function test_last_canonical_superadmin_cannot_be_revoked(): void
    {
        $user = User::factory()->create(['email' => 'root@example.test']);

        $service = app(SuperAdminBootstrapper::class);
        $service->ensure('root@example.test');

        $this->expectException(RuntimeException::class);

        $service->revoke($user);
    }

    public function test_suspended_canonical_superadmin_has_no_platform_authority(): void
    {
        $user = User::factory()->suspended()->create();

        PlatformAccessGrant::factory()->for($user)->create();

        $this->assertFalse(PlatformAdmin::check($user));
        $this->assertFalse($user->hasPlatformCapability(PlatformCapability::ManageUsers));
    }

    public function test_unverified_user_cannot_be_bootstrapped_as_superadmin(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified-root@example.test',
        ]);

        $this->expectException(ValidationException::class);

        app(SuperAdminBootstrapper::class)->ensure($user->email);
    }
}
