<?php

namespace Tests\Feature;

use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Contexts\Conversation as LegacyConversation;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Intents\Composer;
use App\Livewire\Messages\Thread;
use App\Livewire\Relationships\Create as DealStart;
use App\Livewire\Relationships\Show as DealShow;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Commitment;
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
use App\Support\Deals\AgreementDocument;
use App\Support\Deals\AgreementDrafter;
use App\Support\Deals\DealLinks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3-C4/C5: conversation, one Agreement document, explicit mutual acceptance of the exact version, in one workspace. */
class DealWorkspaceTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private function account(): Actor
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['market', 'deals', 'messages']);

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

    private function show(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(DealShow::class, ['relationship' => $deal]);
    }

    /** Opens an active Deal between a responder and the owner of a fresh Offer. */
    private function activeDeal(?Actor $alice = null, ?Actor $bob = null, string $intro = 'Hi'): array
    {
        [$alice, $bob] = [$alice ?? $this->account(), $bob ?? $this->account()];
        $deal = $this->startAs($bob, $this->offer($alice), $intro);
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        return [$alice, $bob, $deal->fresh()];
    }

    /** Writes in the shared Deal thread (the same component the Messages inbox uses). */
    private function say(Actor $who, Relationship $deal, string $text): void
    {
        $this->show($who, $deal); // opening the Deal page is what creates the shared thread
        $conversation = Conversation::query()->where('context_id', $deal->contextBinding->context_id)->firstOrFail();
        Livewire::actingAs($who->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true])
            ->set('body', $text)->call('send', (string) Str::uuid())->assertHasNoErrors();
    }

    private function compose(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(AgreementComposer::class, ['relationship' => $deal])->call('start');
    }

    public function test_two_accounts_go_from_offer_to_conversation_to_one_explicit_agreement(): void
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $offer = $this->offer($alice);

        $deal = $this->startAs($bob, $offer, 'Hi Alice, is the car still available?');
        $this->assertSame(RelationshipStatus::Proposed, $deal->status);
        $this->assertSame($offer->published_version_id, $deal->originating_intent_version_id);
        $this->assertSame('Hi Alice, is the car still available?', $deal->metadata['intro']['text']);
        $this->assertSame(0, ConversationMessage::query()->count(), 'nothing is posted before the other person consents');

        $this->show($alice, $deal)->assertSee('Peugeot 206')->assertSee('Hi Alice, is the car still available?')->call('accept');
        $this->assertSame(RelationshipStatus::Active, $deal->fresh()->status);
        $this->say($alice, $deal, 'Yes it is. What did you have in mind?');
        $this->say($bob, $deal, 'I can pay 1,800,000,000 today. I agree!');

        // Chat, even "I agree", is not consent.
        $this->assertSame(0, Proposal::query()->count());
        $this->assertSame(0, ProposalDecision::query()->count());

        // Alice (owner of the post) drafts from the original post, saves the draft privately, then sends it for review.
        $c = $this->compose($alice, $deal);
        $this->assertSame('Peugeot 206', $c->get('draft')['title']);
        $c->set('draft.summary', 'Sale of one used car.')->call('addItem', 'payment')->set('draft.payment.mode', 'after_confirmed_delivery')
            ->call('addItem', 'clause:scope')->set('draft.clauses.0.text', 'The car as described in the post, nothing else.')
            ->call('saveDraft');
        $this->assertSame(0, Proposal::query()->count(), 'a draft is not an agreement');
        $this->assertNotNull($deal->fresh()->metadata['agreement_draft']);
        $this->compose($alice, $deal)->assertSet('draft.summary', 'Sale of one used car.')->call('send')->assertHasNoErrors();
        $this->assertArrayNotHasKey('agreement_draft', $deal->fresh()->metadata);

        $v1 = ProposalVersion::query()->sole();
        $this->assertSame('after_confirmed_delivery', $v1->terms_data['payment']['mode']);
        $this->assertSame('Sale of one used car.', $v1->terms_data['summary']);
        $this->assertTrue($v1->termsIntegrityOk());
        $proposal = Proposal::query()->sole();

        // Bob reviews and asks for changes (he is not the drafter); Alice revises into a new immutable version.
        $bobOnV1 = $this->show($bob, $deal)->assertSee('Payment')->assertSee('data-agreement-section', false);
        $bobOnV1->set('changeNote', 'Cheaper please')->call('requestChanges');
        $this->assertSame(ProposalDecisionKind::ChangesRequested, ProposalDecision::query()->where('decided_by_user_id', $bob->user->id)->sole()->decision);

        $this->compose($alice, $deal)->set('draft.core.price_min', '1850000000')->call('send')->assertHasNoErrors();
        $this->assertSame(2, ProposalVersion::query()->count());
        $this->assertSame('2000000000', $v1->fresh()->terms_data['price']['min'], 'version 1 never changes');
        $this->show($bob, $deal)->assertSee('Price from')->assertSee('1850000000');

        // Bob's page still shows version 1: agreeing there is refused and records nothing. Chat did not carry over.
        $bobOnV1->call('agree')->assertHasErrors('terms');
        $this->assertSame(0, Contract::query()->count());

        // Everyone accepts the exact version: one Agreement, signed by the right people.
        $this->show($bob, $deal)->call('agree')->assertHasNoErrors();
        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
        $this->assertSame(1, Contract::query()->count());
        $this->assertSame(2, ContractAcceptance::query()->count());
        foreach (ContractAcceptance::query()->with('sourceDecision.proposalVersion')->get() as $acceptance) {
            $this->assertSame($acceptance->sourceDecision->decided_by_user_id, $acceptance->accepted_by_user_id);
            $this->assertSame(2, $acceptance->sourceDecision->proposalVersion->version);
        }
        $this->show($alice, $deal)->assertSee('data-agreement-final', false)->assertSee('data-agreement-signers', false);
        $this->say($bob, $deal, 'Thanks, see you Friday.');

        // Nothing here created work or money.
        $this->assertSame(0, Commitment::query()->count());
    }

    public function test_only_the_drafter_writes_others_ask_and_drafting_can_be_handed_over_safely(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();

        $this->assertSame(403, $this->httpStatus(fn () => app(ProposeDealTerms::class)->execute($deal, $bob->user, ['schema' => 1, 'place' => 'Karaj'])));
        $this->assertSame(0, ProposalVersion::query()->count());

        // Bob asks for an agreement; Alice sees the request and it changes no terms.
        $this->show($bob, $deal)->set('requestNote', 'Could you write it down?')->call('requestAgreement');
        $this->show($alice, $deal)->assertSee($bob->user->username)->assertSee('data-agreement-requests', false);
        $this->assertSame(0, Proposal::query()->count());

        // Alice hands drafting to Bob; now Bob may send, and Alice may take it back.
        $this->show($alice, $deal)->call('delegateDrafting', $bob->id);
        $this->compose($bob, $deal)->call('addItem', 'place')->set('draft.core.place', 'Karaj')->call('send')->assertHasNoErrors();
        $this->assertSame(1, ProposalVersion::query()->count());
        $this->show($alice, $deal)->call('delegateDrafting', 0);
        $this->assertSame(403, $this->httpStatus(fn () => app(ProposeDealTerms::class)->execute($deal->fresh(), $bob->user, ['schema' => 1, 'place' => 'Rasht'])));

        // A stranger cannot hand drafting to themself, and an outsider cannot read the Deal.
        $eve = $this->account();
        $this->assertSame(403, $this->httpStatus(fn () => AgreementDrafter::delegate($deal->fresh(), $eve->user, $eve->id)));
        $this->actingAs($eve->user)->get(route('relationships.show', $deal))->assertForbidden();
    }

    public function test_the_agreement_is_an_ordered_numbered_document_of_independent_terms(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();

        $c = $this->compose($alice, $deal)->call('addItem', 'clause:duties')->call('addItem', 'clause:cancellation')->call('addItem', 'clause:custom')->call('addItem', 'recurrence')->call('addItem', 'completion');
        $items = $c->get('draft')['items'];
        $c->set('draft.clauses.0.text', 'Alice hands over the car.')->set('draft.clauses.1.text', 'Either side may cancel before the handover.')
            ->set('draft.clauses.2.title', 'Spare key')->set('draft.clauses.2.text', 'Two keys are included.')
            ->set('draft.completion.confirmed_by', 'beneficiary')->set('draft.recurrence.every', '2')
            ->call('move', 'clause:custom', -1)->call('move', 'clause:custom', -1)->call('removeItem', 'clause:cancellation')->call('send')->assertHasNoErrors();

        $terms = ProposalVersion::query()->sole()->terms_data;
        $this->assertSame(['duties', 'custom'], array_column($terms['clauses'], 'kind'));
        $this->assertSame('beneficiary', $terms['completion']['confirmed_by']);
        $this->assertSame(['every' => 2, 'unit' => 'month'], $terms['recurrence']);
        $this->assertNotContains('clause:cancellation', $terms['order']);
        $position = fn (string $id) => array_search($id, $terms['order'], true);
        $this->assertLessThan($position('clause:duties'), $position('clause:custom'), 'the clause moved up stays ahead');

        $sections = AgreementDocument::sections($terms);
        $this->assertSame(range(1, count($sections)), array_column($sections, 'n'), 'numbered 1..n');
        $this->assertContains('Spare key', array_column($sections, 'title'));
        $this->show($bob, $deal)->assertSee('Two keys are included.')->assertSee('data-agreement-document', false);

        // A payment term without a rule, or an unfinished typed term, is refused with a message, not stored.
        $this->compose($alice, $deal)->call('addItem', 'payment')->call('send')->assertHasErrors('draft');
        $this->assertSame(1, ProposalVersion::query()->count());
    }

    public function test_typed_terms_are_validated_and_clause_text_never_becomes_automation(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();

        $this->assertSame(422, $this->httpStatus(fn () => app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'payment' => ['mode' => 'whenever']])));
        $this->assertSame(422, $this->httpStatus(fn () => app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'order' => ['clause:ghost'], 'place' => 'x'])));
        $this->assertSame(422, $this->httpStatus(fn () => app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'payment' => ['mode' => 'per_period']])));

        $this->compose($alice, $deal)->call('addItem', 'clause:special')->set('draft.clauses.0.text', 'Pay 5,000,000 on Friday and deliver weekly.')->call('send');
        $terms = ProposalVersion::query()->sole()->terms_data;
        $this->assertArrayNotHasKey('payment', $terms ?? [], 'prose is kept as prose');
        $this->assertSame(0, Commitment::query()->count());
    }

    public function test_starting_twice_reuses_the_open_conversation_and_privacy_holds(): void
    {
        [$alice, $bob, $eve] = [$this->account(), $this->account(), $this->account()];
        $offer = $this->offer($alice);

        $first = $this->startAs($bob, $offer, 'Hello');
        $second = $this->startAs($bob, $offer, 'Hello again');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Relationship::query()->count());

        $this->actingAs($eve->user)->get(route('relationships.show', $first))->assertForbidden();
        $this->actingAs($bob->user)->get(route('relationships.show', $first))->assertOk()->assertSee('Peugeot 206');

        // The owner finds the conversation on the post itself.
        $this->actingAs($alice->user)->get(route('intents.show', $offer))->assertOk()->assertSee('data-intent-deals', false)->assertSee(route('relationships.show', $first), false);
    }

    public function test_direct_agreement_without_chat_and_refusal_or_withdrawal(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal(intro: '');
        $this->assertSame(0, ConversationMessage::query()->count());

        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'place' => 'Tehran']);
        $this->show($bob, $deal)->call('declineTerms');
        $this->assertSame(ProposalStatus::Rejected, Proposal::query()->sole()->status);
        $this->assertSame(0, Contract::query()->count());

        $this->compose($alice, $deal)->set('draft.core.place', 'Karaj')->call('send')->assertHasNoErrors();
        $this->assertSame(2, Proposal::query()->count());
        $this->show($alice, $deal)->call('withdrawTerms');
        $this->assertSame(ProposalStatus::Cancelled, Proposal::query()->latest('id')->first()->status);
    }

    public function test_business_publisher_and_business_responder_are_the_parties_but_people_sign(): void
    {
        [$alice, $bob, $manager] = [$this->account(), $this->account(), $this->account()];
        $seller = app(BusinessService::class)->create($alice, ['name' => 'Seller Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        app(BusinessService::class)->addMember($seller, $manager, 'manager');
        $buyer = app(BusinessService::class)->create($bob, ['name' => 'Buyer Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $offer = $this->offer($alice, 'business:'.$seller->id);
        $this->assertSame($seller->id, $offer->publisher_business_id);

        $deal = $this->startAs($bob, $offer, 'For my company', 'business:'.$buyer->id);
        $this->assertSame('business:'.$buyer->id, $deal->metadata['responder_party']);
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        // The Business manager (not a Deal participant) is on the owner side by authority, yet cannot open the Deal page.
        $this->assertTrue(AgreementDrafter::isOwnerSide($deal->fresh()->load('originatingIntent.profile'), $manager->user));
        $this->assertFalse(AgreementDrafter::isOwnerSide($deal->fresh()->load('originatingIntent.profile'), $bob->user));

        $this->compose($alice, $deal)->set('draft.core.place', 'Tehran')->call('send')->assertHasNoErrors();
        $keys = Proposal::query()->sole()->parties->pluck('principal_key')->all();
        $this->assertEqualsCanonicalizing(['business:'.$seller->id, 'business:'.$buyer->id], $keys);

        $this->show($bob, $deal)->call('agree')->assertHasNoErrors();
        $this->assertSame(1, Contract::query()->count());
        $this->assertEqualsCanonicalizing([$alice->user->id, $bob->user->id], ContractAcceptance::query()->pluck('accepted_by_user_id')->all());

        $carol = $this->account();
        $before = Relationship::query()->count();
        try {
            Livewire::actingAs($carol->user)->withQueryParams(['intent' => $this->offer($alice)->uuid])->test(DealStart::class)
                ->set('actingAs', 'business:'.$buyer->id)->call('save');
        } catch (\Throwable) {
        }
        $this->assertSame($before, Relationship::query()->count());
    }

    public function test_one_conversation_everywhere_legacy_links_land_in_the_same_thread(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $ctx = $deal->contextBinding->context;
        $this->say($alice, $deal, 'first message');

        // The old conversation URL opens the Deal; there is no second, weaker composer.
        $this->actingAs($bob->user)->get(route('contexts.conversation', $ctx))->assertRedirect(route('relationships.show', $deal));
        $page = $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertOk();
        $page->assertSee('data-message-thread', false)->assertDontSee('collaboration.conversation.message');

        // Any other context shows the very same Thread component instead of a private copy.
        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'place' => 'Tehran']);
        $negotiation = Proposal::query()->sole()->contextBinding->context;
        Livewire::actingAs($bob->user)->test(LegacyConversation::class, ['context' => $negotiation])->assertSeeHtml('data-context-thread')->assertSeeLivewire(Thread::class);
        $this->assertSame(1, Conversation::query()->where('context_id', $ctx->id)->count(), 'one authoritative thread per context');
    }

    public function test_images_files_and_voice_notes_render_the_same_in_the_deal_thread(): void
    {
        Storage::fake('local');
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->show($alice, $deal);
        $conversation = Conversation::query()->where('context_id', $deal->contextBinding->context_id)->firstOrFail();

        $thread = Livewire::actingAs($alice->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true]);
        $thread->set('files', [UploadedFile::fake()->image('panel.jpg', 300, 200), UploadedFile::fake()->create('terms.pdf', 20, 'application/pdf')])
            ->call('send', (string) Str::uuid())->assertHasNoErrors();
        $thread->set('voice', UploadedFile::fake()->create('voice.webm', 20, 'audio/webm'))->call('sendVoice', 3, (string) Str::uuid())->assertHasNoErrors();

        $html = Livewire::actingAs($bob->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true])->html();
        $this->assertStringContainsString('<img', $html, 'an image is shown as an image, not as a file link');
        $this->assertStringContainsString('<audio', $html);
        $this->assertStringContainsString('terms.pdf', $html);
    }

    public function test_the_thread_root_never_depends_on_changing_data_so_a_recording_survives_new_messages(): void
    {
        // Root cause of silently failing voice notes: the version was inside the x-data expression, so every arriving message
        // rebuilt the Alpine component and dropped the recording in progress. The expression is now constant.
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->say($alice, $deal, 'one');
        $conversation = Conversation::query()->where('context_id', $deal->contextBinding->context_id)->firstOrFail();
        $thread = Livewire::actingAs($bob->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true]);
        $before = $thread->html();
        $this->say($alice, $deal, 'two');
        $after = $thread->call('refreshThread')->html();

        preg_match('/x-data="([^"]*)"/', $before, $a);
        preg_match('/x-data="([^"]*)"/', $after, $b);
        $this->assertSame('messageThread', $a[1]);
        $this->assertSame($a[1], $b[1]);
        $this->assertStringNotContainsString('version:', $a[1]);
        $this->assertNotSame($this->versionAttr($before), $this->versionAttr($after), 'data-version moves, but it is read once and never rebuilds the component');
        $this->assertStringNotContainsString('version: config.version', file_get_contents(resource_path('js/messages.js')));
    }

    public function test_voice_upload_survives_the_recording_ui_and_failures_are_visible_and_logged(): void
    {
        // Root cause in a real Chrome (fake microphone, driven over DevTools): pressing "Send" removed the button's
        // <template x-if> subtree, and the upload started through that subtree's $wire was dropped. No request was ever sent.
        // The thread now keeps one root-bound $wire for everything asynchronous.
        $js = file_get_contents(resource_path('js/messages.js'));
        $this->assertStringContainsString('this.wire = this.$wire', $js);
        $this->assertStringNotContainsString('this.$wire.upload', $js);
        $this->assertStringNotContainsString('this.$wire.sendVoice', $js);

        Storage::fake('local');
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->show($alice, $deal);
        $conversation = Conversation::query()->where('context_id', $deal->contextBinding->context_id)->firstOrFail();

        // A refused file is shown to the person (and logged with its stage), not swallowed.
        Log::spy();
        $thread = Livewire::actingAs($alice->user)->test(Thread::class, ['conversation' => $conversation, 'embedded' => true]);
        $thread->set('voice', UploadedFile::fake()->create('voice.bin', 20, 'application/x-msdownload'))->call('sendVoice', 3, (string) Str::uuid())
            ->assertSee(__('messages.composer.voice_failed'));
        Log::shouldHaveReceived('warning')->with('messages.voice_failed', \Mockery::on(fn ($c) => $c['stage'] === 'store'))->once();
        $this->assertSame(0, ConversationMessage::query()->where('kind', 'voice')->count());

        // The browser can report any stage it failed at.
        $thread->call('voiceFailed', 'upload', 'net::ERR_FAILED | audio/webm 4000B');
        Log::shouldHaveReceived('warning')->with('messages.voice_failed', \Mockery::on(fn ($c) => $c['stage'] === 'upload'))->once();
    }

    public function test_the_workspace_is_one_page_with_lightweight_work_and_money_and_stable_details(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $html = $this->actingAs($alice->user)->get(route('relationships.show', $deal))->assertOk()->getContent();

        foreach (['conversation', 'agreement', 'work', 'money'] as $tab) {
            $this->assertStringContainsString('data-tab="'.$tab.'"', $html);
        }
        $this->assertStringContainsString('Nothing is tracked in this Deal yet', $html);
        $this->assertStringContainsString('no balances, payments or ledger entries', $html);
        $this->assertSame(0, preg_match('/<section[^>]*wire:poll/', $html), 'no page-wide poll: it used to reset open sections');
        $this->assertMatchesRegularExpression('/<details[^>]*x-data="\{ open: false \}"[^>]*x-bind:open="open"[^>]*data-deal-details/', $html);
        $this->assertSame(0, Commitment::query()->count());
    }

    public function test_legacy_deal_links_keep_working(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'place' => 'Tehran']);
        $proposal = Proposal::query()->sole();

        $this->actingAs($bob->user)->get(route('proposals.show', $proposal))->assertRedirect(DealLinks::workspace($deal, 'agreement', 1)); // the old address opens the Deal
        $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertOk();
        $this->actingAs($bob->user)->get(route('deals.index'))->assertOk();
    }

    private function versionAttr(string $html): string
    {
        preg_match('/data-version="([^"]*)"/', $html, $m);

        return $m[1] ?? '';
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
}
