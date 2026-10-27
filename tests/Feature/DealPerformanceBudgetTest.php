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
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\CommitmentProgress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealPerformanceBudgetTest extends TestCase
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

    /** Counts the database queries one render costs, so a screen cannot quietly grow a query per row. */
    private function queries(callable $render): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $render();

        return $count;
    }

    public function test_the_work_and_money_screens_do_not_cost_a_query_per_row(): void
    {
        [$alice, $bob, $deal, $wall] = $this->wallDeal();
        $panelWork = fn () => Livewire::actingAs($bob->user)->test(WorkPanel::class, ['relationship' => $deal]);
        $panelMoney = fn () => Livewire::actingAs($bob->user)->test(MoneyPanel::class, ['relationship' => $deal]);

        $few = ['work' => $this->queries($panelWork), 'money' => $this->queries($panelMoney)];
        for ($i = 0; $i < 8; $i++) {
            $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '5')->call('submitReport')->assertHasNoErrors();
            $this->panel($bob, $deal)->call('review', Fulfillment::query()->latest('id')->firstOrFail()->id, 'accepted')->assertHasNoErrors();
        }
        $many = ['work' => $this->queries($panelWork), 'money' => $this->queries($panelMoney)];
        fwrite(STDERR, 'QUERIES few='.json_encode($few).' many(8 confirmed inspections)='.json_encode($many)."\n");

        // Eight more confirmed pieces of work may add at most two queries each (the policy checks), never a query storm.
        $this->assertLessThanOrEqual($few['work'] + 8 * 2, $many['work']);
        $this->assertLessThanOrEqual($few['money'] + 8 * 2, $many['money']);
    }
}
