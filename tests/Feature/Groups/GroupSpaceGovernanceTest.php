<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ArchiveGroupSpace;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\EnsureDefaultGroupSpace;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PostGroupSpaceMessage;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\TransitionGroupMembership;
use App\Actions\Groups\UpdateGroupSpace;
use App\GroupPermission;
use App\GroupRoleKey;
use App\Livewire\Groups\Show;
use App\Livewire\Groups\SpaceChat;
use App\Livewire\Groups\SpaceManagement;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\GroupSpaceParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GroupSpaceGovernanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_group_creation_still_provisions_exactly_one_general_group_space(): void
    {
        $owner = Actor::factory()->create();

        $group = app(CreateGroup::class)->execute($owner, 'Governed group', null);

        $space = $group->spaces()->sole();

        $this->assertSame('General', $space->name);
        $this->assertSame('general', $space->slug);
        $this->assertSame('group', $space->access_mode);
        $this->assertSame('active', $space->status);
        $this->assertTrue($space->is_default);
    }

    public function test_existing_group_default_space_provisioning_is_safe_and_idempotent(): void
    {
        $owner = Actor::factory()->create();
        $group = Group::create([
            'name' => 'Legacy group',
            'description' => null,
            'timezone' => 'UTC',
            'created_by_actor_id' => $owner->id,
        ]);

        $legacy = GroupSpace::create([
            'group_id' => $group->id,
            'created_by_actor_id' => $owner->id,
            'name' => 'General',
            'slug' => 'general',
            'kind' => 'chat',
            'access_mode' => 'restricted',
            'status' => 'active',
            'is_default' => false,
        ]);

        $first = app(EnsureDefaultGroupSpace::class)->execute($group, $owner);
        $second = app(EnsureDefaultGroupSpace::class)->execute($group, $owner);

        $this->assertSame($legacy->id, $first->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $group->spaces()->where('slug', 'general')->count());
        $this->assertSame('group', $second->access_mode);
        $this->assertTrue($second->is_default);
    }

    public function test_ordinary_active_member_sees_and_posts_in_general(): void
    {
        [$group, , $member, , $general] = $this->groupWithMember();

        $this->assertTrue(Gate::forUser($member->user)->allows('view', $general));

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $group])
            ->assertSee('# General');

        Livewire::actingAs($member->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $general])
            ->set('message', 'Inherited participation works.')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $this->mainConversationId($general),
            'author_actor_id' => $member->id,
            'body' => 'Inherited participation works.',
        ]);
    }

    public function test_explicit_deny_blocks_otherwise_authorized_member_from_general(): void
    {
        [$group, $owner, $member, , $general] = $this->groupWithMember();

        app(SetGroupSpaceParticipant::class)->execute(
            $general,
            $member,
            $owner->user,
            'deny',
            'participant',
        );

        $this->assertFalse(Gate::forUser($member->user)->allows('view', $general));
        $this->assertFalse(Gate::forUser($member->user)->allows('post', $general));

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $group])
            ->assertDontSee('# General');

        Livewire::actingAs($member->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $general])
            ->assertStatus(403);
    }

    public function test_restricted_space_is_invisible_to_ordinary_members(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');

        $this->assertFalse(Gate::forUser($member->user)->allows('view', $space));

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $group])
            ->assertDontSee('# Engineering');

        Livewire::actingAs($member->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->assertStatus(403);
    }

    public function test_explicitly_allowed_active_member_can_view_and_post_in_restricted_space(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');

        $this->allow($space, $member, $owner);

        $this->assertTrue(Gate::forUser($member->user)->allows('view', $space));

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $group])
            ->assertSee('# Engineering');

        Livewire::actingAs($member->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->set('message', 'Restricted member message.')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $this->mainConversationId($space),
            'author_actor_id' => $member->id,
        ]);
    }

    public function test_authenticated_actor_who_never_joined_group_can_use_only_explicitly_allowed_restricted_space(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Private collaboration', null);
        $engineering = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $other = $this->createSpace($group, $owner, 'Finance', 'restricted');

        $this->allow($engineering, $outsider, $owner);

        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $outsider->id,
        ]);
        $this->assertTrue(Gate::forUser($outsider->user)->allows('view', $engineering));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('view', $other));

        Livewire::actingAs($outsider->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $engineering])
            ->set('message', 'Non-member collaboration works.')
            ->call('send')
            ->assertHasNoErrors();

        Livewire::actingAs($outsider->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $other])
            ->assertStatus(403);
    }

    public function test_explicitly_allowed_non_member_gets_no_group_operations_or_permissions(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Boundary group', null);
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $this->allow($space, $outsider, $owner);

        $otherCandidate = Actor::factory()->create();
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $otherCandidate->id,
        ]);

        $this->actingAs($outsider->user)->get(route('groups.show', $group))->assertForbidden();
        $this->actingAs($outsider->user)->get(route('groups.agreements', $group))->assertForbidden();
        $this->actingAs($outsider->user)->get(route('groups.invitations', $group))->assertForbidden();
        $this->actingAs($outsider->user)->get(route('admissions.show', $admission))->assertForbidden();

        $this->assertFalse(Gate::forUser($outsider->user)->allows('update', $group));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('manageMembers', $group));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('manageRoles', $group));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('viewGroupAudit', $group));
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $outsider->id,
        ]);
    }

    public function test_suspended_and_removed_members_cannot_bypass_membership_state_with_old_allow_rules(): void
    {
        [$group, $owner, $suspended, $suspendedMembership] = $this->groupWithMember();
        [$removed, $removedMembership] = $this->addMember($group, $owner);
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');

        $this->allow($space, $suspended, $owner);
        $this->allow($space, $removed, $owner);

        app(TransitionGroupMembership::class)->suspend($suspendedMembership, $owner, 'Suspended for test.');
        app(TransitionGroupMembership::class)->remove($removedMembership, $owner, 'Removed for test.');

        $this->assertFalse(Gate::forUser($suspended->user)->allows('view', $space));
        $this->assertFalse(Gate::forUser($removed->user)->allows('view', $space));
        $this->assertFalse(Gate::forUser($suspended->user)->allows('post', $space));
        $this->assertFalse(Gate::forUser($removed->user)->allows('post', $space));
    }

    public function test_agreement_reacceptance_still_blocks_explicitly_allowed_active_member(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $this->allow($space, $member, $owner);

        $agreement = GroupAgreement::factory()->create([
            'group_id' => $group->id,
        ]);
        GroupAgreementVersion::factory()->active()->create([
            'group_agreement_id' => $agreement->id,
            'created_by_actor_id' => $owner->id,
            'approved_by_actor_id' => $owner->id,
            'reacceptance_required' => true,
        ]);

        $this->assertFalse(Gate::forUser($member->user)->allows('view', $space));
        $this->assertFalse(Gate::forUser($member->user)->allows('post', $space));
    }

    public function test_one_person_restricted_space_requires_explicit_self_allow_and_then_works(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'My Notes', 'restricted');

        $this->assertTrue(Gate::forUser($owner->user)->allows('manage', $space));
        $this->assertFalse(Gate::forUser($owner->user)->allows('view', $space));

        $this->allow($space, $owner, $owner);

        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $space));
        $this->assertFalse(Gate::forUser($member->user)->allows('view', $space));

        app(PostGroupSpaceMessage::class)->execute($space, $owner->user, 'Private note.');

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $this->mainConversationId($space),
            'author_actor_id' => $owner->id,
            'body' => 'Private note.',
        ]);
    }

    public function test_local_manager_can_manage_only_their_space(): void
    {
        [$group, $owner, $member, , $general] = $this->groupWithMember();
        $engineering = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $finance = $this->createSpace($group, $owner, 'Finance', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute(
            $engineering,
            $member,
            $owner->user,
            'allow',
            'manager',
        );

        $this->assertTrue(Gate::forUser($member->user)->allows('manage', $engineering));
        $this->assertTrue(Gate::forUser($member->user)->allows('manageParticipants', $engineering));
        $this->assertFalse(Gate::forUser($member->user)->allows('manage', $finance));
        $this->assertFalse(Gate::forUser($member->user)->allows('manage', $general));

        Livewire::actingAs($member->user)
            ->test(SpaceManagement::class, ['group' => $group])
            ->assertSee('Engineering')
            ->assertDontSee('Finance');
    }

    public function test_manage_spaces_group_role_can_manage_every_active_space(): void
    {
        [$group, $owner, $member, , $general] = $this->groupWithMember();
        $engineering = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $finance = $this->createSpace($group, $owner, 'Finance', 'restricted');
        $this->grantManageSpaces($group, $member);

        $this->assertTrue(Gate::forUser($member->user)->allows('manage', $general));
        $this->assertTrue(Gate::forUser($member->user)->allows('manage', $engineering));
        $this->assertTrue(Gate::forUser($member->user)->allows('manage', $finance));
    }

    public function test_manage_spaces_does_not_grant_read_access_to_restricted_messages(): void
    {
        [$group, $owner, $manager] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $this->grantManageSpaces($group, $manager);

        $this->assertTrue(Gate::forUser($manager->user)->allows('manage', $space));
        $this->assertFalse(Gate::forUser($manager->user)->allows('view', $space));
        $this->assertFalse(Gate::forUser($manager->user)->allows('post', $space));
    }

    public function test_explicit_deny_overrides_membership_manage_spaces_and_stale_livewire_state(): void
    {
        [$group, $owner, $manager, , $general] = $this->groupWithMember();
        $this->grantManageSpaces($group, $manager);

        $component = Livewire::actingAs($manager->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $general])
            ->set('message', 'Must be blocked after deny.');

        app(SetGroupSpaceParticipant::class)->execute(
            $general,
            $manager,
            $owner->user,
            'deny',
            'participant',
        );

        $this->assertTrue(Gate::forUser($manager->user)->allows('manage', $general));
        $this->assertFalse(Gate::forUser($manager->user)->allows('view', $general));
        $component->call('send')->assertStatus(403);
        $this->assertDatabaseMissing('conversation_messages', ['body' => 'Must be blocked after deny.']);
    }

    public function test_cross_group_space_substitution_cannot_mount_or_mutate(): void
    {
        $firstOwner = Actor::factory()->create();
        $secondOwner = Actor::factory()->create();
        $firstGroup = app(CreateGroup::class)->execute($firstOwner, 'First group', null);
        $secondGroup = app(CreateGroup::class)->execute($secondOwner, 'Second group', null);
        $secondSpace = $secondGroup->spaces()->sole();

        Livewire::actingAs($firstOwner->user)
            ->test(SpaceChat::class, ['group' => $firstGroup, 'space' => $secondSpace])
            ->assertStatus(404);

        Livewire::actingAs($firstOwner->user)
            ->test(SpaceManagement::class, ['group' => $firstGroup])
            ->call('beginEdit', $secondSpace->id)
            ->assertStatus(404);

        $this->assertSame('General', $secondSpace->fresh()->name);
    }

    public function test_general_cannot_be_archived_renamed_or_converted_to_restricted(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Protected General', null);
        $general = $group->spaces()->sole();

        try {
            app(ArchiveGroupSpace::class)->execute($general, $owner->user);
            $this->fail('General was archived.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        try {
            app(UpdateGroupSpace::class)->execute($general, $owner->user, 'General', 'restricted');
            $this->fail('General was converted to restricted access.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        try {
            app(UpdateGroupSpace::class)->execute($general, $owner->user, 'Renamed General', 'group');
            $this->fail('General was renamed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $general->refresh();
        $this->assertSame('General', $general->name);
        $this->assertSame('group', $general->access_mode);
        $this->assertSame('active', $general->status);
    }

    public function test_archived_space_cannot_be_viewed_or_posted_to_even_from_stale_mount(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Archive test', null);
        $space = $this->createSpace($group, $owner, 'Temporary', 'group');

        $component = Livewire::actingAs($owner->user)
            ->test(SpaceChat::class, ['group' => $group, 'space' => $space])
            ->set('message', 'Must not survive archive.');

        app(ArchiveGroupSpace::class)->execute($space, $owner->user);

        $this->assertFalse(Gate::forUser($owner->user)->allows('view', $space->fresh()));
        $component->call('send')->assertStatus(403);
        $this->assertDatabaseMissing('conversation_messages', ['body' => 'Must not survive archive.']);
    }

    public function test_duplicate_participant_mutations_are_idempotent(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');

        $first = app(SetGroupSpaceParticipant::class)->execute(
            $space,
            $member,
            $owner->user,
            'allow',
            'participant',
        );
        $second = app(SetGroupSpaceParticipant::class)->execute(
            $space,
            $member,
            $owner->user,
            'allow',
            'participant',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('group_space_participants', 1);
    }

    public function test_denied_rule_cannot_carry_manager_authority(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');

        try {
            app(SetGroupSpaceParticipant::class)->execute(
                $space,
                $member,
                $owner->user,
                'deny',
                'manager',
            );
            $this->fail('Denied manager rule was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('group_space_participants', 0);
    }

    public function test_unverified_inactive_user_and_archived_actor_cannot_use_explicit_allow(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Identity boundary', null);
        $space = $this->createSpace($group, $owner, 'Engineering', 'restricted');

        $unverifiedUser = User::factory()->unverified()->create();
        $unverified = Actor::factory()->for($unverifiedUser)->create();
        $suspendedUser = User::factory()->suspended()->create();
        $suspended = Actor::factory()->for($suspendedUser)->create();
        $archived = Actor::factory()->archived()->create();

        foreach ([$unverified, $suspended, $archived] as $actor) {
            $this->allow($space, $actor, $owner);
            $this->assertFalse(Gate::forUser($actor->user)->allows('view', $space));
        }
    }

    public function test_non_member_local_manager_can_manage_only_explicitly_managed_restricted_space(): void
    {
        $owner = Actor::factory()->create();
        $manager = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'External manager', null);
        $engineering = $this->createSpace($group, $owner, 'Engineering', 'restricted');
        $finance = $this->createSpace($group, $owner, 'Finance', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute(
            $engineering,
            $manager,
            $owner->user,
            'allow',
            'manager',
        );

        $this->assertTrue(Gate::forUser($manager->user)->allows('view', $engineering));
        $this->assertTrue(Gate::forUser($manager->user)->allows('manage', $engineering));
        $this->assertFalse(Gate::forUser($manager->user)->allows('manage', $finance));
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $manager->id,
        ]);
    }

    public function test_space_management_livewire_creates_restricted_space_and_sets_participant_rule(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();

        $component = Livewire::actingAs($owner->user)
            ->test(SpaceManagement::class, ['group' => $group])
            ->set('newSpaceName', 'Engineering')
            ->set('newSpaceAccessMode', 'restricted')
            ->call('createSpace')
            ->assertHasNoErrors();

        $space = $group->spaces()->where('slug', 'engineering')->firstOrFail();

        $component
            ->set('participantActorId', (string) $member->id)
            ->set('participantAccess', 'allow')
            ->set('participantRole', 'participant')
            ->call('setParticipant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('group_space_participants', [
            'group_space_id' => $space->id,
            'actor_id' => $member->id,
            'access' => 'allow',
            'role' => 'participant',
        ]);
        $this->assertTrue(Gate::forUser($member->user)->allows('view', $space));
    }

    private function mainConversationId(GroupSpace $space): int
    {
        $contextId = $space->contextBinding()->value('context_id');

        return (int) Conversation::query()
            ->where('context_id', $contextId)
            ->where('key', 'main')
            ->valueOrFail('id');
    }

    /** @return array{Group, Actor, Actor, GroupMembership, GroupSpace} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Governance group', null);
        [$member, $membership] = $this->addMember($group, $owner);

        /** @var GroupSpace $general */
        $general = $group->spaces()->where('is_default', true)->sole();

        return [$group, $owner, $member, $membership, $general];
    }

    /** @return array{Actor, GroupMembership} */
    private function addMember(Group $group, Actor $actingOwner): array
    {
        $member = Actor::factory()->create();
        $membership = $group->memberships()->create([
            'actor_id' => $member->id,
            'status' => 'active',
        ]);

        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($member, $group, $roles->builtInRole($group, GroupRoleKey::Member));
        app(TransitionGroupMembership::class)->recordInitial(
            $membership,
            $actingOwner,
            'Member added for Space governance test.',
        );

        return [$member, $membership];
    }

    private function createSpace(Group $group, Actor $owner, string $name, string $accessMode): GroupSpace
    {
        return app(CreateGroupSpace::class)->execute($group, $owner->user, $name, $accessMode);
    }

    private function allow(GroupSpace $space, Actor $actor, Actor $grantor): GroupSpaceParticipant
    {
        return app(SetGroupSpaceParticipant::class)->execute(
            $space,
            $actor,
            $grantor->user,
            'allow',
            'participant',
        );
    }

    private function grantManageSpaces(Group $group, Actor $actor): void
    {
        $roles = app(GroupRoleProvisioner::class);
        $role = $roles->createRole(
            $group,
            'Space Administrator '.$actor->id,
            [GroupPermission::ManageSpaces->value],
        );
        $roles->grant($actor, $group, $role);
    }
}
