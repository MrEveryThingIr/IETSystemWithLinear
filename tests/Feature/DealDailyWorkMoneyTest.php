<?php

namespace Tests\Feature;

use App\Actions\Deals\ManageDealMoney;
use App\Actions\Deals\ManageDealWork;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Deals\WorkPanel;
use App\Livewire\Intents\Composer;
use App\Livewire\Relationships\Create as DealStart;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\Deals\DealMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** The simple Work to Money circuit: daily measured work, employer confirmation, owed, request, payment, confirmation. */
class DealDailyWorkMoneyTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    /** @return array{0: Actor, 1: Actor, 2: Relationship, 3: Commitment} Alice does the masonry for Bob at 300,000/m² (about 650 m²) */
    private function masonry(): array
    {
        [$alice, $bob] = [Actor::factory()->create(), Actor::factory()->create()];
        foreach ([$alice, $bob] as $a) {
            $this->publishSurfaces($a->user, ['market', 'deals', 'messages']);
        }
        Livewire::actingAs($alice->user)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')
            ->set('core.title', 'Masonry')->set('core.description', 'Brickwork')->set('core.visibility', 'public')
            ->call('addTerm', 'price')->set('priceMode', 'fixed')->set('price', '300,000')->call('publish')->assertHasNoErrors();
        $offer = ActorProfileIntent::query()->whereHas('profile', fn ($q) => $q->where('actor_id', $alice->id))->latest('id')->firstOrFail();
        Livewire::actingAs($bob->user)->withQueryParams(['intent' => $offer->uuid])->test(DealStart::class)->set('message', 'Hi')->call('save');
        $deal = Relationship::query()->latest('id')->firstOrFail();
        app(RespondToRelationship::class)->execute($deal, $alice->user, true);
        $deal = $deal->fresh();

        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1, 'title' => 'Masonry',
            'quantity' => ['value' => '650', 'unit' => 'square_metre'],
            'price' => ['min' => '300000', 'max' => '300000', 'currency' => 'IRT', 'basis' => 'square_metre'],
            'payment' => ['mode' => 'after_confirmed_delivery'], 'completion' => ['confirmed_by' => 'beneficiary']]);
        app(RespondToProposal::class)->execute(Proposal::query()->latest('id')->firstOrFail(), $bob->user, ProposalDecisionKind::Accepted, null, 1);
        $task = app(ManageDealWork::class)->createTask($deal->fresh(), $bob->user, 'Floors');

        return [$alice, $bob, $deal->fresh(), $task];
    }

    private function report(Actor $doer, Relationship $deal, Commitment $task, string $quantity): Fulfillment
    {
        Livewire::actingAs($doer->user)->test(WorkPanel::class, ['relationship' => $deal])
            ->set('report.commitment', (string) $task->id)->set('report.quantity', $quantity)->call('submitReport')->assertHasNoErrors();

        return Fulfillment::query()->latest('id')->firstOrFail();
    }

    private function accept(Actor $employer, Relationship $deal, Fulfillment $f, ?string $quantity = null): void
    {
        $panel = Livewire::actingAs($employer->user)->test(WorkPanel::class, ['relationship' => $deal]);
        if ($quantity !== null) {
            $panel->set('text.confirm-'.$f->id, $quantity);
        }
        $panel->call('review', $f->id, 'accepted')->assertHasNoErrors();
    }

    /** @return array<string, int> */
    private function summary(Relationship $deal): array
    {
        $money = app(DealMoney::class);

        return $money->summary($money->items($deal->fresh()), 0, 0, $money->onAccount($deal->fresh()));
    }

    public function test_daily_reports_become_owed_money_only_when_accepted_and_payments_reduce_what_remains(): void
    {
        [$alice, $bob, $deal, $task] = $this->masonry();
        $money = app(ManageDealMoney::class);

        $this->accept($bob, $deal, $this->report($alice, $deal, $task, '12'));
        $this->accept($bob, $deal, $this->report($alice, $deal, $task, '15'), '10');
        $this->report($alice, $deal, $task, '8'); // not accepted yet

        $this->assertSame(2, Transaction::query()->count(), 'one obligation per accepted day, nothing for the waiting report');
        foreach (Transaction::query()->get() as $entry) {
            $money->approve($deal, $alice->user, $entry->uuid);
        }
        $this->assertSame(6600000, $this->summary($deal)['earned']);
        $this->assertSame(6600000, $this->summary($deal)['outstanding']);

        // Accepting the same work again records nothing new.
        $money->recordAccepted($deal->fresh(), $bob->user, Fulfillment::query()->where('status', 'accepted')->firstOrFail());
        $this->assertSame(2, Transaction::query()->count());

        $ask = $money->askShare($deal->fresh(), $alice->user, 50);
        $this->assertSame(3300000, $ask['amount']);

        $payment = $money->payOnAccount($deal->fresh(), $bob->user, '2000000', 'pay-1', askId: $ask['id']);
        $this->assertSame(6600000, $this->summary($deal)['outstanding'], 'a reported payment is not received until confirmed');
        $money->approve($deal->fresh(), $alice->user, $payment->uuid);

        $sum = $this->summary($deal);
        $this->assertSame(4600000, $sum['outstanding']);
        $this->assertSame(2000000, $sum['received']);
        $this->assertSame(0, $sum['on_account']);
        $open = collect(app(DealMoney::class)->asks($deal->fresh()))->firstWhere('id', $ask['id']);
        $this->assertSame(1300000, (int) $open['amount'] - (int) $open['paid'], 'the request keeps its remainder');
    }

    public function test_an_advance_before_any_accepted_work_is_credit_and_applies_once_to_later_work(): void
    {
        [$alice, $bob, $deal, $task] = $this->masonry();
        $money = app(ManageDealMoney::class);

        $advance = $money->payOnAccount($deal, $bob->user, '1000000', 'adv');
        $money->approve($deal, $alice->user, $advance->uuid);
        $sum = $this->summary($deal);
        $this->assertSame(1000000, $sum['on_account']);
        $this->assertSame(0, $sum['outstanding'], 'an advance is never a reverse debt');

        $this->accept($bob, $deal, $this->report($alice, $deal, $task, '4'));
        $money->approve($deal->fresh(), $alice->user, Transaction::query()->where('type', 'obligation')->firstOrFail()->uuid);

        $sum = $this->summary($deal);
        $this->assertSame(1200000, $sum['earned']);
        $this->assertSame(200000, $sum['outstanding'], 'the advance is applied once to the accepted work');
        $this->assertSame(0, $sum['on_account']);
    }

    public function test_the_estimate_never_caps_measured_work(): void
    {
        [$alice, $bob, $deal, $task] = $this->masonry();

        $this->accept($bob, $deal, $this->report($alice, $deal, $task, '700'));

        $this->assertSame(210000000, $this->summary($deal)['earned']);
    }
}
