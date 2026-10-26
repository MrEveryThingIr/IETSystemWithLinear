<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Proposals\CancelProposal;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\RespondToProposal;
use App\ContractVersionStatus;
use App\GroupPermission;
use App\Models\Actor;
use App\Models\Business;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalParty;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use App\Services\Business\BusinessService;
use App\Support\Parties\Party;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** M3-C3b: a party is a principal (person, Business, Group); a representative signs for it only while PartyAuthority says so. */
class DealPartyPrincipalsTest extends TestCase
{
    use RefreshDatabase;

    private const TERMS = ['schema' => 1, 'place' => 'Tehran'];

    private function business(Actor $owner, ?Actor $manager = null, string $name = 'Build Co'): Business
    {
        $service = app(BusinessService::class);
        $business = $service->create($owner, ['name' => $name, 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        if ($manager instanceof Actor) {
            $service->addMember($business, $manager, 'manager');
        }

        return $business;
    }

    /** @param list<array<string, mixed>> $invitees */
    private function propose(Actor $creator, array $invitees, ?Party $as = null, ?array $terms = self::TERMS): Proposal
    {
        return app(CreateProposal::class)->execute($creator->user, 'Deal', $invitees, $terms === null ? 'Plain terms.' : '', null, null, 'proposer', null, $terms, $as);
    }

    private function respond(Proposal $proposal, Actor $who): ProposalDecision
    {
        return app(RespondToProposal::class)->execute($proposal, $who->user, ProposalDecisionKind::Accepted, null, 1);
    }

    private function httpStatus(callable $call): int
    {
        try {
            $call();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        } catch (AuthorizationException) {
            return 403;
        }

        return 200;
    }

    public function test_business_manager_signs_for_the_business_and_the_record_keeps_principal_and_signer(): void
    {
        [$carol, $owner, $manager] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner, $manager);

        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)]]);
        $this->assertSame('business:'.$business->id, $proposal->parties->last()->principal_key);

        $decision = $this->respond($proposal, $manager); // not the stored representative

        $this->assertSame($manager->user->id, $decision->decided_by_user_id);
        $this->assertSame('business:'.$business->id, $decision->party->principal_key);

        $version = Contract::query()->sole()->versions()->sole();
        $this->assertSame(ContractVersionStatus::Active, $version->status);
        $acceptance = ContractAcceptance::query()->whereHas('party', fn ($q) => $q->where('business_id', $business->id))->sole();
        $this->assertSame($manager->user->id, $acceptance->accepted_by_user_id);
        $this->assertSame(2, ContractAcceptance::query()->count());
    }

    public function test_two_managers_record_one_acceptance_for_the_business(): void
    {
        [$carol, $dan, $owner, $manager] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner, $manager);
        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)], ['actor' => $dan]]);

        $this->respond($proposal, $owner);
        $this->assertSame(403, $this->httpStatus(fn () => $this->respond($proposal, $manager)), 'the Business already answered');
        $this->assertSame(403, $this->httpStatus(fn () => $this->respond($proposal, $owner)), 'idempotent: no second decision');

        $businessParty = ProposalParty::query()->where('business_id', $business->id)->sole();
        $this->assertSame(1, ProposalDecision::query()->where('proposal_party_id', $businessParty->id)->count());
        $this->assertSame(ProposalStatus::Negotiating, $proposal->fresh()->status);

        $this->respond($proposal, $dan);
        $this->assertSame(3, ContractAcceptance::query()->count(), 'one per principal, not per manager');
    }

    public function test_business_can_propose_and_any_manager_can_withdraw(): void
    {
        [$carol, $owner, $manager] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner, $manager);

        $proposal = $this->propose($owner, [['actor' => $carol]], Party::of($business));
