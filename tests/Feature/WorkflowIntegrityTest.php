<?php

namespace Tests\Feature;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\FinalizeAdmission;
use App\Actions\Groups\ManageAdmission;
use App\Actions\Groups\ManageGroupAgreement;
use App\Actions\Groups\RedeemGroupInvitation;
use App\Actions\Groups\TransitionGroupMembership;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Groups\Agreements;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use App\Models\MembershipAgreementAcceptance;
use App\Support\AgreementEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_email_is_masked_across_every_public_invitation_page(): void
    {
        $this->withoutVite();
        [, , $token] = $this->invitation('private.person@example.com');

        $this->get(route('invitations.show', $token))->assertOk()
            ->assertSee('p•••••••••••••@example.com')
            ->assertDontSee('private.person@example.com');

        Livewire::test(Login::class, ['token' => $token])
            ->assertSet('email', '')
            ->assertSee('p•••••••••••••@example.com')
            ->assertDontSee('private.person@example.com');

        Livewire::test(Register::class, ['token' => $token])
            ->assertSet('email', '')
            ->assertSee('p•••••••••••••@example.com')
            ->assertDontSee('private.person@example.com');
    }

    public function test_duplicate_redemption_remains_idempotent_after_invitation_is_exhausted(): void
    {
        [, $invitation, $token] = $this->invitation(maxUses: 1);
        $candidate = Actor::factory()->create();
        $redemption = app(RedeemGroupInvitation::class);
        $first = $redemption->execute($token, $candidate, $candidate->user->email);
        $second = $redemption->execute($token, $candidate, $candidate->user->email);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $invitation->refresh()->uses_count);
        $this->assertDatabaseCount('group_invitation_acceptances', 1);
        $this->assertDatabaseCount('admissions', 1);
    }

    public function test_closed_application_requires_a_new_invitation_and_preserves_both_histories(): void
    {
        [$owner, $firstInvitation, $firstToken] = $this->invitation();
        $candidate = Actor::factory()->create();
        $redemption = app(RedeemGroupInvitation::class);
        $first = $redemption->execute($firstToken, $candidate, $candidate->user->email);
        app(ManageAdmission::class)->candidateTransition($first, $candidate, 'cancelled', 'Not ready yet.');

        try {
            $redemption->execute($firstToken, $candidate, $candidate->user->email);
            $this->fail('A consumed invitation reopened a closed application.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $secondToken = GroupInvitation::issueToken();
        $secondInvitation = GroupInvitation::factory()->for($firstInvitation->group)->for($owner, 'inviter')->create(['token' => $secondToken]);
        $second = $redemption->execute($secondToken, $candidate, $candidate->user->email);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('cancelled', $first->refresh()->status);
        $this->assertSame('draft', $second->status);
        $this->assertSame($secondInvitation->id, $second->source_invitation_id);
        $this->assertDatabaseCount('admissions', 2);
    }

    public function test_duplicate_admission_transitions_create_one_event_and_conflicting_review_is_rejected(): void
    {
        [$owner, , $token] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);
        $manager = app(ManageAdmission::class);
        $manager->candidateTransition($admission, $candidate, 'submitted', 'Ready for review.');
        $manager->candidateTransition($admission, $candidate, 'submitted', 'Duplicate click.');
        $manager->review($admission, $owner, 'under_review');
        $manager->review($admission, $owner, 'under_review');
        $manager->review($admission, $owner, 'approved', 'Accepted.');

        $this->assertSame(1, $admission->events()->where('event', 'admission.submitted')->count());
        $this->assertSame(1, $admission->events()->where('event', 'admission.under_review')->count());

        $this->expectException(ValidationException::class);
        $manager->review($admission, $owner, 'rejected', 'Conflicting outcome.');
    }

    public function test_acceptance_captures_the_complete_immutable_version_evidence_contract(): void
    {
        [$owner, $invitation, $token] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);
        $version = $this->activeAgreementVersion($invitation->group, $owner);
        app(ManageAdmission::class)->accept($admission, $candidate, $version);

        $acceptance = $admission->acceptances()->sole();
        $this->assertSame($version->group_agreement_id, $acceptance->group_agreement_id);
        $this->assertSame($version->version, $acceptance->version_number);
        $this->assertSame($version->content_hash, $acceptance->evidence_hash);
        $this->assertSame('sha256', $acceptance->hash_algorithm);
        $this->assertTrue($acceptance->required_for_admission);
        $this->assertTrue($acceptance->reacceptance_required);
        $this->assertSame($candidate->id, $acceptance->represented_actor_id);
        $this->assertSame($candidate->user_id, $acceptance->acting_user_id);
        $this->assertSame(1, $acceptance->evidence_schema_version);

        $this->expectException(HttpException::class);
        $acceptance->update(['version_number' => 999]);
    }

    public function test_readmission_records_fresh_acceptance_evidence_for_the_new_membership_period(): void
    {
        [$owner, $invitation, $token] = $this->invitation();
        $candidate = Actor::factory()->create();
        $version = $this->activeAgreementVersion($invitation->group, $owner);
        $firstAdmission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);
        $this->approveAndAccept($firstAdmission, $owner, $candidate, $version);
        $membership = app(FinalizeAdmission::class)->execute($firstAdmission);
        app(TransitionGroupMembership::class)->remove($membership, $owner, 'Participation ended.');

        $secondToken = GroupInvitation::issueToken();
        GroupInvitation::factory()->for($invitation->group)->for($owner, 'inviter')->create(['token' => $secondToken]);
        $secondAdmission = app(RedeemGroupInvitation::class)->execute($secondToken, $candidate, $candidate->user->email);
        $this->approveAndAccept($secondAdmission, $owner, $candidate, $version);
        $readmittedMembership = app(FinalizeAdmission::class)->execute($secondAdmission);

        $this->assertSame($membership->id, $readmittedMembership->id);
        $this->assertSame(2, MembershipAgreementAcceptance::query()->where('group_membership_id', $membership->id)->count());
        $this->assertSame(2, MembershipAgreementAcceptance::query()->where('group_membership_id', $membership->id)->distinct()->count('source_admission_acceptance_id'));
    }

    public function test_nonexistent_daylight_saving_local_time_is_rejected(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Toronto Group', null, 'America/Toronto');
        $version = $this->approvedAgreementVersion($group, $owner);

        Livewire::actingAs($owner->user)->test(Agreements::class, ['group' => $group])
            ->set('effectiveFrom', '2027-03-14T02:30')
            ->call('schedule', $version->id)
            ->assertHasErrors('effectiveFrom');

        $this->assertSame('approved', $version->refresh()->status);
    }

    public function test_scheduled_agreement_cannot_be_activated_early(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Scheduled Group', null);
        $version = $this->approvedAgreementVersion($group, $owner);
        $manager = app(ManageGroupAgreement::class);
        $manager->schedule($version, $owner, now()->addDay());

        $this->expectException(HttpException::class);
        $manager->activate($version, $owner);
    }

    public function test_lifecycle_status_cannot_be_mutated_outside_the_agreement_action(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Lifecycle Group', null);
        $agreement = app(ManageGroupAgreement::class)->create($group, $owner, 'Rules', true, 'Terms');
        $version = $agreement->versions()->sole();

        $this->expectException(HttpException::class);
        $version->update(['status' => 'active']);
    }

    public function test_admission_status_cannot_be_mutated_outside_its_lifecycle(): void
    {
        [, , $token] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);

        $this->expectException(HttpException::class);
        $admission->update(['status' => 'approved']);
    }

    public function test_finalization_rejects_an_acceptance_with_forged_version_evidence(): void
    {
        [$owner, $invitation, $token] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($token, $candidate, $candidate->user->email);
        $version = $this->activeAgreementVersion($invitation->group, $owner);
        $manager = app(ManageAdmission::class);
        $manager->candidateTransition($admission, $candidate, 'submitted');
        $manager->review($admission, $owner, 'under_review');
        $manager->review($admission, $owner, 'approved');
        AgreementAcceptance::create([
            'admission_id' => $admission->id,
            'group_agreement_version_id' => $version->id,
            ...AgreementEvidence::forAcceptance($version, $candidate),
            'evidence_hash' => str_repeat('0', 64),
        ]);

        $this->expectException(ValidationException::class);
        app(FinalizeAdmission::class)->execute($admission);
    }

    /** @return array{Actor, GroupInvitation, string} */
    private function invitation(?string $email = null, int $maxUses = 10): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Inviting Group', null);
        $token = GroupInvitation::issueToken();
        $invitation = GroupInvitation::factory()->for($group)->for($owner, 'inviter')->create(['email' => $email, 'token' => $token, 'max_uses' => $maxUses]);

        return [$owner, $invitation, $token];
    }

    private function activeAgreementVersion(Group $group, Actor $owner): GroupAgreementVersion
    {
        $version = $this->approvedAgreementVersion($group, $owner);
        app(ManageGroupAgreement::class)->activate($version, $owner);

        return $version->refresh();
    }

    private function approvedAgreementVersion(Group $group, Actor $owner): GroupAgreementVersion
    {
        $manager = app(ManageGroupAgreement::class);
        $agreement = $manager->create($group, $owner, 'Required rules', true, "Terms\r\nwith trailing space.   ");
        $version = $agreement->versions()->sole();
        $manager->propose($version, $owner);
        $manager->approve($version, $owner);

        return $version->refresh();
    }

    private function approveAndAccept(Admission $admission, Actor $owner, Actor $candidate, GroupAgreementVersion $version): void
    {
        $manager = app(ManageAdmission::class);
        $manager->candidateTransition($admission, $candidate, 'submitted');
        $manager->review($admission, $owner, 'under_review');
        $manager->review($admission, $owner, 'approved');
        $manager->accept($admission, $candidate, $version);
    }
}
