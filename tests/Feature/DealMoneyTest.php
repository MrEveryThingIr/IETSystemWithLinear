<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Deals\AmendAgreement;
use App\Actions\Deals\ManageDealMoney;
use App\Actions\Deals\ManageDealWork;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\Enums\Bookkeeping\TransactionStatus;
use App\FulfillmentReviewDecision;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Deals\MoneyPanel;
use App\Livewire\Intents\Composer;
use App\Livewire\Relationships\Create as DealStart;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\Deals\DealMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealMoneyTest extends TestCase
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

    /** Agree on typed terms through the real actions: Alice proposes, Bob accepts. */
    private function agree(Actor $alice, Actor $bob, Relationship $deal, array $terms): void
    {
        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1] + $terms);
        app(RespondToProposal::class)->execute(Proposal::query()->latest('id')->firstOrFail(), $bob->user, ProposalDecisionKind::Accepted, null, 1);
    }

    private function parties(): array
    {
        return Contract::query()->sole()->activeVersionRecord()->parties->pluck('id', 'actor_id')->all();
    }

    private function terms(string $price, string $basis, string $mode = 'after_confirmed_delivery', ?string $max = null): array
    {
        return ['price' => ['min' => $price, 'max' => $max ?? $price, 'currency' => 'IRT', 'basis' => $basis], 'payment' => ['mode' => $mode], 'completion' => ['confirmed_by' => 'beneficiary']];
    }

    /** Alice owes Bob the work; Bob confirms the duty. */
    private function duty(Actor $alice, Actor $bob, Relationship $deal, string $title, string $qty, string $unit, int $cycles = 1): Commitment
    {
        $p = $this->parties();
        $work = app(ManageDealWork::class);
        $item = $work->propose($deal, $alice->user, ['title' => $title, 'kind' => 'service', 'quantity' => $qty, 'unit' => $unit, 'cycles' => $cycles, 'obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id],
            'starts_on' => $cycles > 1 ? now()->addDay()->format('Y-m-d') : '', 'start_time' => '09:00', 'duration' => 480, 'every' => 1, 'period' => 'day']);

        return $work->confirm($deal->fresh(), $bob->user, $item['id']);
    }

    /** The doer reports and the receiver confirms one piece of work. */
    private function done(Actor $alice, Actor $bob, Commitment $c, string $qty, $occurrence = null): Fulfillment
    {
        $f = app(SubmitFulfillment::class)->execute($c, $alice->user, $qty, occurrence: $occurrence);
        app(ReviewFulfillment::class)->execute($f, $bob->user, FulfillmentReviewDecision::Accepted);

        return $f->fresh();
    }

    private function money(Relationship $deal): array
    {
        $items = app(DealMoney::class)->items($deal->fresh());

        return [$items, app(DealMoney::class)->summary($items)];
    }

    private function tx(string $type): Collection
    {
        return Transaction::query()->where('type', $type)->get();
    }

    public function test_a_one_time_sale_goes_from_agreement_to_settlement_without_proving_anything_early(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, $this->terms('3000000', 'total'));
        $item = $this->duty($alice, $bob, $deal, 'Deliver the item', '1', 'item');
        $money = app(ManageDealMoney::class);

        // Before the work is confirmed nothing is owed and nothing exists in Bookkeeping.
        [$items, $sum] = $this->money($deal);
        $this->assertCount(0, $items);
        $this->done($alice, $bob, $item, '1');

        // Confirmed work makes the agreed amount owed, but still no request, no entry.
        [$items, $sum] = $this->money($deal);
        $this->assertSame(0, $sum['owed'], 'Confirmed work value is separate from an accepted ledger obligation.');
        $this->assertSame(0, $sum['requested']);
        $this->assertSame(0, Transaction::query()->count());

        // Only the doer may ask; asking records a request, which changes neither what is owed nor what was paid.
        $this->assertSame(403, $this->http(fn () => $money->request($deal, $bob->user, [$items[0]['key']])));
        $money->request($deal, $alice->user, [$items[0]['key']]);
        $this->assertSame(403, $this->http(fn () => $money->request($deal, $bob->user, [$items[0]['key']])));
        $this->assertSame(422, $this->http(fn () => $money->request($deal, $alice->user, [$items[0]['key']])), 'asking twice does not create a second entry');
        [$items, $sum] = $this->money($deal);
        $this->assertEquals(['owed' => 0, 'requested' => 3000000, 'reported' => 0, 'received' => 0, 'outstanding' => 0], array_intersect_key($sum, array_flip(['owed', 'requested', 'reported', 'received', 'outstanding'])));
        $this->assertSame(1, $this->tx('obligation')->count());

        // The payer reports a payment: reported, not received. The payer cannot confirm their own report.
        $obligation = $items[0]['obligation'];
        $money->approve($deal, $bob->user, $obligation->uuid);
        $payment = $money->report($deal, $bob->user, [$obligation->uuid => '3000000'], 'tok-1');
        $this->assertSame(TransactionStatus::Pending, $payment->status);
        [$items, $sum] = $this->money($deal);
        $this->assertSame(3000000, $sum['reported']);
        $this->assertSame(0, $sum['received']);
        $this->assertSame(3000000, $sum['outstanding']);
        $this->assertSame(403, $this->http(fn () => $money->approve($deal, $bob->user, $payment->uuid)));

        // A double submit of the same form is one payment.
        $again = $money->report($deal, $bob->user, [$obligation->uuid => '3000000'], 'tok-1');
        $this->assertSame($payment->id, $again->id);
        $this->assertSame(1, $this->tx('payment')->count());

        // The recipient confirms receipt; only then is it settled, and the Agreement itself is untouched.
        $money->approve($deal, $alice->user, $payment->uuid);
        [$items, $sum] = $this->money($deal);
        $this->assertEquals(['received' => 3000000, 'reported' => 0, 'outstanding' => 0], array_intersect_key($sum, array_flip(['received', 'reported', 'outstanding'])));
        $this->assertSame('settled', $items[0]['state']);
        $this->assertSame(1, $this->tx('payment')->count());
        $this->assertSame(1, $this->tx('obligation')->count());
        // MySQL enforces the 80-character column that SQLite ignores.
        $this->assertSame(0, Transaction::query()->whereRaw('length(idempotency_key) > 80')->count());
    }

    public function test_recurring_work_is_requested_for_two_days_and_paid_in_instalments_across_cycles(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, $this->terms('500000', 'day'));
        $c = $this->duty($alice, $bob, $deal, 'Work a day', '1', 'day', 4);
        $occurrences = $c->planBinding->plan->occurrences()->orderBy('scheduled_start_at')->get();
        foreach ($occurrences as $o) {
            $this->travelTo($o->scheduled_start_at->copy()->addHour());
            app(TransitionPlanOccurrence::class)->markDone($o, $alice->user);
            $this->done($alice, $bob, $c, '1', $o->fresh());
        }
        $money = app(ManageDealMoney::class);

        [$items, $sum] = $this->money($deal);
        $this->assertCount(4, $items);
        $this->assertSame(0, $sum['owed']);
        $this->assertSame(0, $sum['outstanding']);

        // Ask for the first two days only: the other two stay owed per the Agreement but are not requested.
        $money->request($deal, $alice->user, [$items[0]['key'], $items[1]['key']]);
        [$items, $sum] = $this->money($deal);
        $this->assertSame(1000000, $sum['requested']);
        $this->assertSame(1000000, $sum['unrequested']);
        $this->assertSame(0, $sum['owed'], 'a pending request never creates accepted debt');
        $money->approve($deal, $bob->user, $items[0]['obligation']->uuid);
        $money->approve($deal, $bob->user, $items[1]['obligation']->uuid);

        // Instalment 1 covers day 1 and part of day 2; the amount cannot exceed what each request leaves open.
        [$a, $b] = [$items[0]['obligation']->uuid, $items[1]['obligation']->uuid];
        $this->assertSame(422, $this->http(fn () => $money->report($deal, $bob->user, [$a => '600000'], 'too-much')));
        $p1 = $money->report($deal, $bob->user, [$a => '500000', $b => '200000'], 'i-1');
        // A second report cannot reuse what is already reported but not yet confirmed.
        $this->assertSame(422, $this->http(fn () => $money->report($deal, $bob->user, [$a => '1'], 'i-x')));
        $money->approve($deal, $alice->user, $p1->uuid);
        [$items, $sum] = $this->money($deal);
        $this->assertSame(700000, $sum['received']);
        $this->assertSame('settled', $items[0]['state']);
        $this->assertSame('part_paid', $items[1]['state']);
        $this->assertSame(300000, $sum['outstanding'], 'unrequested work value is not accepted ledger debt');

        // Instalment 2 settles day 2; the rest of the Agreement stays open and nothing is double counted.
        $p2 = $money->report($deal, $bob->user, [$b => '300000'], 'i-2');
        $money->approve($deal, $alice->user, $p2->uuid);
        [$items, $sum] = $this->money($deal);
        $this->assertSame(1000000, $sum['received']);
        $this->assertSame(0, $sum['outstanding']);
        $this->assertSame(['settled', 'settled', 'eligible', 'eligible'], $items->pluck('state')->all());
        $this->assertSame(2, $this->tx('payment')->count());
        $this->assertSame(2, $this->tx('obligation')->count());
    }

    public function test_a_disputed_or_rejected_payment_never_settles_and_keeps_its_history(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, $this->terms('1000000', 'total'));
        $c = $this->duty($alice, $bob, $deal, 'Repair', '1', 'item');
        $this->done($alice, $bob, $c, '1');
        $money = app(ManageDealMoney::class);
        $money->request($deal, $alice->user, [$this->money($deal)[0][0]['key']]);
        $obligation = $this->money($deal)[0][0]['obligation'];
        $money->approve($deal, $bob->user, $obligation->uuid);

        // Not received: the payee refuses, with a reason; the claim stays in history and nothing is received.
        $first = $money->report($deal, $bob->user, [$obligation->uuid => '1000000'], 'p1');
        $this->assertSame(422, $this->http(fn () => $money->reject($deal, $alice->user, $first->uuid, '')));
        $money->reject($deal, $alice->user, $first->uuid, 'Nothing arrived');
        $this->assertSame(TransactionStatus::Rejected, $first->fresh()->status);
        $this->assertSame(0, $this->money($deal)[1]['received']);
        $this->assertSame(1000000, $this->money($deal)[1]['outstanding']);

        // A corrected payment is confirmed, then Bob disputes the amount that was confirmed: not settled while disputed.
        $second = $money->report($deal, $bob->user, [$obligation->uuid => '1000000'], 'p2');
        $money->approve($deal, $alice->user, $second->uuid);
        $this->assertSame('settled', $this->money($deal)[0][0]['state']);
        $money->dispute($deal, $bob->user, $second->uuid, 'I paid more');
        $this->assertNotSame('settled', $this->money($deal)[0][0]['state']);
        $this->assertSame(1, $this->money($deal)[1]['attention']);
        $this->assertSame(2, $this->tx('payment')->count(), 'the rejected claim is kept');

        // Strangers cannot touch the Deal's money.
        $eve = $this->account();
        $this->assertSame(403, $this->http(fn () => $money->dispute($deal, $eve->user, $second->uuid, 'x')));
        $this->assertSame(403, $this->http(fn () => $money->report($deal, $eve->user, [$obligation->uuid => '1'], 'e')));
    }

    public function test_when_the_terms_cannot_give_an_amount_nothing_is_owed_until_the_payer_agrees(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, $this->terms('500000', 'total', max: '900000'));
        $c = $this->duty($alice, $bob, $deal, 'Custom job', '1', 'item');
        $this->done($alice, $bob, $c, '1');
        $money = app(ManageDealMoney::class);
        $key = $this->money($deal)[0][0]['key'];

        $this->assertNull($this->money($deal)[0][0]['derived']);
        $this->assertSame(0, $this->money($deal)[1]['owed']);
        $this->assertSame(422, $this->http(fn () => $money->request($deal, $alice->user, [$key])), 'an amount must be typed');
        $money->request($deal, $alice->user, [$key], [$key => '700,000']);
        $this->assertSame(0, $this->money($deal)[1]['owed'], 'a request is not a debt');
        $obligation = $this->money($deal)[0][0]['obligation'];

        // Paying an amount nobody has agreed is refused; once the payer agrees it is owed and payable.
        $this->assertSame(422, $this->http(fn () => $money->report($deal, $bob->user, [$obligation->uuid => '700000'], 'early')));
        $money->approve($deal, $bob->user, $obligation->uuid);
        $this->assertSame(700000, $this->money($deal)[1]['owed']);
        $money->report($deal, $bob->user, [$obligation->uuid => '700000'], 'late');
    }

    public function test_a_deal_with_no_payment_term_has_no_money_and_still_works(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, ['completion' => ['confirmed_by' => 'beneficiary']]);
        $c = $this->duty($alice, $bob, $deal, 'Help out', '1', 'visit');
        $this->done($alice, $bob, $c, '1');

        $this->assertCount(0, $this->money($deal)[0]);
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(422, $this->http(fn () => app(ManageDealMoney::class)->request($deal, $alice->user, ['fulfillment:'.Fulfillment::query()->sole()->uuid])));
        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_two_people_complete_a_sale_through_the_money_tab(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, $this->terms('3000000', 'total'));
        $duty = $this->duty($alice, $bob, $deal, 'Deliver the item', '1', 'item');
        $this->done($alice, $bob, $duty, '1');
        $key = $this->money($deal)[0][0]['key'];
        $panel = fn (Actor $who) => Livewire::actingAs($who->user)->test(MoneyPanel::class, ['relationship' => $deal]);

        // The doer sees the amount from the agreed price and asks; the payer sees a request but no debt beyond the Agreement.
        $panel($alice)->assertSee('data-money-request', false)->set('selected.'.$key, true)->call('request')->assertHasNoErrors();
        $obligation = $this->money($deal)[0][0]['obligation'];
        $panel($bob)->call('agree', $obligation->uuid)->assertHasNoErrors();
        $panel($bob)->assertSee('data-money-pay', false)->assertDontSee('data-money-request', false);

        // Reporting needs an amount; the recipient cannot report on the payer's behalf.
        $panel($bob)->call('report')->assertHasErrors('money');
        $panel($bob)->set('pay.'.$obligation->uuid, '3000000')->call('report')->assertHasNoErrors();
        $panel($alice)->assertSee('data-money-payment', false)->assertDontSee('data-money-pay"', false);
        $this->assertSame(0, $this->money($deal)[1]['received']);

        // The recipient confirms receipt: settled, and the tab says so.
        $payment = $this->tx('payment')->sole();
        $panel($bob)->call('received', $payment->uuid)->assertStatus(403);
        $panel($alice)->call('received', $payment->uuid)->assertHasNoErrors();
        $this->assertSame('settled', $this->money($deal)[0][0]['state']);
        $panel($bob)->assertSeeHtml('data-state="settled"');

        // The Deal page shows the panel inside the Money tab, and a stranger cannot open the Deal.
        $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertOk()->assertSee('data-money-panel', false);
        $this->actingAs($this->account()->user)->get(route('relationships.show', $deal))->assertForbidden();
    }

    public function test_an_amendment_changes_future_work_but_never_the_amount_owed_for_earlier_work(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, $this->terms('500000', 'day'));
        $c = $this->duty($alice, $bob, $deal, 'Day of work', '1', 'day');
        $this->done($alice, $bob, $c, '1');
        $requests = app(ManageDealMoney::class)->request($deal, $alice->user, [$this->money($deal)[0][0]['key']]);
        app(ManageDealMoney::class)->approve($deal, $bob->user, $requests[0]->uuid);
        $this->assertSame(500000, $this->money($deal)[1]['owed']);

        // The Agreement is amended to a higher day rate and the amendment takes effect.
        app(AmendAgreement::class)->execute(Contract::query()->sole(), $alice->user, ['schema' => 1] + $this->terms('900000', 'day'), now()->addDay(), 'Asia/Tehran');
        app(AcceptContractVersion::class)->execute(Contract::query()->sole()->pendingVersionRecord(), $bob->user);
        $this->travel(3)->days();
        app(ActivateDueContractVersions::class)->executeForContract(Contract::query()->sole());

        // Earlier work keeps the price it was set up under; the request and its Bookkeeping entry are untouched.
        [$items, $sum] = $this->money($deal);
        $this->assertSame(500000, $sum['owed']);
        $this->assertSame(500000, $items[0]['derived']);
        $this->assertSame(1, $this->tx('obligation')->count());
    }
}
