<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\TransferGroupOwnership;
use App\GroupPermission;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TransferGroupOwnershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ownership_transfer_is_atomic_and_preserves_other_roles(): void
    {
        [$group, $owner, $member, $membership, $roles] = $this->ownedGroupWithMember();
        $coordinator = $roles->createRole($group, 'Coordinator', [GroupPermission::ManageInvitations->value]);
        $roles->grant($member, $group, $coordinator);

        app(TransferGroupOwnership::class)->execute($group, $owner, $membership);

        $this->assertSame(['Member'], $roles->roleNames($owner, $group)->all());
        $this->assertEqualsCanonicalizing(['Coordinator', 'Member', 'Owner'], $roles->roleNames($member, $group)->all());
        $this->assertFalse(Gate::forUser($owner->user)->allows('transferOwnership', $group));
        $this->assertTrue(Gate::forUser($member->user)->allows('transferOwnership', $group));
    }

    public function test_ownership_cannot_transfer_to_a_membership_in_another_group(): void
    {
        [$group, $owner] = $this->ownedGroupWithMember();
        $otherOwner = Actor::factory()->create();
        $otherGroup = Group::factory()->for($otherOwner, 'creator')->create();
        $otherMembership = $otherGroup->memberships()->create(['actor_id' => $otherOwner->id, 'status' => 'active']);

        $this->expectException(HttpException::class);

        app(TransferGroupOwnership::class)->execute($group, $owner, $otherMembership);
    }

    /** @return array{Group, Actor, Actor, GroupMembership, GroupRoleProvisioner} */
    private function ownedGroupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = Group::factory()->for($owner, 'creator')->create();
        $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $membership = $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $builtIns = $roles->provision($group);
        $roles->grant($owner, $group, $builtIns['owner']);
        $roles->grant($member, $group, $builtIns['member']);

        return [$group, $owner, $member, $membership, $roles];
    }
}
