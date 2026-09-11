<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\ManageGroupAgreement;
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
}
