<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\TransitionGroupMembership;
use App\GroupPermission;
use App\Livewire\Groups\Show;
use App\Models\Actor;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class GroupAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_group_creation_requires_platform_capability(): void
    {
        $ordinaryUser = User::factory()->create();
        Actor::factory()->for($ordinaryUser)->create();
        $administrator = User::factory()->superadmin()->create();
        Actor::factory()->for($administrator)->create();

        $this->actingAs($ordinaryUser)->get(route('groups.create'))->assertForbidden();
        $this->actingAs($administrator)->get(route('groups.create'))->assertOk();
    }

    public function test_group_mutation_rechecks_active_user_and_actor_after_mount(): void
    {
        $owner = Actor::factory()->create();
        $group = $this->createOwnedGroup($owner);

        $component = Livewire::actingAs($owner->user)
            ->test(Show::class, ['group' => $group])
            ->set('name', 'Unauthorized change');

        $owner->user()->update(['status' => 'suspended']);

        $this->assertFalse(Gate::forUser($owner->user)->allows('update', $group));

        $component->call('save')->assertStatus(403);

        $this->assertSame('First group', $group->refresh()->name);
    }

    public function test_group_mutation_rechecks_active_membership_after_mount(): void
    {
        $owner = Actor::factory()->create();
        $group = $this->createOwnedGroup($owner);
        $manager = Actor::factory()->create();
        $membership = $group->memberships()->create(['actor_id' => $manager->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $managerRole = $roles->createRole($group, 'Group editor', [GroupPermission::ManageGroup->value]);
        $roles->grant($manager, $group, $managerRole);

        $component = Livewire::actingAs($manager->user)
            ->test(Show::class, ['group' => $group])
            ->set('name', 'Unauthorized change');

        app(TransitionGroupMembership::class)->remove($membership, $owner, 'Access revoked during an open page.');

        $this->assertFalse(Gate::forUser($manager->user)->allows('update', $group));

        $component->call('save')->assertStatus(403);

        $this->assertSame('First group', $group->refresh()->name);
    }

    public function test_permissions_do_not_leak_between_groups(): void
    {
        $firstOwner = Actor::factory()->create();
        $secondOwner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $firstGroup = $this->createOwnedGroup($firstOwner);
        $secondGroup = $this->createOwnedGroup($secondOwner, 'Second group');
        $roles = app(GroupRoleProvisioner::class);

        foreach ([$firstGroup, $secondGroup] as $group) {
            $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        }

        $editor = $roles->createRole($firstGroup, 'Editor', ['participate', 'manage_group']);
        $roles->grant($member, $firstGroup, $editor);
        $roles->grant($member, $secondGroup, $roles->provision($secondGroup)['member']);

        $this->assertTrue($roles->hasPermission($member, $firstGroup, 'manage_group'));
        $this->assertFalse($roles->hasPermission($member, $secondGroup, 'manage_group'));
        $this->assertTrue(Gate::forUser($member->user)->allows('update', $firstGroup));
        $this->assertFalse(Gate::forUser($member->user)->allows('update', $secondGroup));

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $firstGroup])
            ->set('name', 'Permitted change')
            ->call('save')
            ->assertStatus(200);

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $secondGroup])
            ->set('name', 'Denied change')
            ->call('save')
            ->assertStatus(403);

        $this->assertSame('Permitted change', $firstGroup->refresh()->name);
        $this->assertSame('Second group', $secondGroup->refresh()->name);
    }

    public function test_management_surfaces_follow_contextual_capabilities(): void
    {
        $owner = Actor::factory()->create();
        $manager = Actor::factory()->create();
        $group = $this->createOwnedGroup($owner);
        $group->memberships()->create(['actor_id' => $manager->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $memberManager = $roles->createRole($group, 'Member manager', [GroupPermission::ManageMembers->value]);
        $roles->grant($manager, $group, $memberManager);

        $this->assertTrue(Gate::forUser($manager->user)->allows('manageMembers', $group));
        $this->assertFalse(Gate::forUser($manager->user)->allows('manageRoles', $group));
        $this->assertFalse(Gate::forUser($manager->user)->allows('transferOwnership', $group));

        Livewire::actingAs($manager->user)
            ->test(Show::class, ['group' => $group])
            ->assertSee(__('ui.groups.suspend'))
            ->assertDontSee(__('ui.groups.roles_permissions'))
            ->assertDontSee(__('ui.groups.transfer_ownership'));
    }

    private function createOwnedGroup(Actor $owner, string $name = 'First group'): Group
    {
        $group = Group::create(['name' => $name, 'description' => null, 'created_by_actor_id' => $owner->id]);
        $roles = app(GroupRoleProvisioner::class);
        $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $roles->grant($owner, $group, $roles->provision($group)['owner']);

        return $group;
    }
}
