<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileDisclosureGrant;
use App\Models\ActorProfileDisclosureItem;
use App\Models\ActorProfileIntent;
use App\Models\AiAssistanceRun;
use App\Models\Admission;
use App\Models\AdmissionContext;
use App\Models\AdmissionEvent;
use App\Models\AgreementAcceptance;
use App\Models\AgreementEvent;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\ContentEvidenceReference;
use App\Models\DevelopmentOrigin;
use App\Models\Context;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use App\Models\GroupInvitationAcceptance;
use App\Models\GroupMembership;
use App\Models\GroupMembershipEvent;
use App\Models\GroupRoleChangeRequest;
use App\Models\GroupSpace;
use App\Models\GroupSpaceContext;
use App\Models\GroupSpaceMessage;
use App\Models\GroupSpaceParticipant;
use App\Models\MembershipAgreementAcceptance;
use App\Models\PersonalContext;
use App\Models\PlatformAccessGrant;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
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
            ActorProfile::factory()->create(),
            ActorProfileIntent::factory()->create(),
            ActorProfileDisclosureGrant::factory()->create(),
            ActorProfileDisclosureItem::factory()->create(),
            AiAssistanceRun::factory()->create(),
            Context::factory()->create(),
            ContentBlueprint::factory()->create(),
            ContentBlueprintVersion::factory()->create(),
            ContentEvidenceReference::factory()->create(),
            DevelopmentOrigin::factory()->create(),
            PersonalContext::factory()->create(),
            GroupSpaceContext::factory()->create(),
            AdmissionContext::factory()->create(),
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
            GroupSpaceParticipant::factory()->create(),
            SpaceContentDefinition::factory()->create(),
            SpaceContentDefinitionVersion::factory()->create(),
            SpaceContent::factory()->create(),
            SpaceContentRevision::factory()->create(),
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
        $this->assertSame('archived', GroupSpace::factory()->archived()->create()->status);
        $this->assertSame('restricted', GroupSpace::factory()->restricted()->create()->access_mode);
        $this->assertSame('deny', GroupSpaceParticipant::factory()->denied()->create()->access);
        $this->assertSame('manager', GroupSpaceParticipant::factory()->manager()->create()->role);
        $this->assertSame('active', SpaceContentDefinition::factory()->active()->create()->status);
        $this->assertSame('archived', SpaceContent::factory()->archived()->create()->status);
        $this->assertNotNull(PlatformAccessGrant::factory()->revoked()->create()->revoked_at);
        $this->assertNotNull(ActorProfileDisclosureGrant::factory()->revoked()->create()->revoked_at);
        $this->assertFalse(ActorProfileDisclosureGrant::factory()->expired()->create()->isActive());
    }
}
