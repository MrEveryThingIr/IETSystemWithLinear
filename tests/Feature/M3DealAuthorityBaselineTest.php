<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\ProposeTermsVersion;
use App\Actions\Proposals\RespondToProposal;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\ContractAcceptance;
use App\Models\ProposalDecision;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M3-C1 characterization. These pin what the integrated code does today so that
 * M3-C2/C3 change it deliberately. Each test names the docs/development/M3-C1-ARCHITECTURE.md
 * finding it records and states what the checkpoint that fixes it must flip.
 */
class M3DealAuthorityBaselineTest extends TestCase
{
    use RefreshDatabase;

    /** G1: acceptance is recorded against the latest version, not the version the person saw. C3 adds an expected-version argument. */
    public function test_acceptance_lands_on_latest_version_even_if_the_responder_saw_an_older_one(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user, 'Garden help', [['actor' => $bob, 'role' => 'worker']], 'Version one terms.',
        );
        $versionTwo = app(ProposeTermsVersion::class)->execute($proposal, $alice->user, 'Garden help v2', 'Version two terms, higher price.');

        // Bob "saw" version one, but the action has no way to say so.
        $decision = app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted);

        $this->assertSame($versionTwo->id, $decision->proposal_version_id);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
    }

    /** G2: after the Proposal is accepted every other party must accept again as a ContractVersion. C2/C3 derive that acceptance from the Proposal decision. */
    public function test_accepted_proposal_still_needs_a_second_acceptance_on_the_contract_version(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user, 'Garden help', [['actor' => $bob, 'role' => 'worker']], 'Terms.',
        );
        app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted);
        $this->assertSame(2, ProposalDecision::query()->where('decision', ProposalDecisionKind::Accepted->value)->count());

        $contract = app(CreateContractFromProposal::class)->execute($proposal->fresh(), $alice->user, now()->addMinutes(5), 'UTC');
        $version = $contract->versions->sole();

        $this->assertSame(ContractVersionStatus::Proposed, $version->status);
        $this->assertSame(1, ContractAcceptance::query()->count(), 'only the proposer is carried over');

        app(AcceptContractVersion::class)->execute($version, $bob->user);

        $this->assertSame(2, ContractAcceptance::query()->count());
        $this->assertNotSame(ContractVersionStatus::Proposed, $version->fresh()->status);
    }

    /** G3: parties are Actors only. C3 adds business_id/group_id to both party tables. */
    public function test_party_tables_have_no_business_or_group_reference_yet(): void
    {
        foreach (['proposal_parties', 'contract_version_parties'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'business_id'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'group_id'), $table);
        }
    }
}
