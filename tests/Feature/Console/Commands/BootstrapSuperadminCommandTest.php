<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BootstrapSuperadminCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_bootstraps_existing_user_without_prompts(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);

        $this->artisan('platform:bootstrap-superadmin', ['email' => $user->email])
            ->expectsOutputToContain('Platform Superadmin granted')
            ->assertSuccessful();

        $this->assertNotNull($user->refresh()->actor);
        $this->assertDatabaseHas('platform_access_grants', ['user_id' => $user->id, 'revoked_at' => null]);
    }

    public function test_command_collects_credentials_when_creating_first_user(): void
    {
        $this->artisan('platform:bootstrap-superadmin', ['email' => 'new-owner@example.test'])
            ->expectsQuestion('Username for the new administrator', 'new_owner')
            ->expectsQuestion('Password for the new administrator', 'a secure bootstrap password')
            ->assertSuccessful();

        $user = User::query()->where('email', 'new-owner@example.test')->firstOrFail();
        $this->assertNotNull($user->actor);
        $this->assertDatabaseHas('platform_access_grants', ['user_id' => $user->id, 'revoked_at' => null]);
    }

    public function test_command_refuses_to_replace_existing_superadmin(): void
    {
        User::factory()->superadmin()->create();
        $candidate = User::factory()->create();

        $this->artisan('platform:bootstrap-superadmin', ['email' => $candidate->email])
            ->expectsOutputToContain('An active platform administrator already exists.')
            ->assertFailed();

        $this->assertDatabaseCount('platform_access_grants', 1);
    }
}