$this->assertNotNull($proposal->parties->firstWhere("business_id", $business->id));

        app(CancelProposal::class)->execute($proposal, $manager->user);
        $this->assertSame(ProposalStatus::Cancelled, $proposal->fresh()->status);
    }

    public function test_group_manager_signs_for_the_group(): void
    {
        [$carol, $owner, $editor] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $group = app(CreateGroup::class)->execute($owner, 'Street association', null);
        $group->memberships()->create(['actor_id' => $editor->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($editor, $group, $roles->createRole($group, 'Concepts', [GroupPermission::ManageConcepts->value]));

        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($group)]]);
        $decision = $this->respond($proposal, $editor);

        $this->assertSame($group->id, $decision->party->group_id);
        $this->assertSame($editor->user->id, $decision->decided_by_user_id);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
    }

    public function test_one_person_cannot_stand_on_both_sides(): void
    {
        [$owner, $carol] = [Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner);

        // At creation: the owner personally and their own Business.
        $this->assertSame(422, $this->httpStatus(fn () => $this->propose($owner, [['actor' => $carol], ['actor' => $owner, 'party' => Party::of($business)]])));
        $this->assertSame(422, $this->httpStatus(fn () => $this->propose($carol, [['actor' => $owner], ['actor' => $owner, 'party' => Party::of($business)]])));

        // At response: authority gained after creation does not let a manager answer for two sides.
        [$ownerA, $ownerB, $both] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $a = $this->business($ownerA, null, 'A');
        $b = $this->business($ownerB, null, 'B');
        $proposal = $this->propose($carol, [['actor' => $ownerA, 'party' => Party::of($a)], ['actor' => $ownerB, 'party' => Party::of($b)]]);
        app(BusinessService::class)->addMember($a, $both, 'manager');
        app(BusinessService::class)->addMember($b, $both, 'manager');

        $this->assertSame(403, $this->httpStatus(fn () => $this->respond($proposal, $both)));
        $this->assertSame(0, ProposalDecision::query()->where('decided_by_user_id', $both->user->id)->count());
    }

    public function test_outsiders_and_non_managers_are_refused_without_learning_anything(): void
    {
        [$carol, $owner, $member, $stranger] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner);
        app(BusinessService::class)->addMember($business, $member, 'member');
        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)]], terms: [...self::TERMS, 'notes' => 'private price detail']);

        foreach ([$member, $stranger] as $actor) {
            $this->assertSame(403, $this->httpStatus(fn () => $this->respond($proposal, $actor)));
            $this->actingAs($actor->user)->get(route('proposals.show', $proposal))->assertForbidden()->assertDontSee('private price detail');
        }
        $this->assertSame(0, ProposalDecision::query()->count() - 1, 'only the proposer decision exists');
    }

    public function test_authority_revoked_after_page_load_is_rechecked_inside_the_transaction(): void
    {
        [$carol, $owner, $manager] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner, $manager);
        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)]]);

        // "Page load": authority is read and remembered for this request.
        $this->assertTrue(Gate::forUser($manager->user)->allows('respond', $proposal));
        app(BusinessService::class)->removeMember($business, $business->memberships()->where('actor_id', $manager->id)->sole());

        $this->assertSame(403, $this->httpStatus(fn () => $this->respond($proposal, $manager)));
        $this->assertSame(0, ProposalDecision::query()->where('decided_by_user_id', $manager->user->id)->count());
        $this->assertSame(0, Contract::query()->count());

        // Current authority wins: a newly added manager can sign.
        $new = Actor::factory()->create();
        app(BusinessService::class)->addMember($business, $new, 'manager');
        $this->assertSame($new->user->id, $this->respond($proposal, $new)->decided_by_user_id);
    }

    public function test_principal_identity_is_unique_in_the_database_and_not_tied_to_the_representative(): void
    {
        [$carol, $owner] = [Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner);
        $other = $this->business($owner, null, 'Other Co');

        $this->assertSame(422, $this->httpStatus(fn () => $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)], ['actor' => $owner, 'party' => Party::of($business)]])));

        // One representative may stand for two different principals (the old actor_id key would have refused this).
        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)]]);
        $row = ['proposal_id' => $proposal->id, 'role' => 'x', 'added_by_actor_id' => $carol->id, 'actor_id' => $owner->id];
        ProposalParty::query()->create([...$row, 'business_id' => $other->id]);
        $this->assertSame(3, ProposalParty::query()->where('proposal_id', $proposal->id)->count());

        $this->expectException(QueryException::class); // the same principal again is refused by the unique key itself
        DB::table('proposal_parties')->insert([
            ...$row, 'uuid' => (string) Str::uuid(), 'business_id' => $business->id, 'principal_key' => 'business:'.$business->id,
            'required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_party_is_never_both_business_and_group(): void
    {
        [$carol, $owner] = [Actor::factory()->create(), Actor::factory()->create()];
        $proposal = $this->propose($carol, [['actor' => $owner]]);

        $this->expectException(InvalidArgumentException::class);
        ProposalParty::query()->create(['proposal_id' => $proposal->id, 'actor_id' => $owner->id, 'business_id' => 1, 'group_id' => 1, 'role' => 'x', 'added_by_actor_id' => $carol->id]);
    }

    public function test_manual_contract_acceptance_is_per_principal_and_by_current_authority(): void
    {
        [$carol, $owner, $manager] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        $business = $this->business($owner, $manager);
        $proposal = $this->propose($carol, [['actor' => $owner, 'party' => Party::of($business)]], terms: null); // free text: explicit Contract form
        app(RespondToProposal::class)->execute($proposal, $manager->user, ProposalDecisionKind::Accepted);

        $contract = app(CreateContractFromProposal::class)->execute($proposal->fresh(), $carol->user, now()->addMinutes(5), 'UTC');
        $version = $contract->versions->sole();
        $this->assertSame(1, ContractAcceptance::query()->count(), 'only the proposer is carried over');

        $this->assertSame(403, $this->httpStatus(fn () => app(AcceptContractVersion::class)->execute($version, Actor::factory()->create()->user)));
        app(AcceptContractVersion::class)->execute($version, $manager->user);
        $this->assertSame(403, $this->httpStatus(fn () => app(AcceptContractVersion::class)->execute($version->fresh(), $owner->user)), 'one acceptance for the Business');
        $this->assertSame(2, ContractAcceptance::query()->count());
        $this->assertSame(ContractVersionStatus::Accepted, $version->fresh()->status, 'accepted by both; activates at its effective time');
    }

    public function test_actor_only_parties_keep_working_and_get_a_principal_key(): void
    {
        [$alice, $bob] = [Actor::factory()->create(), Actor::factory()->create()];
        $proposal = $this->propose($alice, [['actor' => $bob]]);

        $this->assertEqualsCanonicalizing(['actor:'.$alice->id, 'actor:'.$bob->id], $proposal->parties->pluck('principal_key')->all());
        $this->assertNull($proposal->parties->first()->business_id);
        $this->respond($proposal, $bob);
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
        $this->assertSame(2, ContractAcceptance::query()->count());
    }
}
