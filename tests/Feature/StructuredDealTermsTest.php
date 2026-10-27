<?php

namespace Tests\Feature;

use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\ProposeTermsVersion;
use App\Actions\Proposals\RespondToProposal;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use App\Support\Deals\DealTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StructuredDealTermsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function terms(array $override = []): array
    {
        return array_replace_recursive([
            'schema' => 1,
            'price' => ['min' => '100.50', 'max' => '120', 'currency' => 'irt', 'basis' => 'per day'],
            'place' => ' Tehran ',
            'time' => ['starts_on' => '2026-11-20', 'ends_on' => '2026-11-22'],
            'quantity' => ['value' => '03', 'unit' => 'days'],
            'fields' => [['key' => 'b', 'label' => 'Tools', 'value' => 'Own'], ['key' => 'a', 'label' => 'Insurance', 'value' => 'Yes']],
            'notes' => 'Bring ID.',
        ], $override);
    }

    public function test_normalization_is_canonical_and_hash_is_order_independent(): void
    {
        $a = DealTerms::normalize($this->terms());
        $b = DealTerms::normalize(array_reverse($this->terms(), true) + ['fields' => array_reverse($this->terms()['fields'])]);

        $this->assertSame('IRT', $a['price']['currency']);
        $this->assertSame('100.5', $a['price']['min']);
        $this->assertSame('3', $a['quantity']['value']);
        $this->assertSame('Tehran', $a['place']);
        $this->assertSame(['on_acceptance'], array_values($a['effective']));
        $this->assertSame(['a', 'b'], array_column($a['fields'], 'key'));
        $this->assertSame(DealTerms::hash($a), DealTerms::hash($b));
        $this->assertNotSame(DealTerms::hash($a), DealTerms::hash(DealTerms::normalize($this->terms(['price' => ['max' => '121']]))));
    }

    public function test_invalid_terms_are_rejected(): void
    {
        $bad = [
            ['schema' => 2],
            $this->terms(['surprise' => 1]),
            $this->terms(['price' => ['min' => '200']]),
            $this->terms(['price' => ['min' => '1.5e3']]),
            $this->terms(['price' => ['currency' => null]]),
            $this->terms(['time' => ['ends_on' => '2026-11-01']]),
            $this->terms(['effective' => ['mode' => 'at']]),
            $this->terms(['fields' => [['key' => 'x y', 'value' => 'v']]]),
            $this->terms(['fields' => [['key' => 'k', 'value' => 'v'], ['key' => 'k', 'value' => 'w']]]),
        ];

        foreach ($bad as $i => $terms) {
            try {
                DealTerms::normalize($terms);
                $this->fail("case {$i} should be rejected");
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode(), "case {$i}");
            }
        }
    }

    public function test_render_is_readable_and_stable(): void
    {
        $text = DealTerms::render(DealTerms::normalize($this->terms()));

        $this->assertStringContainsString('Price: 100.5 – 120 IRT / per day', $text);
        $this->assertStringContainsString('Time: 2026-11-20 – 2026-11-22', $text);
        $this->assertStringContainsString('Insurance: Yes', $text);
        $this->assertStringContainsString('Takes effect: when everyone has agreed', $text);
        $this->assertSame($text, DealTerms::render(DealTerms::normalize($this->terms())));
    }

    public function test_structured_version_is_sealed_hashed_immutable_and_reaches_the_contract_unchanged(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user, 'Garden help', [['actor' => $bob, 'role' => 'worker']], '', null, null, 'proposer', null, $this->terms(),
        );
        $version = $proposal->versions->sole();

        $this->assertTrue($version->termsIntegrityOk());
        $this->assertSame(DealTerms::hash(DealTerms::normalize($this->terms())), $version->terms_hash);
        $this->assertStringContainsString('Place: Tehran', (string) $version->termsRevision->payload['terms']);

        $this->assertSame(0, Contract::query()->count());
        app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted, null, 1);

        // Single Agree: Bob's one decision completed the Agreement on the same sealed Terms.
        $contract = Contract::query()->sole();
        $contractVersion = $contract->versions()->sole();
        $this->assertSame($version->terms_content_revision_id, $contractVersion->terms_content_revision_id);
        $this->assertSame(ContractVersionStatus::Active, $contractVersion->status);
        $this->assertSame(2, ContractAcceptance::query()->count());
        foreach (ContractAcceptance::query()->with('party', 'sourceDecision')->get() as $acceptance) {
            $this->assertNotNull($acceptance->source_proposal_decision_id);
            $this->assertSame($acceptance->sourceDecision->decided_by_user_id, $acceptance->accepted_by_user_id);
            $this->assertSame($acceptance->sourceDecision->proposal_party_id, $acceptance->party->source_proposal_party_id);
        }
        $this->assertSame(0, Commitment::query()->count(), 'accepted Terms create no obligations');

        try {
            app(CreateContractFromProposal::class)->execute($proposal->fresh(), $alice->user, now()->addMinutes(5), 'UTC');
            $this->fail('a second Contract for the same Terms must be refused');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->expectException(LogicException::class);
        $version->update(['terms_hash' => str_repeat('0', 64)]);
    }

    public function test_free_text_versions_still_work_and_have_no_structured_terms(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute($alice->user, 'Old style', [['actor' => $bob, 'role' => 'worker']], 'Plain terms.');
        $version = $proposal->versions->sole();

        $this->assertNull($version->terms_data);
        $this->assertNull($version->termsIntegrityOk());

        app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
    }

    public function test_g1_decision_is_bound_to_the_reviewed_version_inside_the_transaction(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user, 'Garden help', [['actor' => $bob, 'role' => 'worker']], '', null, null, 'proposer', null, $this->terms(),
        );
        app(ProposeTermsVersion::class)->execute($proposal, $alice->user, 'v2', '', null, null, null, $this->terms(['price' => ['max' => '200']]));

        // Bob reviewed version 1; version 2 has since been proposed.
        try {
            app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted, null, 1);
            $this->fail('stale acceptance must be refused');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(0, $proposal->versions()->where('version', 2)->sole()->decisions()->where('proposal_party_id', $proposal->parties()->where('actor_id', $bob->id)->value('id'))->count());
        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);

        // A structured version cannot be answered without naming the reviewed version.
        try {
            app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted);
            $this->fail('missing expected version must be refused for structured Terms');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted, null, 2);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
    }

    public function test_origin_reference_is_normalized(): void
    {
        // Matching against the Deal's pinned intent version is enforced in ProposeTermsVersion and journey-tested in C5.
        $normalized = DealTerms::normalize($this->terms(['origin' => ['intent_uuid' => '3f2504e0-4f89-11d3-9a0c-0305e82c3301', 'intent_version_id' => '7', 'kind' => 'offer']]));
        $this->assertSame(7, $normalized['origin']['intent_version_id']);
    }
}
