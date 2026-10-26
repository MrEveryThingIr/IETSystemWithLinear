<?php

namespace Tests\Feature;

use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Intents\Composer;
use App\Livewire\Items\Index as MyItems;
use App\Livewire\Relationships\Create as DealStart;
use App\Livewire\Relationships\Show as DealShow;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\Deals\AgreementDocument;
use App\Support\Deals\DealLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealConsolidationTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private function account(): Actor
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['market', 'deals', 'messages']);

        return $actor;
    }

    private function offer(Actor $owner): ActorProfileIntent
    {
        Livewire::actingAs($owner->user)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')
            ->set('core.title', 'Peugeot 206')->set('core.description', 'Used car')->set('core.visibility', 'public')
            ->call('addTerm', 'price')->set('priceMode', 'fixed')->set('price', '2,000,000')->call('publish')->assertHasNoErrors();

        return ActorProfileIntent::query()->whereHas('profile', fn ($q) => $q->where('actor_id', $owner->id))->latest('id')->firstOrFail();
    }

    /** @return array{0: Actor, 1: Actor, 2: Relationship} */
    private function activeDeal(): array
    {
        [$alice, $bob] = [$this->account(), $this->account()];
        $offer = $this->offer($alice);
        Livewire::actingAs($bob->user)->withQueryParams(['intent' => $offer->uuid])->test(DealStart::class)->set('message', 'Hi')->call('save');
        $deal = Relationship::query()->latest('id')->firstOrFail();
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);

        return [$alice, $bob, $deal->fresh()];
    }

    private function compose(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(AgreementComposer::class, ['relationship' => $deal])->call('start');
    }

    private function http(callable $call): int
    {
        try {
            $call();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }

        return 200;
    }

    /** Alice drafts a document with structured and narrative terms; Bob accepts the exact version. */
    private function agreed(Actor $alice, Actor $bob, Relationship $deal): Proposal
    {
        $c = $this->compose($alice, $deal)->set('draft.title', 'Car sale')
            ->call('addItem', 'clause:duties')->call('addItem', 'clause:duties')->call('addItem', 'clause:duties')->call('addItem', 'clause:scope');
        $c->set('draft.clauses.0.text', 'Alice hands over the car.')->set('draft.clauses.1.title', 'Bob')->set('draft.clauses.1.text', 'Bob pays on handover.')
            ->set('draft.clauses.2.text', 'Both bring ID.')->set('draft.clauses.3.text', 'One used car.')->call('send')->assertHasNoErrors();
        $proposal = Proposal::query()->latest('id')->firstOrFail();
        app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted, null, 1);

        return $proposal->fresh();
    }

    public function test_old_proposal_and_contract_addresses_open_the_deal_at_the_right_tab_and_version(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->compose($alice, $deal)->call('addItem', 'clause:scope')->set('draft.clauses.0.text', 'v1')->call('send');
        $proposal = Proposal::query()->sole();
        $eve = $this->account();

        $this->actingAs($bob->user)->get(route('proposals.show', $proposal))->assertRedirect(DealLinks::workspace($deal, 'agreement', 1));
        $this->actingAs($eve->user)->get(route('proposals.show', $proposal))->assertForbidden();

        // The link lands on the Agreement tab with the requested version expanded.
        $html = $this->actingAs($bob->user)->get(DealLinks::workspace($deal, 'agreement', 1))->assertOk()->getContent();
        $this->assertStringContainsString('data-start="agreement"', $html);

        // The legal record stays reachable from the accepted Deal and links back to it.
        $this->compose($alice, $deal)->call('addItem', 'clause:special')->set('draft.clauses.1.text', 'v2')->call('send');
        app(RespondToProposal::class)->execute($proposal->fresh(), $bob->user, ProposalDecisionKind::Accepted, null, 2);
        $contract = \App\Models\Contract::query()->sole();
        $this->actingAs($bob->user)->get(route('contracts.show', $contract))->assertOk()->assertSee(route('relationships.agreement.print', $deal), false);
        $this->assertSame(DealLinks::workspace($deal, 'agreement', 2), DealLinks::contract($contract->fresh('relationship', 'sourceProposalVersion')));
        $this->actingAs($eve->user)->get(route('contracts.show', $contract))->assertForbidden();
    }

    public function test_the_printable_agreement_exists_only_for_an_executed_agreement_and_is_complete(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $eve = $this->account();

        // Nothing agreed yet: a draft or an open proposal is never presented as executed.
        $this->actingAs($alice->user)->get(route('relationships.agreement.print', $deal))->assertNotFound();
        $this->compose($alice, $deal)->call('addItem', 'clause:scope')->set('draft.clauses.0.text', 'Open proposal')->call('send');
        $this->actingAs($alice->user)->get(route('relationships.agreement.print', $deal))->assertNotFound();
        Proposal::query()->sole()->versions()->count();

        $proposal = Proposal::query()->sole();
        app(RespondToProposal::class)->execute($proposal, $bob->user, ProposalDecisionKind::Accepted, null, 1);

        $page = $this->actingAs($bob->user)->get(route('relationships.agreement.print', $deal))->assertOk();
        $page->assertSee('data-agreement-print', false)->assertSee('Open proposal')->assertSee($alice->user->username)->assertSee($bob->user->username)
            ->assertSee('data-print-party', false)->assertSee(substr(ProposalVersion::query()->sole()->terms_hash, 0, 16));

        // Participants only; no chat content is printed.
        $this->actingAs($eve->user)->get(route('relationships.agreement.print', $deal))->assertForbidden();
        $this->assertStringNotContainsString('Hi', preg_replace('/Hi[a-z]/i', '', $page->getContent()) ?? '');
    }

    public function test_several_clauses_of_one_kind_share_one_numbered_heading(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $c = $this->compose($alice, $deal)->call('addItem', 'clause:duties')->call('addItem', 'clause:scope')->call('addItem', 'clause:duties');

        // Adding a second "duties" clause files it under the first one, so numbering is 1.1 / 1.2, not a repeated heading.
        $items = $c->get('draft')['items'];
        $dutyKeys = array_values(array_filter($items, fn ($id) => str_starts_with($id, 'clause:duties')));
        $this->assertCount(2, $dutyKeys);
        $this->assertSame(1, abs(array_search($dutyKeys[0], $items, true) - array_search($dutyKeys[1], $items, true)), 'same-kind clauses stay together');

        $this->agreed($alice, $bob, $deal);
        $terms = ProposalVersion::query()->latest('id')->first()->terms_data;
        $sections = AgreementDocument::sections($terms);
        $group = collect($sections)->firstWhere('id', 'group:duties');
        $this->assertNotNull($group);
        $this->assertCount(3, $group['clauses']);
        $this->assertSame([$group['n'].'.1', $group['n'].'.2', $group['n'].'.3'], array_column($group['clauses'], 'n'));
        $this->assertSame(1, collect($sections)->where('title', __('deals.clauses.duties.title'))->count(), 'one heading');
        $this->assertSame(range(1, count($sections)), array_column($sections, 'n'));

        $html = $this->actingAs($bob->user)->get(route('relationships.agreement.print', $deal))->getContent();
        $this->assertSame(1, substr_count($html, __('deals.clauses.duties.title')), 'the printed heading appears once');
        $this->assertStringContainsString('data-agreement-clause="'.$group['n'].'.2"', $html);
    }

    public function test_currency_and_price_unit_are_selected_and_custom_units_are_kept(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $c = $this->compose($alice, $deal)->call('addItem', 'price')->set('draft.core.price_min', '40')->set('draft.core.price_max', '')->set('draft.core.currency', 'EUR')->set('draft.core.basis_choice', 'hour');
        $c->assertSee('EUR')->assertSee(__('deals.bases.milestone'))->call('send')->assertHasNoErrors();
        $this->assertSame(['min' => '40', 'currency' => 'EUR', 'basis' => 'hour'], ProposalVersion::query()->latest('id')->first()->terms_data['price']);
        $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertSee('40 EUR / '.__('deals.bases.hour'));

        $this->compose($alice, $deal)->set('draft.core.basis_choice', '__other')->set('draft.core.basis_custom', 'per bedroom')->call('send')->assertHasNoErrors();
        $this->assertSame('per bedroom', ProposalVersion::query()->latest('id')->first()->terms_data['price']['basis']);
        // Reopened, a custom unit is shown as custom, not lost.
        $this->compose($alice, $deal)->assertSet('draft.core.basis_choice', '__other')->assertSet('draft.core.basis_custom', 'per bedroom');
    }

    public function test_working_hours_and_timezone_are_validated_and_stored_unambiguously(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->compose($alice, $deal)->call('addItem', 'time')->set('draft.core.starts_on', '2026-12-01')->set('draft.core.ends_on', '2026-12-31')
            ->set('draft.core.daily_from', '08:00')->set('draft.core.daily_to', '17:00')->set('draft.core.timezone', 'Asia/Tehran')->call('send')->assertHasNoErrors();
        $time = ProposalVersion::query()->latest('id')->first()->terms_data['time'];
        $this->assertSame(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'daily_from' => '08:00', 'daily_to' => '17:00', 'timezone' => 'Asia/Tehran'], $time);
        $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertSee('08:00 – 17:00 (Asia/Tehran)');

        foreach ([['daily_from' => '08:00'], ['daily_from' => '25:00', 'daily_to' => '17:00'], ['daily_from' => '09:00', 'daily_to' => '09:00'], ['daily_from' => '08:00', 'daily_to' => '17:00', 'timezone' => 'Mars/Base']] as $bad) {
            $this->assertSame(422, $this->http(fn () => app(ProposeDealTerms::class)->execute($deal->fresh(), $alice->user, ['schema' => 1, 'time' => ['starts_on' => '2026-12-01'] + $bad])));
        }
        $this->assertSame(422, $this->http(fn () => app(ProposeDealTerms::class)->execute($deal->fresh(), $alice->user, ['schema' => 1, 'price' => ['min' => '5', 'currency' => 'EURO7']])));
    }

    public function test_agreement_drafts_can_be_found_and_resumed_and_intent_drafts_too(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();

        // Agreement: save, leave, find on Deals and on the tab, resume with the saved content.
        $this->compose($alice, $deal)->call('addItem', 'clause:scope')->set('draft.clauses.0.text', 'Half written')->set('draft.title', 'My draft')->call('saveDraft');
        $this->actingAs($alice->user)->get(route('deals.index'))->assertOk()->assertSee('data-agreement-drafts', false)->assertSee('My draft')
            ->assertSee(e(route('relationships.show', ['relationship' => $deal, 'tab' => 'agreement', 'resume' => 1])), false);
        $this->actingAs($bob->user)->get(route('deals.index'))->assertDontSee('data-agreement-drafts', false);

        Livewire::actingAs($alice->user)->withQueryParams(['tab' => 'agreement'])->test(DealShow::class, ['relationship' => $deal])->assertSee('data-draft-badge', false)->assertSee(__('deals.continue_draft'));
        Livewire::actingAs($alice->user)->withQueryParams(['tab' => 'agreement', 'resume' => 1])->test(DealShow::class, ['relationship' => $deal])->assertSeeHtml('data-agreement-composer')->assertSee('Half written');

        // Intent: save a draft in the Composer, find it under My Items > drafts, reopen it in the Composer.
        Livewire::actingAs($alice->user)->test(Composer::class)->call('choose', 'need')->call('chooseTopic', 'repair')->set('core.title', 'Fix my tap')->call('saveDraft');
        $draft = ActorProfileIntent::query()->where('status', 'draft')->firstOrFail();
        Livewire::actingAs($alice->user)->test(MyItems::class)->assertSet('show', 'active')->set('show', 'draft')->assertSee('Fix my tap')->assertSee(__('items.offers_needs.resume'))
            ->assertSeeHtml(route('intents.compose.edit', $draft));
        Livewire::actingAs($alice->user)->test(Composer::class, ['intent' => $draft])->assertSet('core.title', 'Fix my tap');
    }

    public function test_navigation_has_one_home_per_stage_and_an_owner_is_never_told_to_negotiate_with_themself(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $offer = $deal->originatingIntent;

        // Market discovers, the old three-column page redirects, My Items manages, Deals negotiates.
        $this->actingAs($alice->user)->get(route('experience.needs-offers'))->assertRedirect(route('intents.index'));
        $this->assertSame('intents.index', collect(app(\App\Services\Surfaces\ExperienceNavigation::class)->for($alice->user)['primary'])->keyBy('key')['market']['route']);

        // My Items: view / edit / conversations first, matches only as an optional extra.
        Livewire::actingAs($alice->user)->test(MyItems::class)->assertSee(__('items.offers_needs.view'))->assertSee(__('items.offers_needs.edit'))
            ->assertSee(__('items.offers_needs.conversations', ['count' => 1]))->assertSee(__('items.offers_needs.matches_optional'));

        // The directory: own post leads with Edit/View, someone else's with the conversation start.
        $mine = $this->actingAs($alice->user)->get(route('intents.index', ['scope' => 'mine']))->assertOk();
        $mine->assertSee('data-own-edit', false)->assertDontSee(route('relationships.create', ['intent' => $offer->uuid]), false);
        $this->actingAs($bob->user)->get(route('intents.index'))->assertSee(route('relationships.create', ['intent' => $offer->uuid]), false)->assertDontSee('data-own-edit', false);

        // The owner's own post page: Edit first, related conversations listed, no "respond to this".
        $this->actingAs($alice->user)->get(route('intents.show', $offer))->assertOk()->assertSee('data-intent-deals', false)->assertSee(route('intents.compose.edit', $offer), false)
            ->assertDontSee(route('relationships.create', ['intent' => $offer->uuid]), false);

        // Back from a Deal goes to Deals; the printable page goes back to the Agreement tab.
        $this->actingAs($alice->user)->get(route('relationships.show', $deal))->assertSee(route('deals.index'), false);
    }
}
