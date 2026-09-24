<?php

namespace Tests\Feature;

use App\ContextKind;
use App\Livewire\Proposals\Create as ProposalCreate;
use App\Livewire\Proposals\Show as ProposalShow;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipContext;
use App\Models\RelationshipParticipant;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use App\Support\ContextTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProposalExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alice_bob_and_carol_complete_a_direct_multi_party_negotiation_in_the_ui(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        Livewire::actingAs($alice->user)
            ->test(ProposalCreate::class)
            ->set('title', 'Riverside construction collaboration')
            ->set('partyUsernames', $bob->user->username.', '.$carol->user->username)
            ->set('summary', 'Coordinate Riverside construction.')
            ->set('terms', 'Alice coordinates the project. Bob provides construction work. Carol coordinates site requirements.')
            ->call('save');

        $proposal = Proposal::query()
            ->with(['contextBinding.context', 'versions.termsRevision'])
            ->sole();

        $this->assertSame(ProposalStatus::Negotiating, $proposal->status);
        $this->assertSame(ContextKind::Negotiation, $proposal->contextBinding->context->kind);
        $this->assertTrue($proposal->versions->sole()->termsRevision->hasVerifiableManifest());

        Livewire::actingAs($bob->user)
            ->test(ProposalShow::class, ['proposal' => $proposal])
            ->assertSee('Riverside construction collaboration')
            ->assertSee('Alice coordinates the project.')
            ->set('responseNote', 'Bob accepts version 1.')
            ->call('accept')
            ->assertHasNoErrors();

        Livewire::actingAs($carol->user)
            ->test(ProposalShow::class, ['proposal' => $proposal])
            ->set('responseNote', 'Clarify site access and drawings.')
            ->call('requestChanges')
            ->assertHasNoErrors()
            ->set('revisionTitle', 'Riverside construction collaboration — revised terms')
            ->set('revisionSummary', 'Revised site coordination responsibilities.')
            ->set('revisionTerms', 'Alice coordinates the project. Bob provides construction work. Carol coordinates site access, drawings, and daily requirements.')
            ->set('versionNote', 'Clarified Carol responsibilities.')
            ->call('proposeVersion')
            ->assertHasNoErrors();

        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);
        $this->assertSame(2, $proposal->versions()->max('version'));

        Livewire::actingAs($alice->user)
            ->test(ProposalShow::class, ['proposal' => $proposal])
            ->call('accept')
            ->assertHasNoErrors();

        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);

        Livewire::actingAs($bob->user)
            ->test(ProposalShow::class, ['proposal' => $proposal])
            ->call('accept')
            ->assertHasNoErrors()
            ->assertSee(__('proposals.status.accepted'));

        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);

        $versionTwo = $proposal->versions()->where('version', 2)->firstOrFail();
        $this->assertSame(
            3,
            $versionTwo->decisions()
                ->where('decision', ProposalDecisionKind::Accepted->value)
                ->count(),
        );

        $context = $proposal->contextBinding->context;
        $entries = app(ContextTimeline::class)->entries($context, $alice->user);

        $this->assertTrue($entries->contains(fn ($entry): bool => $entry->kind === 'proposal'));
        $this->assertTrue($entries->contains(
            fn ($entry): bool => $entry->url === route('proposals.show', $proposal),
        ));

        $this->actingAs($alice->user)
            ->get(route('contexts.conversation', $context))
            ->assertOk()
            ->assertSee(__('collaboration.conversation.read_only'));
    }

    public function test_active_relationship_can_prefill_a_proposal_without_becoming_the_proposal_authority(): void
    {
        config()->set('release.profile', 'ideal_v1');

        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $relationship = Relationship::factory()->active()->create([
            'title' => 'Riverside collaboration',
            'created_by_actor_id' => $alice->id,
        ]);

        RelationshipParticipant::factory()->manager()->create([
            'relationship_id' => $relationship->id,
            'actor_id' => $alice->id,
            'invited_by_actor_id' => $alice->id,
            'role' => 'project owner',
        ]);
        RelationshipParticipant::factory()->active()->create([
            'relationship_id' => $relationship->id,
            'actor_id' => $bob->id,
            'invited_by_actor_id' => $alice->id,
            'role' => 'builder',
        ]);

        $context = Context::factory()->create(['kind' => ContextKind::Relationship]);
        RelationshipContext::factory()->create([
            'relationship_id' => $relationship->id,
            'context_id' => $context->id,
        ]);

        $this->actingAs($alice->user)
            ->get(route('relationships.show', $relationship))
            ->assertOk()
            ->assertSee(route('proposals.create', ['relationship' => $relationship->uuid]), false);

        Livewire::actingAs($alice->user)
            ->withQueryParams(['relationship' => $relationship->uuid])
            ->test(ProposalCreate::class)
            ->assertSet('partyUsernames', $bob->user->username)
            ->set('title', 'Riverside paid-work proposal')
            ->set('terms', 'Bob may perform the described Riverside work under these proposed terms.')
            ->call('save');

        $proposal = Proposal::query()->sole();

        $this->assertSame($relationship->id, $proposal->relationship_id);
        $this->assertNotSame($context->id, $proposal->contextBinding()->firstOrFail()->context_id);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_outsider_cannot_open_proposal_page_or_negotiation_workspace(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();

        Livewire::actingAs($alice->user)
            ->test(ProposalCreate::class)
            ->set('title', 'Private proposal')
            ->set('partyUsernames', $bob->user->username)
            ->set('terms', 'Private proposed terms.')
            ->call('save');

        $proposal = Proposal::query()->with('contextBinding.context')->sole();

        $this->actingAs($outsider->user)
            ->get(route('proposals.show', $proposal))
            ->assertForbidden();

        $this->actingAs($outsider->user)
            ->get(route('contexts.conversation', $proposal->contextBinding->context))
            ->assertForbidden();
    }
}
