<?php

namespace Tests\Feature\Actions\Platform;

use App\Actions\Platform\RequestPlatformAccess;
use App\Actions\Platform\ReviewPlatformAccessRequest;
use App\Models\PlatformAccessGrant;
use App\Models\PlatformAccessRequest;
use App\Models\User;
use App\PlatformCapability;
use App\PlatformRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformAccessRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_duplicate_pending_group_creator_request_is_idempotent(): void
    {
        $user = User::factory()->create();
        $requestAccess = app(RequestPlatformAccess::class);

        $first = $requestAccess->execute($user, PlatformRole::GroupCreator, 'I need to create a project group.');
        $second = $requestAccess->execute($user, PlatformRole::GroupCreator, 'A repeated submission.');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('platform_access_requests', 1);
        $this->assertSame("{$user->id}:group_creator", $first->pending_key);
        $this->assertFalse($user->hasPlatformCapability(PlatformCapability::CreateGroups));
    }

    public function test_approval_grants_only_group_creation_capability(): void
    {
        $user = User::factory()->create();
        $administrator = User::factory()->create();
        PlatformAccessGrant::factory()->for($administrator)->create();

        $request = app(RequestPlatformAccess::class)->execute($user, PlatformRole::GroupCreator);
        $reviewed = app(ReviewPlatformAccessRequest::class)->execute($request, $administrator, true, 'Approved for project coordination.');

        $this->assertSame('approved', $reviewed->status);
        $this->assertNull($reviewed->pending_key);
        $this->assertSame($administrator->id, $reviewed->reviewed_by_user_id);
        $this->assertSame('Approved for project coordination.', $reviewed->review_note);
        $this->assertTrue($user->hasPlatformCapability(PlatformCapability::CreateGroups));
        $this->assertFalse($user->hasPlatformCapability(PlatformCapability::ManageActors));
        $this->assertDatabaseHas('platform_access_grants', [
            'user_id' => $user->id,
            'role' => PlatformRole::GroupCreator->value,
            'granted_by_user_id' => $administrator->id,
            'revoked_at' => null,
        ]);
    }

    public function test_rejection_does_not_grant_access(): void
    {
        $user = User::factory()->create();
        $administrator = User::factory()->create();
        PlatformAccessGrant::factory()->for($administrator)->create();

        $request = app(RequestPlatformAccess::class)->execute($user, PlatformRole::GroupCreator);
        $reviewed = app(ReviewPlatformAccessRequest::class)->execute($request, $administrator, false, 'Not needed yet.');

        $this->assertSame('rejected', $reviewed->status);
        $this->assertFalse($user->hasPlatformCapability(PlatformCapability::CreateGroups));
        $this->assertDatabaseMissing('platform_access_grants', [
            'user_id' => $user->id,
            'role' => PlatformRole::GroupCreator->value,
        ]);
    }

    public function test_superadmin_role_cannot_be_self_requested(): void
    {
        $user = User::factory()->create();

        $this->expectException(HttpException::class);

        app(RequestPlatformAccess::class)->execute($user, PlatformRole::Superadmin);
    }

    public function test_user_cannot_review_their_own_platform_access_request(): void
    {
        $administrator = User::factory()->create();
        PlatformAccessGrant::factory()->for($administrator)->create();

        $request = PlatformAccessRequest::create([
            'user_id' => $administrator->id,
            'role' => PlatformRole::GroupCreator,
            'status' => 'pending',
            'pending_key' => $administrator->id.':group_creator',
            'correlation_id' => fake()->uuid(),
        ]);

        $this->expectException(HttpException::class);

        app(ReviewPlatformAccessRequest::class)->execute($request, $administrator, true);
    }
}
