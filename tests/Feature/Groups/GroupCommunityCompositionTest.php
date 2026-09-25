<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\TransitionGroupMembership;
use App\Actions\Relationships\CreateRelationship;
use App\GroupRoleKey;
use App\Livewire\Groups\Community;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\Plan;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupCommunityCompositionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_composes_existing_kernels_without_copying_domain_storage(): void
    {
        [$group, $owner, $member, $general] = $this->groupWithMember();
        $context = $general->contextBinding()->with('context')->firstOrFail()->context;

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $general,
            $owner->user,
            'Community article',
            null,
            [[
                'key' => 'body',
                'label' => 'Body',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);
        $content = app(CreateSpaceContent::class)->execute(
            $general,
            $definition,
            $owner->user,
            'Riverside community update',
            ['body' => 'Shared through the existing Content kernel.'],
        );
        app(PublishSpaceContent::class)->execute($content, $owner->user);

        Plan::factory()->create([
            'context_id' => $context->id,
            'created_by_actor_id' => $owner->id,
            'title' => 'Riverside inspection walk',
        ]);

        $concept = Concept::factory()->create();
        $profile = ActorProfile::factory()->create(['actor_id' => $member->id]);
        ActorProfileIntent::factory()->create([
            'actor_profile_id' => $profile->id,
            'concept_id' => $concept->id,
            'created_by_actor_id' => $member->id,
            'kind' => ProfileIntentKind::Offer,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Authenticated,
            'title' => 'Electrical service available',
        ]);

        app(CreateRelationship::class)->execute(
            $owner->user,
            $concept,
            'client',
            [['actor' => $member, 'role' => 'provider']],
            title: 'Riverside electrical collaboration',
        );

        Livewire::actingAs($owner->user)
            ->test(Community::class, ['group' => $group])
            ->assertSee('# General')
            ->assertSee('Riverside community update')
            ->assertSee('Riverside inspection walk')
            ->assertSee('Electrical service available')
            ->assertSee('Riverside electrical collaboration')
            ->assertSee($member->user->username)
            ->assertSee(route('groups.spaces.show', [$group, $general]), false)
            ->assertSee(route('planner.index', ['context' => $context->uuid]), false)
            ->assertSee(route('contexts.timeline', $context), false);

        $this->assertDatabaseCount('stories', 0);
        $this->assertDatabaseCount('space_contents', 1);
        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseCount('actor_profile_intents', 1);
        $this->assertDatabaseCount('relationships', 1);
    }

    public function test_community_respects_space_intent_and_relationship_policies(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();

        $secretSpace = app(CreateGroupSpace::class)->execute(
            $group,
            $owner->user,
            'Finance',
            'restricted',
        );
        $secretContext = $secretSpace->contextBinding()->with('context')->firstOrFail()->context;

        Plan::factory()->create([
            'context_id' => $secretContext->id,
            'created_by_actor_id' => $owner->id,
            'title' => 'Private finance plan',
        ]);

        $concept = Concept::factory()->create();
        $ownerProfile = ActorProfile::factory()->create(['actor_id' => $owner->id]);
        ActorProfileIntent::factory()->create([
            'actor_profile_id' => $ownerProfile->id,
            'concept_id' => $concept->id,
            'created_by_actor_id' => $owner->id,
            'kind' => ProfileIntentKind::Offer,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Private,
            'title' => 'Private owner offer',
        ]);

        $outsider = Actor::factory()->create();
        app(CreateRelationship::class)->execute(
            $owner->user,
            $concept,
            'owner',
            [['actor' => $outsider, 'role' => 'outsider']],
            title: 'Owner outsider private relationship',
        );

        Livewire::actingAs($member->user)
            ->test(Community::class, ['group' => $group])
            ->assertDontSee('# Finance')
            ->assertDontSee('Private finance plan')
            ->assertDontSee('Private owner offer')
            ->assertDontSee('Owner outsider private relationship');

        $this->actingAs($outsider->user)
            ->get(route('groups.community', $group))
            ->assertForbidden();
    }

    public function test_group_index_opens_community_as_the_member_facing_entry_point(): void
    {
        [$group, , $member] = $this->groupWithMember();

        $this->actingAs($member->user)
            ->get(route('groups.index'))
            ->assertOk()
            ->assertSee(route('groups.community', $group), false);
    }

    /** @return array{Group, Actor, Actor, GroupSpace, GroupMembership} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Maple Housing Office', null);
        $membership = $group->memberships()->create([
            'actor_id' => $member->id,
            'status' => 'active',
        ]);

        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($member, $group, $roles->builtInRole($group, GroupRoleKey::Member));
        app(TransitionGroupMembership::class)->recordInitial(
            $membership,
            $owner,
            'Member added for community composition proof.',
        );

        /** @var GroupSpace $general */
        $general = $group->spaces()->sole();

        return [$group, $owner, $member, $general, $membership];
    }
}
