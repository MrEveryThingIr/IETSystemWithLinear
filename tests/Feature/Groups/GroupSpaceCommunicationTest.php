<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\TransitionGroupMembership;
use App\GroupRoleKey;
use App\Livewire\Groups\Show;
use App\Livewire\Groups\SpaceChat;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupSpaceCommunicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_group_creation_provisions_one_default_general_chat_space(): void
    {
        $owner = Actor::factory()->create();

        $group = app(CreateGroup::class)->execute($owner, 'Communication group', null);

        $space = $group->spaces()->sole();

        $this->assertSame('General', $space->name);
        $this->assertSame('general', $space->slug);
        $this->assertSame('chat', $space->kind);
        $this->assertSame('active', $space->status);
        $this->assertTrue($space->is_default);
        $this->assertSame($owner->id, $space->created_by_actor_id);
    }

    public function test_active_member_can_post_and_other_member_can_read_chat_message(): void
    {
        [$group, $owner, $member, $space] = $this->groupWithMember();

        Livewire::actingAs($member->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->set('message', 'Hello from the first group space.')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('group_space_messages', [
            'group_space_id' => $space->id,
            'author_actor_id' => $member->id,
            'body' => 'Hello from the first group space.',
        ]);

        Livewire::actingAs($owner->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->assertSee($member->user->username)
            ->assertSee('Hello from the first group space.');
    }

    public function test_group_overview_exposes_general_space_tab(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Tabbed group', null);

        Livewire::actingAs($owner->user)
            ->test(Show::class, ['group' => $group])
            ->assertSee('Spaces')
            ->assertSee('# General');
    }

    public function test_outsider_cannot_open_group_space(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Private group', null);
        $space = $group->spaces()->sole();

        Livewire::actingAs($outsider->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->assertStatus(403);
    }

    public function test_suspended_member_cannot_post_after_chat_was_mounted(): void
    {
        [$group, $owner, $member, $space, $membership] = $this->groupWithMember();

        $component = Livewire::actingAs($member->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->set('message', 'This must not be persisted.');

        app(TransitionGroupMembership::class)->suspend($membership, $owner, 'Communication access revoked.');

        $component->call('send')->assertStatus(403);

        $this->assertDatabaseCount('group_space_messages', 0);
    }

    /** @return array{Group, Actor, Actor, GroupSpace, GroupMembership} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Communication group', null);
        $membership = $group->memberships()->create([
            'actor_id' => $member->id,
            'status' => 'active',
        ]);

        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($member, $group, $roles->builtInRole($group, GroupRoleKey::Member));
        app(TransitionGroupMembership::class)->recordInitial($membership, $owner, 'Member added for communication test.');

        /** @var GroupSpace $space */
        $space = $group->spaces()->sole();

        return [$group, $owner, $member, $space, $membership];
    }
}
