<?php

namespace Tests\Feature;

use App\Models\AccessInvitation;
use App\Models\AccessInvitationAcceptance;
use App\Models\Account;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileDisclosureGrant;
use App\Models\ActorProfileDisclosureItem;
use App\Models\ActorProfileIntent;
use App\Models\Admission;
use App\Models\AdmissionContext;
use App\Models\AdmissionEvent;
use App\Models\AgreementAcceptance;
use App\Models\AgreementEvent;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\CommitmentPlanBinding;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\ContentEvidenceReference;
use App\Models\ContentPlacement;
use App\Models\Context;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractContext;
use App\Models\ContractEvent;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\DomainBlueprint;
use App\Models\DomainBlueprintVersion;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\FulfillmentReview;
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
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Ledger;
use App\Models\MembershipAgreementAcceptance;
use App\Models\MonetaryUnit;
use App\Models\PersonalContext;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\PlanParticipant;
use App\Models\PlanReminder;
use App\Models\PlanScheduleRule;
use App\Models\PlatformAccessGrant;
use App\Models\Proposal;
use App\Models\ProposalContext;
use App\Models\ProposalDecision;
use App\Models\ProposalEvent;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\Models\RelationshipContext;
use App\Models\RelationshipEvent;
use App\Models\RelationshipParticipant;
use App\Models\Settlement;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\Story;
use App\Models\StoryRole;
use App\Models\User;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
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
            AccessInvitation::factory()->create(),
            AccessInvitationAcceptance::factory()->create(),
            Actor::factory()->create(),
            ActorProfile::factory()->create(),
            ActorProfileIntent::factory()->create(),
            ActorProfileDisclosureGrant::factory()->create(),
            ActorProfileDisclosureItem::factory()->create(),
            Context::factory()->create(),
            Contract::factory()->create(),
            ContractContext::factory()->create(),
            ContractVersion::factory()->create(),
            ContractVersionParty::factory()->create(),
            DomainBlueprint::factory()->create(),
            DomainBlueprintVersion::factory()->create(),
            ContractAcceptance::factory()->create(),
            ContractEvent::factory()->create(),
            Commitment::factory()->create(),
            CommitmentPlanBinding::factory()->create(),
            CommitmentEvent::factory()->create(),
            Fulfillment::factory()->create(),
            FulfillmentReview::factory()->create(),
            FulfillmentDispute::factory()->create(),
            FinancialObligation::factory()->create(),
            FinancialObligationEvent::factory()->create(),
            Settlement::factory()->create(),
            MonetaryUnit::factory()->create(),
            Ledger::factory()->create(),
            Account::factory()->create(),
            JournalEntry::factory()->create(),
            JournalLine::factory()->create(),
            Conversation::factory()->create(),
            ConversationMessage::factory()->create(),
            ContentBlueprint::factory()->create(),
            ContentBlueprintVersion::factory()->create(),
            ContentEvidenceReference::factory()->create(),
            ContentPlacement::factory()->create(),
            PersonalContext::factory()->create(),
            Plan::factory()->create(),
            PlanParticipant::factory()->create(),
            PlanScheduleRule::factory()->create(),
            PlanReminder::factory()->create(),
            PlanOccurrence::factory()->create(),
            PlanEvent::factory()->create(),
            PlanOccurrenceEvent::factory()->create(),
            GroupSpaceContext::factory()->create(),
            AdmissionContext::factory()->create(),
            Relationship::factory()->create(),
            RelationshipParticipant::factory()->create(),
            RelationshipContext::factory()->create(),
            RelationshipEvent::factory()->create(),
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
            Proposal::factory()->create(),
            ProposalParty::factory()->create(),
            ProposalContext::factory()->create(),
            ProposalVersion::factory()->create(),
            ProposalDecision::factory()->create(),
            ProposalEvent::factory()->create(),
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
        $this->assertSame('removed', ContentPlacement::factory()->removed()->create()->status);
        $this->assertSame(RelationshipStatus::Ended, Relationship::factory()->ended()->create()->status);
        $this->assertSame(
            RelationshipParticipantStatus::Declined,
            RelationshipParticipant::factory()->declined()->create()->status,
        );
        $this->assertNotNull(PlatformAccessGrant::factory()->revoked()->create()->revoked_at);
        $this->assertNotNull(ActorProfileDisclosureGrant::factory()->revoked()->create()->revoked_at);
        $this->assertFalse(ActorProfileDisclosureGrant::factory()->expired()->create()->isActive());
    }
}
