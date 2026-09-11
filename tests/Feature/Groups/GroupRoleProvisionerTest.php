<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\GroupPermission;
use App\Models\Actor;
use App\Models\Group;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GroupRoleProvisionerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_roles_are_additive_and_permissions_are_combined(): void
    {
        [$group, $member, $roles] = $this->activeMember();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $manager = $roles->createRole($group, 'Member Manager', [GroupPermission::ManageMembers->value]);

        $roles->grant($member, $group, $editor);
        $roles->grant($member, $group, $manager);

        $this->assertEqualsCanonicalizing(['Editor', 'Member', 'Member Manager'], $roles->roleNames($member, $group)->all());
        $this->assertTrue($roles->hasPermission($member, $group, GroupPermission::Participate->value));
        $this->assertTrue($roles->hasPermission($member, $group, GroupPermission::ManageGroup->value));
        $this->assertTrue($roles->hasPermission($member, $group, GroupPermission::ManageMembers->value));
    }

    public function test_revoking_custom_role_preserves_baseline_and_other_roles(): void
    {
        [$group, $member, $roles] = $this->activeMember();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $reviewer = $roles->createRole($group, 'Reviewer', [GroupPermission::ApproveRoleChanges->value]);
        $roles->grant($member, $group, $editor);
        $roles->grant($member, $group, $reviewer);

        $roles->revoke($member, $group, $editor);

        $this->assertEqualsCanonicalizing(['Member', 'Reviewer'], $roles->roleNames($member, $group)->all());
    }

    public function test_built_in_roles_have_immutable_keys_and_frozen_permissions(): void
    {
        [$group, , $roles] = $this->activeMember();
        $builtIns = $roles->provision($group);

        $this->assertSame('owner', $builtIns['owner']->getAttribute('system_key'));
        $this->assertSame('member', $builtIns['member']->getAttribute('system_key'));
        $this->assertEqualsCanonicalizing(GroupPermission::values(), $builtIns['owner']->permissions->pluck('name')->all());
        $this->assertSame([GroupPermission::Participate->value], $builtIns['member']->permissions->pluck('name')->all());
    }

    public function test_reserved_name_is_rejected_case_insensitively(): void
    {
        [$group, , $roles] = $this->activeMember();

        $this->expectException(HttpException::class);

        $roles->createRole($group, 'oWnEr', [GroupPermission::Participate->value]);
    }

    public function test_custom_role_cannot_receive_transfer_ownership(): void
    {
        [$group, , $roles] = $this->activeMember();

        $this->expectException(HttpException::class);

        $roles->createRole($group, 'Coordinator', [GroupPermission::TransferOwnership->value]);
    }

    /** @return array{Group, Actor, GroupRoleProvisioner} */
    private function activeMember(): array
    {
        $creator = Actor::factory()->create();
        $group = Group::factory()->for($creator, 'creator')->create();
        $member = Actor::factory()->create();
        $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $builtIns = $roles->provision($group);
        $roles->grant($member, $group, $builtIns['member']);

        return [$group, $member, $roles];
    }
}
