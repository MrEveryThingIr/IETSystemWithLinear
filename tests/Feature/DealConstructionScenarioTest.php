<?php

namespace Tests\Feature;

use App\Actions\Deals\ManageDealMoney;
use App\Actions\Deals\ManageDealWork;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\ContractStatus;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Deals\MoneyPanel;
use App\Livewire\Deals\WorkPanel;
use App\Livewire\Intents\Composer;
use App\Livewire\Relationships\Create as DealStart;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\Deals\DealMoney;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealConstructionScenarioTest extends TestCase
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

    private function panel(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(WorkPanel::class, ['relationship' => $deal]);
    }

    private function report(Actor $alice, Relationship $deal, Commitment $wall, string $qty, string $note = 'Floor'): Fulfillment
    {
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', $qty)->set('report.note', $note)->call('submitReport')->assertHasNoErrors();

        return Fulfillment::query()->latest('id')->firstOrFail();
    }

    private function review(Actor $bob, Relationship $deal, Fulfillment $f, string $decision, ?string $confirmed = null, ?string $note = null): void
    {
        $c = $this->panel($bob, $deal);
        if ($confirmed !== null) {
            $c->set('text.confirm-'.$f->id, $confirmed);
        }
        if ($note !== null) {
            $c->set('text.review-'.$f->id, $note);
        }
        $c->call('review', $f->id, $decision)->assertHasNoErrors();
    }

    private function snapshot(Relationship $deal): array
    {
        $money = app(DealMoney::class);
        $items = $money->items($deal->fresh());

        return [$items, $money->summary($items, $money->reportedValue($deal->fresh()), $money->estimatedValue($deal->fresh()))];
    }

    /**
     * The owner's sample, end to end: a builder (Alice) is paid 500,000 Toman per square metre by an employer (Bob),
     * reports irregular quantities, is partly accepted, asks for the first week's wages only when they fall due, is paid in
     * an instalment and a final payment, and the Agreement itself is never completed by money.
     */
    public function test_a_builder_paid_per_square_metre_is_paid_weekly_for_confirmed_quantities_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-07 10:00:00', 'UTC')); // a Monday
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, [
            'title' => 'Brick wall',
            'price' => ['min' => '500000', 'max' => '500000', 'currency' => 'IRT', 'basis' => 'square_metre'],
            'payment' => ['mode' => 'per_period', 'period' => 'week'],
            'completion' => ['confirmed_by' => 'beneficiary'],
            // Working days are written down, but they never turn into wages: pay follows measured square metres.
            'time' => ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-31'],
            'recurrence' => ['every' => 1, 'unit' => 'day', 'count' => 20],
        ]);
        // (Days worked are logged as history; they never become wages.)
        $p = Contract::query()->sole()->activeVersionRecord()->parties->pluck('id', 'actor_id')->all();
        $work = app(ManageDealWork::class);
        $item = $work->propose($deal, $alice->user, ['title' => 'Build the wall', 'kind' => 'work', 'quantity' => '120', 'unit' => 'square_metre', 'cycles' => 1, 'obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id], 'starts_on' => '', 'start_time' => '09:00', 'duration' => 60, 'every' => 1, 'period' => 'day']);
        $wall = $work->confirm($deal->fresh(), $bob->user, $item['id']);
        $money = app(ManageDealMoney::class);

        // Attendance alone earns nothing.
        [$items, $sum] = $this->snapshot($deal);
        $this->assertCount(0, $items);
        $this->assertSame(0, $sum['earned']);

        // Irregular reports in one week: partly accepted, accepted, still waiting, and refused.
        $this->panel($alice, $deal)->set('log.commitment', (string) $wall->id)->set('log.date', '2026-12-07')->set('log.from', '05:00')->set('log.to', '07:30')->set('log.note', 'North side')->set('log.estimate', '15')->call('logDay')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('log.commitment', (string) $wall->id)->set('log.date', '2026-12-07')->set('log.from', '08:00')->set('log.to', '09:30')->set('log.note', 'More bricks')->set('log.estimate', '25')->call('logDay')->assertHasNoErrors();
        $r1 = $this->report($alice, $deal, $wall, '12.5');
        $this->review($bob, $deal, $r1, 'accepted', '10');
        $this->travelTo(CarbonImmutable::parse('2026-12-09 10:00:00', 'UTC'));
        $r2 = $this->report($alice, $deal, $wall, '20');
        $this->review($bob, $deal, $r2, 'accepted');
        $r3 = $this->report($alice, $deal, $wall, '8');
        $r4 = $this->report($alice, $deal, $wall, '5');
        $this->review($bob, $deal, $r4, 'rejected', null, 'Not built yet');

        // Mid-week: 30 square metres earned (10 + 20), 4,000,000 reported and waiting; nothing due before the week ends.
        [$items, $sum] = $this->snapshot($deal);
        fwrite(STDERR, 'DBG '.json_encode(Fulfillment::query()->get(['id', 'quantity', 'status'])->toArray()).' rv='.app(DealMoney::class)->reportedValue($deal->fresh()).'
');
        $this->assertSame(15000000, $sum['earned']);
        $this->assertSame(4000000, $sum['reported_value'], 'work awaiting inspection is shown, but it is not owed');
        $this->assertSame(1000000, $sum['estimated'], 'rough daily progress (40) less what went to inspection (10 + 20 + 8) is a guide only');
        $this->assertSame(0, $sum['payable']);
        $this->assertSame(15000000, $sum['not_yet_due']);
        $this->assertSame(0, $sum['outstanding_due']);
        $this->assertSame('2026-12-13', $items[0]['due_on']->toDateString());
        $this->assertSame('not_due', $items[0]['state']);
        $this->assertSame(422, $this->http(fn () => $money->request($deal, $alice->user, [$items[0]['key']])), 'wages cannot be asked for before they fall due');
        $this->assertSame(0, Transaction::query()->count());

        // The week ends: both confirmed pieces fall due; the doer asks for them.
        $this->travelTo(CarbonImmutable::parse('2026-12-13 12:00:00', 'UTC'));
        [$items, $sum] = $this->snapshot($deal);
        $this->assertSame(15000000, $sum['payable']);
        $this->assertSame(15000000, $sum['outstanding_due']);
        $money->request($deal, $alice->user, [$items[0]['key'], $items[1]['key']]);
        [$items, $sum] = $this->snapshot($deal);
        $this->assertSame(15000000, $sum['requested']);
        $this->assertSame(15000000, $sum['earned'], 'asking never adds to what is earned');
        [$a, $b] = [$items[0]['obligation']->uuid, $items[1]['obligation']->uuid];
        $this->assertSame(
            'Brick wall',
            Ledger::query()->sole()->name === null ? '' : Str::before(Ledger::query()->sole()->name, ' · '),
            'the Deal\'s book carries the Deal\'s own name',
        );
        $this->assertStringContainsString($alice->user->username, Ledger::query()->sole()->name);
        $this->assertStringContainsString($bob->user->username, Ledger::query()->sole()->name);

        // An instalment covering both pieces, then the final payment; each is a separate event.
        $p1 = $money->report($deal, $bob->user, [$a => '5000000', $b => '2000000'], 'week-1-instalment', null, 'Week 1', 'instalment');
        $this->assertStringStartsWith(__('deals.money.purpose.instalment'), (string) $p1->description);
        [, $sum] = $this->snapshot($deal);
        $this->assertSame(7000000, $sum['reported']);
        $this->assertSame(0, $sum['received']);
        $money->approve($deal, $alice->user, $p1->uuid);
        [$items, $sum] = $this->snapshot($deal);
        $this->assertSame(7000000, $sum['received']);
        $this->assertSame(8000000, $sum['outstanding_due']);
        $this->assertSame(['settled', 'part_paid'], $items->pluck('state')->all());

        $p2 = $money->report($deal, $bob->user, [$b => '8000000'], 'week-1-final', null, null, 'final');
        $money->approve($deal, $alice->user, $p2->uuid);
        [$items, $sum] = $this->snapshot($deal);
        $this->assertSame(15000000, $sum['received']);
        $this->assertSame(0, $sum['outstanding_due']);
        $this->assertSame(2, $sum['settled_count']);
        // Nothing is counted twice: earned = received + still due + not yet due.
        $this->assertSame($sum['earned'], $sum['received'] + $sum['outstanding_due'] + $sum['not_yet_due']);

        // Later the waiting inspection is confirmed (the following Monday): it is earned, but not yet due, and the Agreement stays open.
        $this->travelTo(CarbonImmutable::parse('2026-12-14 09:00:00', 'UTC'));
        $this->review($bob, $deal, $r3->fresh(), 'accepted');
        [$items, $sum] = $this->snapshot($deal);
        $this->assertSame(19000000, $sum['earned']);
        $this->assertSame(4000000, $sum['not_yet_due']);
        $this->assertSame(0, $sum['reported_value']);
        $this->assertSame($sum['earned'], $sum['received'] + $sum['outstanding_due'] + $sum['not_yet_due']);
        $this->assertSame('2026-12-20', $items->last()['due_on']->toDateString());
        $this->assertSame(ContractStatus::Active, Contract::query()->sole()->status);
        $this->assertSame(2, Transaction::query()->where('type', 'payment')->count());
        $this->assertSame(2, Transaction::query()->where('type', 'obligation')->count());
        $this->assertSame(0, Transaction::query()->whereRaw('length(idempotency_key) > 80')->count());
    }

    public function test_the_money_tab_defines_each_figure_and_shows_where_it_comes_from(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-07 10:00:00', 'UTC'));
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, ['title' => 'Brick wall', 'price' => ['min' => '500000', 'max' => '500000', 'currency' => 'IRT', 'basis' => 'square_metre'], 'payment' => ['mode' => 'after_confirmed_delivery'], 'completion' => ['confirmed_by' => 'beneficiary']]);
        $p = Contract::query()->sole()->activeVersionRecord()->parties->pluck('id', 'actor_id')->all();
        $work = app(ManageDealWork::class);
        $item = $work->propose($deal, $alice->user, ['title' => 'Build the wall', 'kind' => 'work', 'quantity' => '120', 'unit' => 'square_metre', 'cycles' => 1, 'obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id], 'starts_on' => '', 'start_time' => '09:00', 'duration' => 60, 'every' => 1, 'period' => 'day']);
        $wall = $work->confirm($deal->fresh(), $bob->user, $item['id']);
        $r1 = $this->report($alice, $deal, $wall, '4', '2026-12-07');
        $this->review($bob, $deal, $r1, 'accepted');
        app(ManageDealMoney::class)->request($deal, $alice->user, [$this->snapshot($deal)[0][0]['key']]);

        $page = Livewire::actingAs($bob->user)->test(MoneyPanel::class, ['relationship' => $deal]);
        foreach (['reported_value', 'earned', 'payable', 'outstanding_due', 'not_yet_due', 'requested', 'reported', 'received', 'settled'] as $k) {
            $page->assertSeeHtml('data-sum="'.$k.'"');
        }
        $page->assertSeeHtml('data-money-legend')->assertSee(__('deals.money.legend.identity'))->assertSeeHtml('data-item-details')
            ->assertSee(__('deals.money.due.after_confirmed_delivery'))->assertSee(__('deals.agreement_version', ['version' => 1]));
    }
}
