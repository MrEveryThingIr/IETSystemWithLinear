<?php

namespace Tests\Feature;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionBootstrapSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_the_first_superadmin_and_is_idempotent(): void
    {
        config()->set('bootstrap.superadmin.password', 'A-strong-production-password-123!');

        $exit = Artisan::call('iet:bootstrap-superadmin', [
            '--username' => 'ReleaseAdmin',
            '--email' => 'release-admin@example.com',
        ]);

        $this->assertSame(0, $exit);

        $user = User::query()
            ->where('username', 'ReleaseAdmin')
            ->where('email', 'release-admin@example.com')
            ->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->actor);
        $this->assertSame('ReleaseAdmin', $user->actor->profile?->display_name);

        $this->assertDatabaseHas('platform_access_grants', [
            'user_id' => $user->id,
            'role' => PlatformRole::Superadmin->value,
            'revoked_at' => null,
        ]);

        $this->assertSame(0, Artisan::call('iet:bootstrap-superadmin', [
            '--username' => 'AnotherAdmin',
            '--email' => 'another@example.com',
        ]));

        $this->assertSame(1, PlatformAccessGrant::query()
            ->active()
            ->where('role', PlatformRole::Superadmin->value)
            ->count());
    }

    public function test_command_refuses_placeholder_password_in_non_interactive_mode(): void
    {
        config()->set('bootstrap.superadmin.password', 'password');

        $exit = Artisan::call('iet:bootstrap-superadmin', [
            '--username' => 'ReleaseAdmin',
            '--email' => 'release-admin@example.com',
        ]);

        $this->assertSame(1, $exit);
        $this->assertDatabaseCount('users', 0);
    }
}
