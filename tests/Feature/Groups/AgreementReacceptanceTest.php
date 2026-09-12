<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\ManageGroupAgreement;
use App\Actions\Groups\TransitionGroupMembership;
use App\Livewire\Groups\AcceptAgreements;
use App\Livewire\Groups\Index as GroupIndex;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementReacceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_and_member_must_reaccept_an_activated_required_revision_before_participating(): void
    {
        $owner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = Group::create(['name' => 'Group', 'created_by_actor_id' => $owner->id]);
        $roles = app(GroupRoleProvisioner::class);
        $ownerMembership = $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $memberMembership = $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        $provisioned = $roles->provision($group);
        $roles->grant($owner, $group, $provisioned['owner']);
        $roles->grant($member, $group, $provisioned['member']);
        $memberships = app(TransitionGroupMembership::class);
        $memberships->recordInitial($ownerMembership, null, 'Initial owner membership.');
        $memberships->recordInitial($memberMembership, null, 'Initial member membership.');

        $agreement = GroupAgreement::create(['group_id' => $group->id, 'name' => 'Rules']);
        $first = GroupAgreementVersion::create(['group_agreement_id' => $agreement->id, 'version' => 1, 'content' => 'First rules', 'status' => 'active', 'reacceptance_required' => true, 'effective_from' => now()->subMinute()]);
        $manager = app(ManageGroupAgreement::class);
        $manager->acceptMembership($ownerMembership, $first, $owner);
        $manager->acceptMembership($memberMembership, $first, $member);

        $scheduled = GroupAgreementVersion::create(['group_agreement_id' => $agreement->id, 'version' => 2, 'content' => 'Updated rules', 'status' => 'scheduled', 'reacceptance_required' => true, 'effective_from' => now()->subSecond()]);
        $this->assertSame(1, $manager->activateDue(now()));

        $this->assertFalse(Gate::forUser($owner->user)->allows('view', $group));
        $this->assertFalse(Gate::forUser($member->user)->allows('view', $group));

        Livewire::actingAs($member->user)
            ->test(GroupIndex::class)
            ->assertSee('Review agreements')
            ->assertSee(route('groups.accept-agreements', $group), false);

        Livewire::actingAs($owner->user)->test(AcceptAgreements::class, ['group' => $group])->call('accept', $scheduled->id)->assertStatus(200);
        Livewire::actingAs($member->user)
            ->test(AcceptAgreements::class, ['group' => $group])
            ->call('accept', $scheduled->id)
            ->assertStatus(200)
            ->assertSee('Open group');

        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $group));
        $this->assertTrue(Gate::forUser($member->user)->allows('view', $group));
        $this->assertDatabaseCount('membership_agreement_acceptances', 4);
    }

    public function test_a_new_membership_period_does_not_count_old_acceptance_evidence(): void
    {
        $owner = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = Group::factory()->for($owner, 'creator')->create();
        $ownerMembership = $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $membership = $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $provisioned = $roles->provision($group);
        $roles->grant($owner, $group, $provisioned['owner']);
        $roles->grant($member, $group, $provisioned['member']);
        $memberships = app(TransitionGroupMembership::class);
        $memberships->recordInitial($ownerMembership, null, 'Initial owner membership.');
        $memberships->recordInitial($membership, null, 'Initial membership.');
        $agreement = GroupAgreement::create(['group_id' => $group->id, 'name' => 'Period rules']);
        $version = GroupAgreementVersion::create([
            'group_agreement_id' => $agreement->id,
            'version' => 1,
            'content' => 'Accept for each membership period.',
            'status' => 'active',
            'reacceptance_required' => true,
            'effective_from' => now()->subMinute(),
        ]);
        $manager = app(ManageGroupAgreement::class);
        $firstAcceptance = $manager->acceptMembership($membership, $version, $member);
        $memberships->remove($membership, null, 'Period ended.');
        $membership = $memberships->readmit($membership, null, 'New period.');

        $this->assertFalse(Gate::forUser($member->user)->allows('view', $group));

        Livewire::actingAs($member->user)
            ->test(AcceptAgreements::class, ['group' => $group])
            ->assertSee('Accept for each membership period.')
            ->call('accept', $version->id)
            ->assertSee('Open group');

        $secondAcceptance = $membership->agreementAcceptances()->latest('id')->firstOrFail();
        $this->assertNotSame($firstAcceptance->group_membership_event_id, $secondAcceptance->group_membership_event_id);
        $this->assertTrue(Gate::forUser($member->user)->allows('view', $group));
    }
}
