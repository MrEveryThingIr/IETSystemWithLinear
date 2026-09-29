<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\ProposalStatus;
use App\Support\DealPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalDealPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_projects_internal_records_into_one_user_facing_progression(): void
    {
        $pipeline = app(DealPipeline::class);

        $connection = Relationship::factory()->create();
        $this->assertSame('connect', $pipeline->stage($connection->load(['proposals', 'contracts'])));

        $negotiating = Relationship::factory()->active()->create();
        $this->assertSame('negotiate', $pipeline->stage($negotiating->load(['proposals', 'contracts'])));

        Proposal::factory()->create([
            'relationship_id' => $negotiating->id,
            'status' => ProposalStatus::Accepted,
        ]);

        $this->assertSame(
            'agree',
            $pipeline->stage($negotiating->fresh()->load(['proposals', 'contracts'])),
        );

        Contract::factory()->create([
            'relationship_id' => $negotiating->id,
            'status' => ContractStatus::Active,
        ]);

        $this->assertSame(
            'work',
            $pipeline->stage($negotiating->fresh()->load(['proposals', 'contracts'])),
        );
    }

    public function test_standalone_proposal_and_contract_browser_creation_redirects_to_deals(): void
    {
        $actor = Actor::factory()->create();

        $this->actingAs($actor->user)
            ->get(route('proposals.create'))
            ->assertRedirect(route('deals.index'));

        $this->actingAs($actor->user)
            ->get(route('contracts.create'))
            ->assertRedirect(route('deals.index'));
    }

    public function test_deals_page_is_the_primary_pipeline_view(): void
    {
        $owner = Actor::factory()->create();
        $other = Actor::factory()->create();

        $relationship = Relationship::factory()->active()->create([
            'title' => 'Machine inspection job',
            'created_by_actor_id' => $owner->id,
        ]);

        RelationshipParticipant::factory()->manager()->create([
            'relationship_id' => $relationship->id,
            'actor_id' => $owner->id,
            'invited_by_actor_id' => $owner->id,
        ]);

        RelationshipParticipant::factory()->active()->create([
            'relationship_id' => $relationship->id,
            'actor_id' => $other->id,
            'invited_by_actor_id' => $owner->id,
        ]);

        $this->actingAs($owner->user)
            ->get(route('deals.index'))
            ->assertOk()
            ->assertSee('Machine inspection job')
            ->assertSee(__('deals.stages.negotiate'))
            ->assertSee(__('deals.actions.proposal'));
    }
}
