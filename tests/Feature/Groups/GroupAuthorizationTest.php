<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Livewire\Groups\Show;
use App\Models\Actor;
use App\Models\Group;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_group_mutation_rechecks_active_membership_after_mount(): void
    {
        $owner = Actor::factory()->create();
        $group = $this->createOwnedGroup($owner);
        $membership = $group->memberships()->where('actor_id', $owner->id)->sole();

        $component = Livewire::actingAs($owner->user)
            ->test(Show::class, ['group' => $group])
            ->set('name', 'Unauthorized change');

        $membership->update(['status' => 'removed']);

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
        $roles->assign($member, $firstGroup, $editor);
        $roles->assign($member, $secondGroup, $roles->provision($secondGroup)['member']);

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

    private function createOwnedGroup(Actor $owner, string $name = 'First group'): Group
    {
        $group = Group::create(['name' => $name, 'description' => null, 'created_by_actor_id' => $owner->id]);
        $roles = app(GroupRoleProvisioner::class);
        $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $roles->assign($owner, $group, $roles->provision($group)['owner']);

        return $group;
    }
}
