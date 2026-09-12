<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AdmissionEvent;
use App\Models\AgreementAcceptance;
use App\Models\AgreementEvent;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use App\Models\GroupInvitationAcceptance;
use App\Models\GroupMembership;
use App\Models\GroupMembershipEvent;
use App\Models\GroupRoleChangeRequest;
use App\Models\GroupSpace;
use App\Models\GroupSpaceMessage;
use App\Models\MembershipAgreementAcceptance;
use App\Models\PlatformAccessGrant;
use App\Models\Story;
use App\Models\StoryRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModelFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_persisted_application_model_has_a_working_factory(): void
    {
        $models = [
            User::factory()->create(),
            Actor::factory()->create(),
            Group::factory()->create(),
            GroupMembership::factory()->create(),
            GroupMembershipEvent::factory()->create(),
            GroupInvitation::factory()->create(),
            GroupInvitationAcceptance::factory()->create(),
            Admission::factory()->create(),
            AdmissionEvent::factory()->create(),
            GroupAgreement::factory()->create(),
            GroupAgreementVersion::factory()->create(),
            AgreementAcceptance::factory()->create(),
            AgreementEvent::factory()->create(),
            MembershipAgreementAcceptance::factory()->create(),
            GroupRoleChangeRequest::factory()->create(),
            GroupSpace::factory()->create(),
            GroupSpaceMessage::factory()->create(),
            Story::factory()->create(),
            StoryRole::factory()->create(),
            PlatformAccessGrant::factory()->create(),
        ];

        foreach ($models as $model) {
            $this->assertInstanceOf(Model::class, $model);
            $this->assertModelExists($model);
        }
    }

    public function test_lifecycle_factories_create_the_named_edge_states(): void
    {
        $this->assertSame('clarification_required', Admission::factory()->clarificationRequired()->create()->status);
        $this->assertSame('finalized', Admission::factory()->finalized()->create()->status);
        $this->assertSame('rejected', Admission::factory()->rejected()->create()->status);
        $this->assertSame('cancelled', Admission::factory()->cancelled()->create()->status);
        $this->assertSame('scheduled', GroupAgreementVersion::factory()->scheduled()->create()->status);
        $this->assertSame('rejected', GroupAgreementVersion::factory()->rejected()->create()->status);
        $this->assertNotNull(GroupInvitation::factory()->expired()->create()->expires_at);
        $this->assertNotNull(GroupInvitation::factory()->revoked()->create()->revoked_at);
        $this->assertSame(1, GroupInvitation::factory()->exhausted()->create()->uses_count);
        $this->assertSame('removed', GroupMembership::factory()->removed()->create()->status);
        $this->assertSame('suspended', GroupMembership::factory()->suspended()->create()->status);
        $this->assertSame('left', GroupMembership::factory()->left()->create()->status);
        $this->assertSame('suspended', User::factory()->suspended()->create()->status);
        $this->assertSame('closed', User::factory()->closed()->create()->status);
        $this->assertSame('archived', Actor::factory()->withoutUser()->archived()->create()->status);
        $this->assertNotNull(PlatformAccessGrant::factory()->revoked()->create()->revoked_at);
    }
}
