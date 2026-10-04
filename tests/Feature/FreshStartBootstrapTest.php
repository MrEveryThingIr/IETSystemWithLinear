<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Support\PlatformAdmin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreshStartBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seed_creates_only_the_named_bootstrap_account_and_no_business_demo(): void
    {
        config()->set('bootstrap.demo_data', false);
        config()->set('bootstrap.superadmin.username', 'MrEveryThing');
        config()->set('bootstrap.superadmin.email', 'mreverything@example.test');
        config()->set('bootstrap.superadmin.password', 'password');

        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('username', 'MrEveryThing')->sole();

        $this->assertSame('mreverything@example.test', $user->email);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->actor);
        $this->assertSame('MrEveryThing', $user->actor->profile?->display_name);
        $this->assertTrue(PlatformAdmin::check($user));

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(0, Business::query()->count());
        $this->assertDatabaseCount('access_invitations', 0);
    }
}
