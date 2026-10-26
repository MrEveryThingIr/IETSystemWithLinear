<?php

namespace Tests\Feature;

use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Deals\TermsComposer;
use App\Livewire\Intents\Composer;
use App\Livewire\Messages\Thread;
use App\Livewire\Relationships\Create as DealStart;
use App\Livewire\Relationships\Show as DealShow;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\Conversation;
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
            ->call('addField', 'brand')->set('details.0.value', 'Peugeot')->call('addField', 'condition')->set('details.1.value', 'used')
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

    /** Writes in the shared Deal thread (the same component the Messages inbox uses). */
    private function say(Actor $who, Relationship $deal, string $text): void
    {
        $this->show($who, $deal); // opening the Deal page is what creates the shared thread
        $conversation = Conversation::query()->where('context_id', $deal->contextBinding->context_id)->firstOrFail();
        Livewire::actingAs($who->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true])
            ->set('body', $text)->call('send', (string) \Illuminate\Support\Str::uuid())->assertHasNoErrors();
    }

    private function compose(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(TermsComposer::class, ['relationship' => $deal])->call('start');
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
        $this->show($alice, $deal)->assertSee('Peugeot 206')->assertSee('Hi Alice, is the car still available?')->call('accept');
        $this->assertSame(RelationshipStatus::Active, $deal->fresh()->status);
        $this->say($alice, $deal, 'Yes it is. What did you have in mind?');

        // Ordinary talk about money does not bind anyone.
        $this->say($bob, $deal, 'I can pay 1,800,000,000 today.');
        $this->assertSame(0, Proposal::query()->count());
        $this->assertSame(0, ProposalDecision::query()->count());

        // Alice proposes structured Terms; Bob asks for changes.
        $this->compose($alice, $deal)->call('showBlock', 'price')->set('draft.core.price_min', '2000000000')->call('showBlock', 'place')->set('draft.core.place', 'Tehran')
            ->call('propose')->assertHasNoErrors();
        $proposal = Proposal::query()->sole();
        $this->assertSame(ProposalStatus::Negotiating, $proposal->status);

        $bobOnV1 = $this->show($bob, $deal)->assertSee('Terms · version 1');
        $bobOnV1->set('changeNote', 'Too high')->call('requestChanges');
        $this->assertSame(ProposalDecisionKind::ChangesRequested, ProposalDecision::query()->where('decided_by_user_id', $bob->user->id)->sole()->decision);

        // Alice revises: a new immutable version, with what changed shown.
        $this->compose($alice, $deal)->assertSet('draft.core.price_min', '2000000000')->set('draft.core.price_min', '1850000000')->call('propose')->assertHasNoErrors();
        $this->assertSame(2, ProposalVersion::query()->count());
        $this->assertSame('2000000000', ProposalVersion::query()->where('version', 1)->sole()->terms_data['price']['min']);
        $this->show($bob, $deal)->assertSee('Terms · version 2')->assertSee('Price from')->assertSee('1850000000');
        $this->assertSame('1850000000', ProposalVersion::query()->where('version', 2)->sole()->terms_data['price']['min']);

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
        $this->say($bob, $deal, 'Thanks, see you Friday.');
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

        $this->compose($bob, $deal)->set('draft.core.place', 'Karaj')->call('propose')->assertHasNoErrors();
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

    public function test_composer_starts_from_the_offer_edits_typed_fields_and_shows_what_changed(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $deal = $this->startAs($bob, $this->offer($alice), 'Hello');
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        // The first draft is the originating post: price and its typed vehicle fields are already there.
        $c = $this->compose($alice, $deal);
        $draft = $c->get('draft');
        $this->assertNotEmpty($draft['fields'], 'fields come from the pinned Offer');
        $this->assertContains('brand', array_column($draft['fields'], 'key'));

        $i = array_search('condition', array_column($draft['fields'], 'key'), true);
        $c->call('addField', 'warranty')->set('draft.fields.'.count($draft['fields']).'.value', 'true');
        $c->set('search', 'Spare key')->call('addCustom')->set('draft.fields.'.(count($draft['fields']) + 1).'.value', 'two')->call('removeField', 0)->set('draft.notes', 'Handover at the notary.')->call('propose')->assertHasNoErrors();

        $v1 = ProposalVersion::query()->sole();
        $types = collect($v1->terms_data['fields'])->keyBy('key');
        $this->assertSame('boolean', $types['warranty']['type']);
        $this->assertSame('text', $types->first(fn ($f) => str_starts_with($f['key'], 'custom_'))['type']);
        $this->assertNotContains($draft['fields'][0]['key'], $types->keys()->all(), 'a removed field is gone from the next version');
        $this->assertSame('Handover at the notary.', $v1->terms_data['notes']);
        $this->assertTrue($v1->termsIntegrityOk());

        // Version 2 edits one typed value, adds a recurrence, and the diff names exactly that.
        $this->compose($bob, $deal)->call('showBlock', 'recurrence')->set('draft.recurrence.every', '2')->set('draft.recurrence.unit', 'week')
            ->set('draft.fields.0.value', 'edited')->call('propose')->assertHasNoErrors();
        $v2 = ProposalVersion::query()->where('version', 2)->sole();
        $this->assertSame(['every' => 2, 'unit' => 'week'], $v2->terms_data['recurrence']);
        $diff = \App\Support\Deals\DealTermsDiff::compare($v1->terms_data, $v2->terms_data);
        $this->assertContains('recurrence', array_column($diff['rows'], 'key'));
        $this->show($alice, $deal)->assertSee('Repeats');
    }

    public function test_every_composer_type_survives_a_submit_without_error(): void
    {
        // Regression for "submit errors": a dense draft with each field type must normalize and store.
        [$alice, $bob] = [$this->account(), $this->account()];
        $deal = $this->startAs($bob, $this->offer($alice), '');
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        $c = $this->compose($alice, $deal);
        foreach (['price', 'place', 'time', 'quantity', 'route', 'recurrence'] as $block) {
            $c->call('showBlock', $block);
        }
        $before = count($c->get('draft')['fields']);
        $c->set('draft.core.price_min', '10')->set('draft.core.price_max', '12.5')->set('draft.core.basis', 'per day')
            ->set('draft.core.place', 'Tehran')->set('draft.core.starts_on', '2026-12-01')->set('draft.core.ends_on', '2026-12-05')
            ->set('draft.core.quantity', '3')->set('draft.core.unit', 'days')->set('draft.core.from', 'A')->set('draft.core.to', 'B')
            ->set('draft.recurrence.count', '4')->call('addField', 'handover')->set('draft.fields.'.$before.'.value', 'delivery')
            ->call('propose');
        $c->assertHasNoErrors();
        $this->assertSame(1, ProposalVersion::query()->count());
    }

    public function test_details_block_state_is_client_side_and_the_page_has_no_whole_page_poll(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $deal = $this->startAs($bob, $this->offer($alice), 'Hi');
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        $html = $this->actingAs($alice->user)->get(route('relationships.show', $deal))->assertOk()->getContent();
        // The collapse came from the page re-rendering every 8 s and morphing <details> back to its server state (closed).
        $this->assertStringNotContainsString('wire:poll', preg_replace('/<livewire:.*$/s', '', substr($html, strpos($html, 'data-deal-details') - 4000, 4000)) ?? '');
        $this->assertMatchesRegularExpression('/<details[^>]*x-data="\{ open: false \}"[^>]*x-bind:open="open"[^>]*data-deal-details/', $html);
        $this->assertSame(0, preg_match('/<section[^>]*wire:poll/', $html));
    }

    public function test_the_deal_thread_is_the_rich_messaging_thread_with_files_and_step_lines(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $deal = $this->startAs($bob, $this->offer($alice), 'Hi');
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);
        $this->say($alice, $deal, 'hello');

        $page = $this->actingAs($alice->user)->get(route('relationships.show', $deal))->assertOk();
        $page->assertSee('data-message-thread', false)->assertSee('data-deal-thread', false);

        // Same conversation as the inbox, so voice, files, location, replies and reactions are inherited.
        $conversation = Conversation::query()->where('context_id', $deal->contextBinding->context_id)->sole();
        $thread = Livewire::actingAs($bob->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true]);
        $thread->assertSee('hello');
        $this->assertTrue(method_exists(Thread::class, 'sendVoice') && method_exists(Thread::class, 'sendLocation') && method_exists(Thread::class, 'react'));

        // A structured step shows as a line in that same thread, never as consent.
        $this->compose($alice, $deal)->call('showBlock', 'place')->set('draft.core.place', 'Tehran')->call('propose');
        Livewire::actingAs($bob->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true])->assertSee('proposed terms');
        $this->assertSame(0, \App\Models\ContractAcceptance::query()->count());
    }
}
