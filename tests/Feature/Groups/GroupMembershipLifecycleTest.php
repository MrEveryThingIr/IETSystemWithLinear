<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\TransitionGroupMembership;
use App\GroupPermission;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupMembershipEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GroupMembershipLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_suspension_preserves_roles_but_disables_authority_until_reactivation(): void
    {
        [$group, $owner, $member, $membership, $roles] = $this->groupWithMember();
        $manager = $roles->createRole($group, 'Manager', [GroupPermission::ManageGroup->value]);
        $roles->grant($member, $group, $manager);
        $transitions = app(TransitionGroupMembership::class);

        $transitions->suspend($membership, $owner, 'Temporary safety review.');

        $this->assertSame('suspended', $membership->refresh()->status);
        $this->assertEqualsCanonicalizing(['Manager', 'Member'], $roles->roleNames($member, $group)->all());
        $this->assertFalse(Gate::forUser($member->user)->allows('update', $group));

        $transitions->reactivate($membership, $owner, 'Review completed.');

        $this->assertSame('active', $membership->refresh()->status);
        $this->assertTrue(Gate::forUser($member->user)->allows('update', $group));
        $this->assertSame(['membership.suspended', 'membership.active'], $membership->events()->pluck('event')->all());
    }

    public function test_removal_revokes_non_baseline_roles_and_readmission_restores_only_member(): void
    {
        [$group, $owner, $member, $membership, $roles] = $this->groupWithMember();
        $manager = $roles->createRole($group, 'Manager', [GroupPermission::ManageMembers->value]);
        $roles->grant($member, $group, $manager);
        $transitions = app(TransitionGroupMembership::class);

        $transitions->remove($membership, $owner, 'Participation ended.');

        $this->assertSame('removed', $membership->refresh()->status);
        $this->assertSame(['Member'], $roles->roleNames($member, $group)->all());

        $transitions->readmit($membership, $owner, 'Approved through a new admission.');

        $this->assertSame('active', $membership->refresh()->status);
        $this->assertSame(['Member'], $roles->roleNames($member, $group)->all());
        $this->assertSame(
            ['membership.removed', 'membership.active'],
            $membership->events()->pluck('event')->all(),
        );
        $this->assertTrue((bool) $membership->events()->latest('id')->value('metadata')['readmission']);
    }

    public function test_removed_membership_cannot_be_reactivated_without_readmission(): void
    {
        [$group, $owner, , $membership] = $this->groupWithMember();
        $transitions = app(TransitionGroupMembership::class);
        $transitions->remove($membership, $owner, 'Removed.');

        $this->expectException(HttpException::class);

        $transitions->reactivate($membership->refresh(), $owner, 'Invalid shortcut.');
    }

    public function test_status_cannot_be_changed_outside_the_lifecycle_action(): void
    {
        [, , , $membership] = $this->groupWithMember();

        $this->expectException(LogicException::class);

        $membership->update(['status' => 'removed']);
    }

    public function test_membership_events_are_immutable(): void
    {
        [, , , $membership] = $this->groupWithMember();
        $event = GroupMembershipEvent::factory()->for($membership, 'membership')->create([
            'group_id' => $membership->group_id,
        ]);

        $this->expectException(LogicException::class);

        $event->reason = 'Rewritten history.';
        $event->save();
    }

    /** @return array{Group, Actor, Actor, GroupMembership, GroupRoleProvisioner} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = Group::factory()->for($owner, 'creator')->create();
        $ownerMembership = $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $membership = $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $builtIns = $roles->provision($group);
        $roles->grant($owner, $group, $builtIns['owner']);
        $roles->grant($member, $group, $builtIns['member']);
        app(TransitionGroupMembership::class)->recordInitial($ownerMembership, $owner, 'Group created.');

        return [$group, $owner, $member, $membership, $roles];
    }
}
