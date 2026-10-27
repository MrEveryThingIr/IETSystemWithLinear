<?php

namespace Tests\Feature;

use App\Actions\Deals\ManageDealWork;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Deals\MoneyPanel;
use App\Livewire\Deals\WorkPanel;
use App\Livewire\Intents\Composer;
use App\Livewire\Messages\Thread;
use App\Livewire\Relationships\Create as DealStart;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\ConversationMessage;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\CommitmentProgress;
use App\Support\Deals\DealMoney;
use App\Support\Deals\DealNote;
use App\Support\Messaging\ThreadFor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealConversationAreasTest extends TestCase
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
        } catch (AuthorizationException) {
            return 403;
        } catch (ValidationException) {
            return 422;
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

    private function agree(Actor $alice, Actor $bob, Relationship $deal, array $terms): void
    {
        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1] + $terms);
        app(RespondToProposal::class)->execute(Proposal::query()->latest('id')->firstOrFail(), $bob->user, ProposalDecisionKind::Accepted, null, 1);
    }

    /** @return array{0: Actor, 1: Actor, 2: Relationship, 3: Commitment} Alice builds 120 square metres for Bob; nothing is scheduled. */
    private function wallDeal(): array
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, ['price' => ['min' => '500000', 'max' => '500000', 'currency' => 'IRT', 'basis' => 'square_metre'], 'payment' => ['mode' => 'per_period', 'period' => 'week'], 'completion' => ['confirmed_by' => 'beneficiary']]);
        $p = Contract::query()->sole()->activeVersionRecord()->parties->pluck('id', 'actor_id')->all();
        $work = app(ManageDealWork::class);
        $item = $work->propose($deal, $alice->user, ['title' => 'Build the wall', 'kind' => 'work', 'quantity' => '120', 'unit' => 'square_metre', 'cycles' => 1, 'obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id], 'starts_on' => '', 'start_time' => '09:00', 'duration' => 60, 'every' => 1, 'period' => 'day']);

        return [$alice, $bob, $deal, $work->confirm($deal->fresh(), $bob->user, $item['id'])];
    }

    private function panel(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(WorkPanel::class, ['relationship' => $deal]);
    }

    private function accepted(Commitment $c): string
    {
        return app(CommitmentProgress::class)->acceptedQuantity($c);
    }

    public function test_work_and_money_steps_are_labelled_cards_in_the_one_deal_conversation_and_can_be_filtered(): void
    {
        [$alice, $bob, $deal, $wall] = $this->wallDeal();
        $work = fn (Actor $who) => $this->panel($who, $deal);
        $money = fn (Actor $who) => Livewire::actingAs($who->user)->test(MoneyPanel::class, ['relationship' => $deal]);

        $work($alice)->set('log.commitment', (string) $wall->id)->set('log.date', now()->subDay()->format('Y-m-d'))->set('log.from', '08:00')->set('log.to', '12:00')->set('log.note', 'Laid bricks')->call('logDay')->assertHasNoErrors();
        $work($alice)->set('report.commitment', (string) $wall->id)->set('report.quantity', '10')->set('report.note', 'First floor')->call('submitReport')->assertHasNoErrors();
        $inspection = Fulfillment::query()->sole();
        $work($bob)->set('text.confirm-'.$inspection->id, '8')->call('review', $inspection->id, 'accepted')->assertHasNoErrors();

        $this->travelTo(now()->addDays(10)); // the week the work was confirmed in has ended, so it is due
        $key = app(DealMoney::class)->items($deal->fresh())->first()['key'];
        $money($alice)->set('selected.'.$key, true)->call('request')->assertHasNoErrors();
        $obligation = app(DealMoney::class)->items($deal->fresh())->first()['obligation'];
        $money($bob)->set('pay.'.$obligation->uuid, '4000000')->call('report')->assertHasNoErrors();
        $payment = Transaction::query()->where('type', 'payment')->sole();
        $money($alice)->call('received', $payment->uuid)->assertHasNoErrors();

        // Every step is one card in the same conversation, labelled by its area and linking to that tab.
        $cards = ConversationMessage::query()->where('kind', 'deal')->get();
        $byArea = $cards->groupBy(fn ($m) => $m->meta['area']);
        $this->assertSame(['inspection_partly', 'inspection_requested', 'work_logged'], $byArea['work']->pluck('meta.type')->sort()->values()->all());
        $this->assertSame(['money_payment_reported', 'money_receipt_confirmed', 'money_requested'], $byArea['money']->pluck('meta.type')->sort()->values()->all());
        $this->assertTrue($byArea['work']->every(fn ($m) => str_contains($m->meta['url'], 'tab=work')));
        $this->assertTrue($byArea['money']->every(fn ($m) => str_contains($m->meta['url'], 'tab=money')));

        // The Deal page shows them as distinct cards; the chips filter them without a second chat.
        $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertOk()->assertSee('data-deal-event', false)->assertSee('data-thread-filter', false)
            ->assertSee(__('deals.timeline.money_receipt_confirmed', DealNote::params($byArea['money']->firstWhere('meta.type', 'money_receipt_confirmed')->meta)));
        $context = $deal->fresh()->contextBinding->context;
        $thread = Livewire::actingAs($bob->user)->test(Thread::class, ['conversation' => ThreadFor::conversation($context, $bob->user), 'embedded' => true]);
        $thread->assertSeeHtml('data-area="work"')->assertSeeHtml('data-area="money"');
        $thread->call('setFilter', 'work')->assertSeeHtml('data-area="work"')->assertDontSeeHtml('data-area="money"');
        $thread->call('setFilter', 'money')->assertSeeHtml('data-area="money"')->assertDontSeeHtml('data-area="work"');
        $thread->call('setFilter', 'chat')->assertDontSeeHtml('data-deal-event');
        $thread->call('setFilter', 'nonsense')->assertSet('filter', 'all');
    }
}
