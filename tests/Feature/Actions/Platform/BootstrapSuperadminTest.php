<?php

namespace Tests\Feature\Actions\Platform;

use App\Actions\Platform\BootstrapSuperadmin;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformCapability;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapSuperadminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_active_user_becomes_verified_superadmin_with_primary_actor(): void
    {
        $user = User::factory()->unverified()->create();

        $grant = app(BootstrapSuperadmin::class)->execute($user->email);

        $this->assertTrue($grant->user->is($user));
        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertNotNull($user->actor);
        $this->assertTrue($user->hasPlatformCapability(PlatformCapability::ManageActors));
    }

    public function test_missing_user_is_created_with_hashed_password_and_primary_actor(): void
    {
        $grant = app(BootstrapSuperadmin::class)->execute(
            'owner@example.test',
            'platform_owner',
            'correct horse battery staple',
        );

        $user = $grant->user;
        $this->assertSame('owner@example.test', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->actor);
        $this->assertTrue(Hash::check('correct horse battery staple', $user->password));
    }

    public function test_second_bootstrap_is_rejected_without_creating_another_grant(): void
    {
        PlatformAccessGrant::factory()->create();
        $candidate = User::factory()->create();

        try {
            app(BootstrapSuperadmin::class)->execute($candidate->email);
            $this->fail('A second bootstrap unexpectedly succeeded.');
        } catch (DomainException $exception) {
            $this->assertSame('An active platform administrator already exists.', $exception->getMessage());
        }

        $this->assertDatabaseCount('platform_access_grants', 1);
        $this->assertNull($candidate->actor);
    }

    public function test_inactive_user_cannot_be_bootstrapped(): void
    {
        $user = User::factory()->suspended()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The selected account must be active.');

        app(BootstrapSuperadmin::class)->execute($user->email);
    }
}
