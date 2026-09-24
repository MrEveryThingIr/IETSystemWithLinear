<?php

namespace Tests\Feature;

use App\Actions\Conversations\PostContextMessage;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\ProposeTermsVersion;
use App\Actions\Proposals\RespondToProposal;
use App\ContextKind;
use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalEvent;
use App\Models\ProposalVersion;
use App\ProposalDecisionKind;
use App\ProposalEventType;
use App\ProposalStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ProposalNegotiationKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_alice_bob_and_carol_negotiate_exact_versions_without_creating_contract_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user,
            'Riverside construction collaboration',
            [
                ['actor' => $bob, 'role' => 'builder'],
                ['actor' => $carol, 'role' => 'project partner'],
            ],
            'Alice will coordinate Riverside construction. Bob will provide construction work and Carol will coordinate site requirements.',
            'Riverside collaboration proposal',
        );

        $proposal->load(['parties.actor', 'contextBinding.context', 'versions.termsRevision.content']);

        $this->assertSame(ProposalStatus::Negotiating, $proposal->status);
        $this->assertNull($proposal->relationship_id);
        $this->assertCount(3, $proposal->parties);
        $this->assertSame(ContextKind::Negotiation, $proposal->contextBinding->context->kind);
        $this->assertDatabaseCount('relationships', 0);
        $this->assertDatabaseCount('plans', 0);
        $this->assertDatabaseCount('ledgers', 0);

        $versionOne = $proposal->currentVersionRecord();
        $this->assertInstanceOf(ProposalVersion::class, $versionOne);
        $this->assertSame(1, $versionOne->version);
        $this->assertTrue($versionOne->termsRevision->hasVerifiableManifest());
        $this->assertSame(
            $proposal->contextBinding->context_id,
            $versionOne->termsRevision->content->context_id,
        );

        $aliceParty = $proposal->parties->firstWhere('actor_id', $alice->id);
        $this->assertNotNull($aliceParty);
        $this->assertDatabaseHas('proposal_decisions', [
            'proposal_version_id' => $versionOne->id,
            'proposal_party_id' => $aliceParty->id,
            'decision' => ProposalDecisionKind::Accepted->value,
        ]);

        $this->assertTrue($alice->user->can('view', $proposal));
        $this->assertTrue($bob->user->can('view', $proposal));
        $this->assertTrue($carol->user->can('view', $proposal));

        app(PostContextMessage::class)->execute(
            $proposal->contextBinding->context,
            $bob->user,
            'The work scope is understandable, but Carol should review the site coordination terms.',
        );

        app(RespondToProposal::class)->execute(
            $proposal,
            $bob->user,
            ProposalDecisionKind::Accepted,
            'Bob accepts version 1.',
        );

        app(RespondToProposal::class)->execute(
            $proposal,
            $carol->user,
            ProposalDecisionKind::ChangesRequested,
            'Please clarify the site coordination responsibilities.',
        );

        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);

        $versionTwo = app(ProposeTermsVersion::class)->execute(
            $proposal,
            $carol->user,
            'Riverside construction collaboration — revised terms',
            'Alice coordinates Riverside construction. Bob provides construction work. Carol coordinates site access, drawings, and daily site requirements.',
            'Revised after Carol requested clearer site responsibilities.',
            versionNote: 'Carol counter-proposal',
        );

        $this->assertSame(2, $versionTwo->version);
        $this->assertTrue($versionTwo->termsRevision->hasVerifiableManifest());
        $this->assertSame(5, ProposalEvent::query()->count());

        app(RespondToProposal::class)->execute(
            $proposal,
            $alice->user,
            ProposalDecisionKind::Accepted,
            'Alice accepts version 2.',
        );

        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);

        app(RespondToProposal::class)->execute(
            $proposal,
            $bob->user,
            ProposalDecisionKind::Accepted,
            'Bob accepts version 2.',
        );

        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
        $this->assertSame(6, ProposalDecision::query()->count());

        $this->assertDatabaseHas('proposal_decisions', [
            'proposal_version_id' => $versionOne->id,
            'decision' => ProposalDecisionKind::ChangesRequested->value,
        ]);
        $this->assertDatabaseHas('proposal_decisions', [
            'proposal_version_id' => $versionTwo->id,
            'decision' => ProposalDecisionKind::Accepted->value,
        ]);

        $this->assertDatabaseCount('plans', 0);
        $this->assertDatabaseCount('ledgers', 0);

        $this->expectException(AuthorizationException::class);
        app(PostContextMessage::class)->execute(
            $proposal->contextBinding->context,
            $alice->user,
            'Accepted negotiation is now read-only.',
        );
    }

    public function test_old_version_acceptance_never_counts_for_new_version(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user,
            'Paid workshop help',
            [['actor' => $bob, 'role' => 'worker']],
            'Bob may help Alice with workshop work for the terms stated here.',
        );

        app(RespondToProposal::class)->execute(
            $proposal,
            $bob->user,
            ProposalDecisionKind::ChangesRequested,
            'Please revise the work window.',
        );

        $versionTwo = app(ProposeTermsVersion::class)->execute(
            $proposal,
            $alice->user,
            'Paid workshop help — version 2',
            'Bob may help Alice with workshop work during the revised work window.',
        );

        $this->assertSame(2, $versionTwo->version);
        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);

        $bobParty = $proposal->parties()->where('actor_id', $bob->id)->sole();
        $this->assertDatabaseMissing('proposal_decisions', [
            'proposal_version_id' => $versionTwo->id,
            'proposal_party_id' => $bobParty->id,
        ]);

        app(RespondToProposal::class)->execute(
            $proposal,
            $bob->user,
            ProposalDecisionKind::Accepted,
        );

        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
    }

    public function test_rejection_is_explicit_terminal_proposal_state(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user,
            'Direct service proposal',
            [['actor' => $bob, 'role' => 'recipient']],
            'Alice proposes to provide the described service to Bob.',
        );

        app(RespondToProposal::class)->execute(
            $proposal,
            $bob->user,
            ProposalDecisionKind::Rejected,
            'Bob does not want these terms.',
        );

        $this->assertSame(ProposalStatus::Rejected, $proposal->fresh()->status);
        $this->assertDatabaseHas('proposal_events', [
            'proposal_id' => $proposal->id,
            'event_type' => ProposalEventType::Rejected->value,
        ]);

        $this->expectException(AuthorizationException::class);
        app(ProposeTermsVersion::class)->execute(
            $proposal,
            $alice->user,
            'Rejected proposal revision',
            'This must not be created after rejection.',
        );
    }

    public function test_outsider_cannot_read_or_participate_in_negotiation_context(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user,
            'Private direct proposal',
            [['actor' => $bob]],
            'Private proposed terms.',
        );

        $context = $proposal->contextBinding()->with('context')->firstOrFail()->context;

        $this->assertFalse($outsider->user->can('view', $proposal));
        $this->assertFalse($outsider->user->can('view', $context));
        $this->assertFalse($outsider->user->can('interactContent', $context));

        $this->expectException(AuthorizationException::class);
        app(PostContextMessage::class)->execute(
            $context,
            $outsider->user,
            'I should not be able to enter this negotiation.',
        );
    }

    public function test_proposal_versions_and_decisions_are_immutable_history(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user,
            'Immutable proposal',
            [['actor' => $bob]],
            'Terms that will be sealed.',
        );

        $version = $proposal->currentVersionRecord();
        $this->assertInstanceOf(ProposalVersion::class, $version);

        $this->expectException(LogicException::class);
        $version->update(['note' => 'Mutated']);
    }
}
