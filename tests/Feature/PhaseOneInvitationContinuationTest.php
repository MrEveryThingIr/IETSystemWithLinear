<?php

namespace Tests\Feature;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\FinalizeAdmission;
use App\Actions\Groups\ManageAdmission;
use App\Actions\Groups\RedeemGroupInvitation;
use App\Livewire\Groups\Invitations as GroupInvitations;
use App\Models\Actor;
use App\Models\GroupInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseOneInvitationContinuationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_acceptance_post_returns_to_the_safe_unavailable_invitation_state(): void
    {
        $this->withoutVite();
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Inviting group', null);
        $token = GroupInvitation::issueToken();
        $invitation = GroupInvitation::factory()
            ->for($group)
            ->for($owner, 'inviter')
            ->create([
                'token' => $token,
                'expires_at' => now()->addDay(),
                'max_uses' => 1,
                'uses_count' => 0,
            ]);
        $candidate = Actor::factory()->create();

        $this->actingAs($candidate->user);
        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->post(route('invitations.accept', $token))
            ->assertRedirect(route('invitations.show', $token));

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertSee('expired');

        $this->assertDatabaseCount('admissions', 0);
        $this->assertSame(0, $invitation->refresh()->uses_count);
    }

    public function test_exhausted_invitation_is_not_reported_as_active_to_group_managers(): void
    {
        $this->withoutVite();
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Managed group', null);
        GroupInvitation::factory()
            ->for($group)
            ->for($owner, 'inviter')
            ->exhausted()
            ->create();

        Livewire::actingAs($owner->user)
            ->test(GroupInvitations::class, ['group' => $group])
            ->assertSee(__('ui.invitations.exhausted'));
    }

    public function test_admission_does_not_grant_group_access_but_finalized_membership_does(): void
    {
        $this->withoutVite();
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Membership boundary group', null);
        $token = GroupInvitation::issueToken();
        GroupInvitation::factory()
            ->for($group)
            ->for($owner, 'inviter')
            ->create(['token' => $token, 'max_uses' => 1, 'uses_count' => 0]);
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);

        $this->actingAs($candidate->user)
            ->get(route('groups.show', $group))
            ->assertForbidden();

        $manager = app(ManageAdmission::class);
        $manager->candidateTransition($admission, $candidate, 'submitted');
        $manager->review($admission, $owner, 'under_review');
        $manager->review($admission, $owner, 'approved');
        app(FinalizeAdmission::class)->execute($admission);

        $this->actingAs($candidate->user)
            ->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee($group->name);
    }

    public function test_manager_authority_does_not_cross_group_boundaries(): void
    {
        $this->withoutVite();
        $unrelatedOwner = Actor::factory()->create();
        app(CreateGroup::class)->execute($unrelatedOwner, 'Unrelated managed group', null);

        $targetOwner = Actor::factory()->create();
        $targetGroup = app(CreateGroup::class)->execute($targetOwner, 'Target group', null);
        $token = GroupInvitation::issueToken();
        GroupInvitation::factory()
            ->for($targetGroup)
            ->for($targetOwner, 'inviter')
            ->create(['token' => $token]);
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);

        $this->actingAs($unrelatedOwner->user)
            ->get(route('admissions.show', $admission))
            ->assertForbidden();
    }

    public function test_invitation_acceptance_endpoint_rate_limits_repeated_mutation_attempts(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Rate limited group', null);
        $token = GroupInvitation::issueToken();
        GroupInvitation::factory()
            ->for($group)
            ->for($owner, 'inviter')
            ->unlimited()
            ->create(['token' => $token]);
        $candidate = Actor::factory()->create();

        $this->actingAs($candidate->user);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('invitations.accept', $token))->assertRedirect();
        }

        $this->post(route('invitations.accept', $token))->assertStatus(429);
    }
}
