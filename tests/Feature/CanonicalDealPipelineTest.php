<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\ContractVersionStatus;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\ProposalStatus;
use App\Support\DealPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class CanonicalDealPipelineTest extends TestCase
{
    use PublishesFeatureSurfaces;
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

        $contract = Contract::factory()->create([
            'relationship_id' => $negotiating->id,
            'status' => ContractStatus::Active,
        ]);

        $version = ContractVersion::factory()->create([
            'contract_id' => $contract->id,
            'status' => ContractVersionStatus::Active,
            'accepted_at' => now(),
            'activated_at' => now(),
        ]);

        $commitment = Commitment::factory()->create([
            'contract_version_id' => $version->id,
        ]);

        $this->assertSame(
            'work',
            $pipeline->stage($negotiating->fresh()->load([
                'proposals',
                'contracts.versions.commitments.fulfillments',
            ])),
        );

        Fulfillment::factory()->create([
            'commitment_id' => $commitment->id,
            'status' => FulfillmentStatus::Submitted,
        ]);

        $this->assertSame(
            'review',
            $pipeline->stage($negotiating->fresh()->load([
                'proposals',
                'contracts.versions.commitments.fulfillments',
            ])),
        );

        $settlementRelationship = Relationship::factory()->active()->create();
        $settlementContract = Contract::factory()->create([
            'relationship_id' => $settlementRelationship->id,
            'status' => ContractStatus::Active,
        ]);
        $settlementVersion = ContractVersion::factory()->create([
            'contract_id' => $settlementContract->id,
            'status' => ContractVersionStatus::Active,
            'accepted_at' => now(),
            'activated_at' => now(),
        ]);
        $settlementCommitment = Commitment::factory()->create([
            'contract_version_id' => $settlementVersion->id,
        ]);
        Fulfillment::factory()->create([
            'commitment_id' => $settlementCommitment->id,
            'status' => FulfillmentStatus::Accepted,
            'reviewed_at' => now(),
        ]);

        $this->assertSame(
            'settle',
            $pipeline->stage($settlementRelationship->fresh()->load([
                'proposals',
                'contracts.versions.commitments.fulfillments',
            ])),
        );
    }

    public function test_standalone_browser_creation_cannot_bypass_the_market_and_deal_pipeline(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['deals']);

        $this->actingAs($actor->user)
            ->get(route('relationships.create'))
            ->assertRedirect(route('intents.index'));

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

        $this->publishSurfaces($owner->user, ['deals']);

        $this->actingAs($owner->user)
            ->get(route('deals.index'))
            ->assertOk()
            ->assertSee('Machine inspection job')
            ->assertSee(__('deals.stages.negotiate'))
            ->assertSee(__('deals.actions.proposal'));
    }
}
