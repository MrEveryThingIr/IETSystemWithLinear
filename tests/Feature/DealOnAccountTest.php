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
use App\Support\CommitmentProgress;
use App\Support\Deals\DealMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealOnAccountTest extends TestCase
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

    private function money(Relationship $deal): array
    {
        $m = app(DealMoney::class);

        return $m->summary($m->items($deal->fresh()), $m->reportedValue($deal->fresh()), $m->estimatedValue($deal->fresh()), $m->onAccount($deal->fresh()));
    }

    /** The traditional way of working: a price per metre, no inspections along the way, money handed over on account. */
    public function test_money_on_account_follows_the_reports_without_any_inspection_and_is_settled_later(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        // Alice is the doer (father), Bob the employer (Majid). Only a price per square metre is agreed: no total, no payment rule.
        $this->agree($alice, $bob, $deal, ['price' => ['min' => '500000', 'max' => '500000', 'currency' => 'IRT', 'basis' => 'square_metre']]);
        $this->panel($bob, $deal)->set('form.title', 'Bathroom walls')->call('addTask')->assertHasNoErrors();
        $task = Commitment::query()->sole();
        $this->assertSame('square_metre', $task->unit);
        $this->assertTrue(WorkPanel::isOpen($task), 'no total was agreed, so the quantity is open');

        // Money exists for a priced Agreement even though no payment rule was written, and it starts from the doer's own reports.
        $this->panel($alice, $deal)->set('log.commitment', (string) $task->id)->set('log.date', now()->subDays(2)->format('Y-m-d'))->set('log.from', '08:00')->set('log.to', '15:00')->set('log.note', 'First wall')->set('log.estimate', '15')->call('logDay')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('log.commitment', (string) $task->id)->set('log.date', now()->subDay()->format('Y-m-d'))->set('log.from', '08:00')->set('log.to', '12:00')->set('log.note', 'Second wall')->set('log.estimate', '25')->call('logDay')->assertHasNoErrors();
        $sum = $this->money($deal);
        $this->assertSame(20000000, $sum['estimated'], 'about 40 square metres at 500,000');
        $this->assertSame(20000000, $sum['roughly_owed']);
        $this->assertSame(0, $sum['earned'], 'nothing is inspected, so nothing is earned');

        $page = Livewire::actingAs($bob->user)->test(MoneyPanel::class, ['relationship' => $deal]);
        $page->assertSeeHtml('data-money-account')->assertSeeHtml('data-sum="roughly_owed"')->assertDontSee(__('deals.money.none'));

        // The doer asks for something on account; the payer hands over less; the doer confirms receiving it.
        $money = fn (Actor $who) => Livewire::actingAs($who->user)->test(MoneyPanel::class, ['relationship' => $deal]);
        $money($bob)->set('ask.amount', '5000000')->call('askMoney')->assertStatus(403);
        $money($alice)->set('ask.amount', '5,000,000')->set('ask.note', 'for materials')->call('askMoney')->assertHasNoErrors();
        $ask = app(DealMoney::class)->asks($deal)[0];
        $money($bob)->assertSee('for materials')->call('useAsk', $ask['id'])->set('account.amount', '4000000')->call('payOnAccount')->assertHasNoErrors();
        $this->assertSame([], app(DealMoney::class)->asks($deal), 'a paid ask is closed');
        $payment = Transaction::query()->where('type', 'payment')->sole();
        $this->assertSame(0, $payment->allocations()->count(), 'on account: no work is attached');
        $this->assertSame(0, $this->money($deal)['on_account'], 'reported, not received');
        $this->assertSame(4000000, $this->money($deal)['on_account_pending']);
        $money($bob)->call('received', $payment->uuid)->assertStatus(403);
        $money($alice)->call('received', $payment->uuid)->assertHasNoErrors();
        $sum = $this->money($deal);
        $this->assertSame(4000000, $sum['on_account']);
        $this->assertSame(16000000, $sum['roughly_owed']);

        // Paying more than the work so far is shown as paid ahead, never as a negative debt.
        $money($bob)->set('account.amount', '20000000')->call('payOnAccount')->assertHasNoErrors();
        $money($alice)->call('received', Transaction::query()->latest('id')->firstOrFail()->uuid);
        $sum = $this->money($deal);
        $this->assertSame(0, $sum['roughly_owed']);
        $this->assertSame(4000000, $sum['paid_ahead']);

        // At a substantial milestone the work is inspected; what was paid on account offsets what is earned, nothing twice.
        $this->panel($alice, $deal)->set('report.commitment', (string) $task->id)->set('report.quantity', '50')->set('report.note', 'Walls done')->call('submitReport')->assertHasNoErrors();
        $this->panel($bob, $deal)->set('text.confirm-'.Fulfillment::query()->sole()->id, '45')->call('review', Fulfillment::query()->sole()->id, 'accepted')->assertHasNoErrors();
        $sum = $this->money($deal);
        $this->assertSame(22500000, $sum['earned']);
        $this->assertSame(0, $sum['estimated'], 'the rough figure has gone to inspection');
        $this->assertSame($sum['earned'], $sum['received'] + $sum['credit_applied'] + $sum['outstanding_due'] + $sum['not_yet_due'], 'earned = received + credit applied + still due + not yet due');
        $this->assertSame(0, $sum['outstanding_due'] + $sum['not_yet_due'], '24,000,000 handed over covers the 22,500,000 earned');
        $this->assertSame(1500000, $sum['paid_ahead']);
    }

    public function test_a_whole_job_task_is_reported_as_a_share_and_errors_name_what_is_open(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agree($alice, $bob, $deal, ['price' => ['min' => '30000000', 'max' => '30000000', 'currency' => 'IRT', 'basis' => 'total'], 'payment' => ['mode' => 'after_confirmed_delivery']]);
        $this->panel($bob, $deal)->set('form.title', 'Renovate the kitchen')->call('addTask')->assertHasNoErrors();
        $task = Commitment::query()->sole();
        $this->assertTrue(WorkPanel::isShare($task));

        $c = $this->panel($alice, $deal)->set('report.commitment', (string) $task->id);
        $c->assertSee(__('deals.work.open_share', ['pct' => 100]));
        $c->set('report.quantity', '140')->call('submitReport')->assertHasErrors('report.quantity')->assertSee(__('deals.errors.share_over'));
        $c->set('report.quantity', '40')->call('submitReport')->assertHasNoErrors();
        $this->assertEquals(0.4, (float) Fulfillment::query()->sole()->quantity);
        $c = $this->panel($alice, $deal)->set('report.commitment', (string) $task->id)->set('report.quantity', '70')->call('submitReport');
        $c->assertHasErrors('report.quantity')->assertSee(__('deals.errors.quantity_exceeds', ['open' => '60 %']));
        $this->panel($bob, $deal)->call('review', Fulfillment::query()->sole()->id, 'accepted')->assertHasNoErrors()->assertSee(__('deals.work.progress_share', ['done' => 40]));
        $this->assertSame(12000000, $this->money($deal)['earned'], '40% of 30,000,000');
    }
}
