<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\RemoveGroupMember;
use App\Exceptions\CannotLeaveGroupWithoutOwner;
use App\GroupRoleKey;
use App\Livewire\Groups\Show;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupOwnerIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_removing_a_non_last_owner_succeeds(): void
    {
        [$group, $firstOwner, $secondOwner] = $this->groupWithTwoOwners();
        $secondMembership = $group->memberships()->where('actor_id', $secondOwner->id)->sole();

        Livewire::actingAs($firstOwner->user)
            ->test(Show::class, ['group' => $group])
            ->call('removeMember', $secondMembership->id)
            ->assertStatus(200);

        $this->assertSame('removed', $secondMembership->refresh()->status);
        $this->assertSame(1, $this->activeOwnerCount($group));
    }

    public function test_removing_the_last_owner_is_rejected(): void
    {
        [$group, $owner] = $this->ownedGroup();
        $membership = $group->memberships()->where('actor_id', $owner->id)->sole();

        Livewire::actingAs($owner->user)
            ->test(Show::class, ['group' => $group])
            ->call('removeMember', $membership->id)
            ->assertStatus(200);

        $this->assertSame('active', $membership->refresh()->status);
        $this->assertSame(1, $this->activeOwnerCount($group));
    }

    public function test_revoking_the_last_owner_role_is_rejected(): void
    {
        [$group, $owner, $roles] = $this->ownedGroup();

        $rejected = false;

        try {
            $roles->revoke($owner, $group, $roles->builtInRole($group, GroupRoleKey::Owner));
            $this->fail('The final active Owner role was revoked.');
        } catch (CannotLeaveGroupWithoutOwner) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertTrue($roles->hasRole($owner, $group, 'Owner'));
        $this->assertSame(1, $this->activeOwnerCount($group));
    }

    public function test_competing_owner_loss_attempts_are_safe_in_either_serial_order(): void
    {
        [$firstGroup, $firstOwner, $secondOwner, $roles] = $this->groupWithTwoOwners();
        $removeGroupMember = app(RemoveGroupMember::class);

        $removeGroupMember->handle($firstGroup->memberships()->where('actor_id', $firstOwner->id)->sole());

        try {
            $roles->revoke($secondOwner, $firstGroup, $roles->builtInRole($firstGroup, GroupRoleKey::Owner));
            $this->fail('The final active Owner role was revoked.');
        } catch (CannotLeaveGroupWithoutOwner) {
            $this->assertSame(1, $this->activeOwnerCount($firstGroup));
        }

        [$secondGroup, $thirdOwner, $fourthOwner, $roles] = $this->groupWithTwoOwners();
        $roles->revoke($thirdOwner, $secondGroup, $roles->builtInRole($secondGroup, GroupRoleKey::Owner));

        try {
            $removeGroupMember->handle($secondGroup->memberships()->where('actor_id', $fourthOwner->id)->sole());
            $this->fail('The final active Owner was removed.');
        } catch (CannotLeaveGroupWithoutOwner) {
            $this->assertSame(1, $this->activeOwnerCount($secondGroup));
        }
    }

    public function test_current_member_cannot_redeem_an_invitation_or_change_owner_role(): void
    {
        [$group, $owner, $roles] = $this->ownedGroup();
        $invitation = GroupInvitation::create([
            'group_id' => $group->id,
            'invited_by_actor_id' => $owner->id,
            'token' => 'last-owner-invitation',
            'expires_at' => now()->addDay(),
            'max_uses' => 1,
            'uses_count' => 0,
        ]);

        $this->actingAs($owner->user)
            ->post(route('invitations.accept', $invitation->token))
            ->assertUnprocessable();

        $this->assertTrue($roles->hasRole($owner, $group, 'Owner'));
        $this->assertSame(0, $invitation->refresh()->uses_count);
        $this->assertCount(0, $invitation->acceptances);
        $this->assertSame(1, $this->activeOwnerCount($group));
        $this->assertDatabaseCount('group_memberships', 1);
        $this->assertDatabaseCount('admissions', 0);
    }

    /** @return array{Group, Actor, GroupRoleProvisioner} */
    private function ownedGroup(): array
    {
        $owner = Actor::factory()->create();
        $group = Group::create(['name' => 'Owner integrity', 'description' => null, 'created_by_actor_id' => $owner->id]);
        $roles = app(GroupRoleProvisioner::class);
        $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $roles->grant($owner, $group, $roles->provision($group)['owner']);

        return [$group, $owner, $roles];
    }

    /** @return array{Group, Actor, Actor, GroupRoleProvisioner} */
    private function groupWithTwoOwners(): array
    {
        [$group, $firstOwner, $roles] = $this->ownedGroup();
        $secondOwner = Actor::factory()->create();
        $group->memberships()->create(['actor_id' => $secondOwner->id, 'status' => 'active']);
        $roles->grant($secondOwner, $group, $roles->provision($group)['owner']);

        return [$group, $firstOwner, $secondOwner, $roles];
    }

    private function activeOwnerCount(Group $group): int
    {
        $roles = app(GroupRoleProvisioner::class);

        return $group->memberships()
            ->where('status', 'active')
            ->with('actor')
            ->get()
            ->filter(fn (GroupMembership $membership): bool => $roles->hasRole($membership->actor, $group, 'Owner'))
            ->count();
    }
}
