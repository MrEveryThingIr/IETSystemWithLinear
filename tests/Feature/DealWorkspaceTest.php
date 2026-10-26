<?php

namespace Tests\Feature;

use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Intents\Composer;
use App\Livewire\Relationships\Create as DealStart;
use App\Livewire\Relationships\Show as DealShow;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ConversationMessage;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use App\RelationshipStatus;
use App\Services\Business\BusinessService;
use App\Support\Parties\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3-C4: Need/Offer -> conversation -> Terms card -> explicit Agree -> Agreement, in one workspace. */
class DealWorkspaceTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private function account(): Actor
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['market', 'deals']);

        return $actor;
    }

    private function offer(Actor $owner, ?string $as = null): ActorProfileIntent
    {
        Livewire::actingAs($owner->user)->withQueryParams($as ? ['as' => $as] : [])->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')
            ->set('core.title', 'Peugeot 206')->set('core.description', 'Used car, one owner')->set('core.visibility', 'public')
            ->call('addTerm', 'price')->set('priceMode', 'fixed')->set('price', '2,000,000,000')
            ->call('publish')->assertHasNoErrors();

        return ActorProfileIntent::query()->whereHas('profile', fn ($q) => $q->where('actor_id', $owner->id))->latest('id')->firstOrFail();
    }

    private function startAs(Actor $responder, ActorProfileIntent $offer, string $message, string $actingAs = ''): Relationship
    {
        Livewire::actingAs($responder->user)->withQueryParams(['intent' => $offer->uuid])->test(DealStart::class)
            ->set('message', $message)->set('actingAs', $actingAs)->call('save')->assertHasNoErrors();

        return Relationship::query()->latest('id')->firstOrFail();
    }

    private function show(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(DealShow::class, ['relationship' => $deal]);
    }

    public function test_two_accounts_go_from_offer_to_conversation_to_one_explicit_agreement(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $offer = $this->offer($alice);

        // Bob starts a conversation: no roles, no form paperwork, just an introduction.
        $deal = $this->startAs($bob, $offer, 'Hi Alice, is the car still available?');
        $this->assertSame(RelationshipStatus::Proposed, $deal->status);
        $this->assertSame($offer->published_version_id, $deal->originating_intent_version_id);
        $this->assertSame('Hi Alice, is the car still available?', $deal->metadata['intro']['text']);
        $this->assertSame(0, ConversationMessage::query()->count(), 'nothing is posted before the other person consents');

        // Alice sees the original Offer and the introduction, and joining happens by replying.
        $this->show($alice, $deal)->assertSee('Peugeot 206')->assertSee('Hi Alice, is the car still available?')
            ->set('message', 'Yes it is. What did you have in mind?')->call('send')->assertHasNoErrors();
        $this->assertSame(RelationshipStatus::Active, $deal->fresh()->status);

        // Ordinary talk about money does not bind anyone.
        $this->show($bob, $deal)->set('message', 'I can pay 1,800,000,000 today.')->call('send');
        $this->assertSame(0, Proposal::query()->count());
        $this->assertSame(0, ProposalDecision::query()->count());

        // Alice proposes structured Terms; Bob asks for changes.
        $this->show($alice, $deal)->call('openTerms')->set('terms.price_min', '2000000000')->set('terms.currency', 'IRT')->set('terms.place', 'Tehran')->call('propose')->assertHasNoErrors();
        $proposal = Proposal::query()->sole();
        $this->assertSame(ProposalStatus::Negotiating, $proposal->status);

        $bobOnV1 = $this->show($bob, $deal)->assertSee('Terms · version 1');
        $bobOnV1->set('changeNote', 'Too high')->call('requestChanges');
        $this->assertSame(ProposalDecisionKind::ChangesRequested, ProposalDecision::query()->where('decided_by_user_id', $bob->user->id)->sole()->decision);

        // Alice revises: a new immutable version, with what changed shown.
        $this->show($alice, $deal)->call('openTerms')->assertSet('terms.price_min', '2000000000')->set('terms.price_min', '1850000000')->call('propose')->assertHasNoErrors();
        $this->assertSame(2, ProposalVersion::query()->count());
        $this->assertSame('2000000000', ProposalVersion::query()->where('version', 1)->sole()->terms_data['price']['min']);
        $this->show($bob, $deal)->assertSee('Terms · version 2')->assertSee('Price from')->assertSee('1850000000');

        // Bob's page still shows version 1: agreeing there is refused and records nothing.
        $bobOnV1->call('agree')->assertHasErrors('terms');
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(1, ProposalDecision::query()->where('decided_by_user_id', $bob->user->id)->count());

        // Both accept the same version: one Agreement with derived, signed acceptances.
        $this->show($bob, $deal)->call('agree')->assertHasNoErrors();
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
        $contract = Contract::query()->sole();
        $this->assertSame(2, ContractAcceptance::query()->count());
        foreach (ContractAcceptance::query()->with('sourceDecision')->get() as $acceptance) {
            $this->assertSame($acceptance->sourceDecision->decided_by_user_id, $acceptance->accepted_by_user_id);
            $this->assertSame(2, $acceptance->sourceDecision->proposalVersion->version);
        }
        $this->show($alice, $deal)->assertSee(route('contracts.show', $contract), false);

        // The conversation stays open after the agreement.
        $this->show($bob, $deal)->set('message', 'Thanks, see you Friday.')->call('send')->assertHasNoErrors();
        $this->assertSame('Thanks, see you Friday.', ConversationMessage::query()->where('kind', 'text')->latest('id')->first()->body);
    }

    public function test_starting_twice_reuses_the_open_conversation_and_privacy_holds(): void
    {
        [$alice, $bob, $eve] = [$this->account(), $this->account(), $this->account()];
        $offer = $this->offer($alice);

        $first = $this->startAs($bob, $offer, 'Hello');
        $second = $this->startAs($bob, $offer, 'Hello again');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Relationship::query()->count());
        $this->assertSame('Hello', $first->fresh()->metadata['intro']['text']);

        $this->actingAs($eve->user)->get(route('relationships.show', $first))->assertForbidden();
        $this->actingAs($bob->user)->get(route('relationships.show', $first))->assertOk()->assertSee('Peugeot 206');
    }

    public function test_direct_proposal_without_chat_and_refusal_or_withdrawal(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $deal = $this->startAs($bob, $this->offer($alice), '');
        $this->assertSame(0, ConversationMessage::query()->count(), 'a blank introduction is fine');
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'place' => 'Tehran']);
        $this->show($bob, $deal)->call('declineTerms');
        $this->assertSame(ProposalStatus::Rejected, Proposal::query()->sole()->status);
        $this->assertSame(0, Contract::query()->count());

        $this->show($bob, $deal)->call('openTerms')->set('terms.place', 'Karaj')->call('propose')->assertHasNoErrors();
        $this->assertSame(2, Proposal::query()->count());
        $this->show($bob, $deal)->call('withdrawTerms');
        $this->assertSame(ProposalStatus::Cancelled, Proposal::query()->latest('id')->first()->status);
    }

    public function test_business_publisher_and_business_responder_are_the_parties_but_people_sign(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $seller = app(BusinessService::class)->create($alice, ['name' => 'Seller Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $buyer = app(BusinessService::class)->create($bob, ['name' => 'Buyer Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $offer = $this->offer($alice, 'business:'.$seller->id);
        $this->assertSame($seller->id, $offer->publisher_business_id);

        $deal = $this->startAs($bob, $offer, 'For my company', 'business:'.$buyer->id);
        $this->assertSame('business:'.$buyer->id, $deal->metadata['responder_party']);
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'place' => 'Tehran']);
        $keys = Proposal::query()->sole()->parties->pluck('principal_key')->all();
        $this->assertEqualsCanonicalizing(['business:'.$seller->id, 'business:'.$buyer->id], $keys);

        $this->show($bob, $deal)->call('agree')->assertHasNoErrors();
        $this->assertSame(1, Contract::query()->count());
        $this->assertEqualsCanonicalizing([$alice->user->id, $bob->user->id], ContractAcceptance::query()->pluck('accepted_by_user_id')->all());

        // Speaking as a Business you do not manage is refused and creates nothing.
        $carol = $this->account();
        $before = Relationship::query()->count();
        try {
            Livewire::actingAs($carol->user)->withQueryParams(['intent' => $this->offer($alice)->uuid])->test(DealStart::class)
                ->set('actingAs', 'business:'.$buyer->id)->call('save');
        } catch (\Throwable) {
        }
        $this->assertSame($before, Relationship::query()->count());
    }
}
