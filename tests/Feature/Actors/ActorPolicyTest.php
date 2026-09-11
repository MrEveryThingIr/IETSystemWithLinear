<?php

namespace Tests\Feature\Actors;

use App\Models\Actor;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Policies\ActorPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ActorPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ordinary_user_has_no_actor_administration_abilities(): void
    {
        $user = User::factory()->create();
        $actor = Actor::factory()->withoutUser()->create();
        $policy = new ActorPolicy;

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $actor));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $actor));
        $this->assertFalse($policy->archive($user, $actor));
        $this->assertFalse($policy->delete($user, $actor));
    }

    public function test_active_verified_superadmin_can_view_create_and_archive_eligible_actor(): void
    {
        $user = User::factory()->superadmin()->create();
        $actor = Actor::factory()->withoutUser()->create();
        $policy = new ActorPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $actor));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->archive($user, $actor));
        $this->assertFalse($policy->update($user, $actor));
        $this->assertFalse($policy->delete($user, $actor));
    }

    public function test_revoked_grant_has_no_authority(): void
    {
        $user = User::factory()->create();
        $user->platformAccessGrants()->save(
            PlatformAccessGrant::factory()->revoked($user)->make(),
        );

        $this->assertFalse((new ActorPolicy)->viewAny($user));
    }

    public function test_superadmin_cannot_archive_active_user_identity_or_archived_actor(): void
    {
        $user = User::factory()->superadmin()->create();
        $activeUserActor = Actor::factory()->create();
        $archivedActor = Actor::factory()->withoutUser()->archived($user)->create();
        $policy = new ActorPolicy;

        $this->assertFalse($policy->archive($user, $activeUserActor));
        $this->assertFalse($policy->archive($user, $archivedActor));
    }
}
