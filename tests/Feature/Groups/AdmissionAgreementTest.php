<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\FinalizeAdmission;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\RedeemGroupInvitation;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdmissionAgreementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_redemption_creates_one_resumable_admission_without_membership(): void
    {
        $owner = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = Group::create(['name' => 'Group', 'created_by_actor_id' => $owner->id]);
        $invitation = GroupInvitation::create(['group_id' => $group->id, 'invited_by_actor_id' => $owner->id, 'token' => 'admission-token', 'uses_count' => 0]);
        $redemption = app(RedeemGroupInvitation::class);
        $first = $redemption->execute($invitation->token, $candidate, $candidate->user->email);
        $second = $redemption->execute($invitation->token, $candidate, $candidate->user->email);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('draft', $first->status);
        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertSame(1, $invitation->refresh()->uses_count);
    }

    public function test_finalization_requires_active_required_versions_to_be_accepted(): void
    {
        [, $admission] = $this->approvedAdmissionWithRequiredVersion();

        $this->expectException(ValidationException::class);

        app(FinalizeAdmission::class)->execute($admission);
    }

    public function test_finalization_reactivates_membership_only_after_approval_and_acceptance(): void
    {
        [$candidate, $admission, $version] = $this->approvedAdmissionWithRequiredVersion();
        AgreementAcceptance::create([
            'admission_id' => $admission->id,
            'group_agreement_version_id' => $version->id,
            'accepted_by_actor_id' => $candidate->id,
            'accepted_at' => now(),
            'evidence_hash' => hash('sha256', 'Terms'),
        ]);

        $membership = app(FinalizeAdmission::class)->execute($admission);

        $this->assertSame('active', $membership->status);
        $this->assertSame('finalized', $admission->refresh()->status);
        $this->assertNotNull($admission->finalized_at);
        $this->assertDatabaseHas('membership_agreement_acceptances', [
            'group_membership_id' => $membership->id,
            'group_agreement_version_id' => $version->id,
            'accepted_by_actor_id' => $candidate->id,
        ]);
        $this->assertDatabaseHas('admission_events', ['admission_id' => $admission->id, 'event' => 'admission.finalized']);

        $this->expectException(HttpException::class);

        app(FinalizeAdmission::class)->execute($admission);
    }

    /** @return array{Actor, Admission, GroupAgreementVersion} */
    private function approvedAdmissionWithRequiredVersion(): array
    {
        $owner = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = Group::create(['name' => 'Group', 'created_by_actor_id' => $owner->id]);
        $roles = app(GroupRoleProvisioner::class);
        $group->memberships()->create(['actor_id' => $owner->id, 'status' => 'active']);
        $roles->grant($owner, $group, $roles->provision($group)['owner']);
        $admission = Admission::create(['group_id' => $group->id, 'candidate_actor_id' => $candidate->id, 'status' => 'approved']);
        $agreement = GroupAgreement::create(['group_id' => $group->id, 'name' => 'Rules', 'required_for_admission' => true]);
        $version = GroupAgreementVersion::create(['group_agreement_id' => $agreement->id, 'version' => 1, 'content' => 'Terms', 'status' => 'active']);

        return [$candidate, $admission, $version];
    }
}
