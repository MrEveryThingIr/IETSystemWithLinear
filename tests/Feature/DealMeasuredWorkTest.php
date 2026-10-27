<?php

namespace Tests\Feature;

use App\Actions\Deals\ManageDealWork;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\FulfillmentStatus;
use App\Livewire\Deals\AgreementComposer;
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
use App\Support\Deals\WorkLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealMeasuredWorkTest extends TestCase
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

    private function log(Actor $who, Relationship $deal, Commitment $c, array $fields)
    {
        $panel = $this->panel($who, $deal)->set('log.commitment', (string) $c->id);
        foreach ($fields as $key => $value) {
            $panel->set('log.'.$key, $value);
        }

        return $panel->call('logDay');
    }

    public function test_a_deliverable_based_duty_keeps_a_day_by_day_log_and_is_measured_at_inspections(): void
    {
        // Real time on purpose: Livewire discards temporary uploads that look a day old under a frozen future clock.
        [$d1, $d2, $d3] = [now()->subDays(3)->format('Y-m-d'), now()->subDays(2)->format('Y-m-d'), now()->subDay()->format('Y-m-d')];
        [$alice, $bob, $deal, $wall] = $this->wallDeal();

        // Irregular days: a long day with a photo and a rough figure, a short day where work stopped, another long day.
        $first = $this->panel($alice, $deal)->set('log.commitment', (string) $wall->id)->set('log.date', $d1)->set('log.from', '08:00')->set('log.to', '16:30')
            ->set('log.note', 'Laid the first six courses on the north side.')->set('log.estimate', '8')->set('logEvidence', [UploadedFile::fake()->image('wall.jpg', 600, 400)]);
        $first->call('logDay');
        $first->assertHasNoErrors();
        $this->log($alice, $deal, $wall, ['date' => $d2, 'from' => '09:00', 'to' => '10:15', 'note' => 'Stopped: the cement had not arrived.'])->assertHasNoErrors();
        $this->log($alice, $deal, $wall, ['date' => $d3, 'from' => '07:30', 'to' => '17:00', 'note' => 'Finished the north side; drawings missing the door opening.', 'estimate' => '12.5'])->assertHasNoErrors();

        $log = WorkLog::for($wall->fresh());
        $this->assertSame(3, $log['days']);
        $this->assertSame(510 + 75 + 570, $log['minutes']);
        $this->assertEquals(20.5, (float) $log['estimate']);
        $this->assertSame(1, collect($log['entries'])->flatMap(fn ($e) => $e['files'])->count(), 'the photo stays with its day');
        $this->assertSame(0, Fulfillment::query()->count(), 'a log is history, not a claim');
        $this->assertEquals(0, (float) app(CommitmentProgress::class)->acceptedQuantity($wall), 'and nothing is confirmed by it');
        $this->panel($bob, $deal)->assertSee('data-log-entry', false)->assertSee('wall.jpg')->assertSee('Stopped: the cement had not arrived.');

        // Only the doer logs; a day must have a start before its finish and cannot be in the future; an entry cannot be empty.
        $this->log($bob, $deal, $wall, ['date' => $d3, 'from' => '08:00', 'to' => '09:00', 'note' => 'x'])->assertStatus(403);
        $this->log($alice, $deal, $wall, ['date' => $d3, 'from' => '10:00', 'to' => '09:00', 'note' => 'x'])->assertHasErrors('work');
        $this->log($alice, $deal, $wall, ['date' => now()->addDays(5)->format('Y-m-d'), 'from' => '08:00', 'to' => '09:00', 'note' => 'x'])->assertHasErrors('work');
        $this->log($alice, $deal, $wall, ['date' => $d3, 'from' => '08:00', 'to' => '09:00', 'note' => ''])->assertHasErrors('work');
        $this->log($alice, $deal, $wall, ['date' => '', 'from' => '', 'to' => ''])->assertHasErrors(['log.date', 'log.from', 'log.to']);
        $this->assertSame(3, WorkLog::for($wall->fresh())['days']);

        // The first floor is finished: the doer asks for an inspection of what is ready (no dates, no hours).
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '18')->set('report.note', 'First floor')->call('submitReport')->assertHasNoErrors();
        $first = Fulfillment::query()->sole();
        $this->assertNull($first->actual_start_at);
        $this->assertEquals(0, (float) app(CommitmentProgress::class)->acceptedQuantity($wall));

        // The receiver measures: only the doer asks; only the receiver decides; at most what was asked; part of it counts.
        $this->panel($bob, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '1')->call('submitReport')->assertStatus(403);
        $this->panel($alice, $deal)->set('text.confirm-'.$first->id, '10')->call('review', $first->id, 'accepted')->assertStatus(403);
        $this->panel($bob, $deal)->set('text.confirm-'.$first->id, '19')->call('review', $first->id, 'accepted')->assertHasErrors('work');
        $this->panel($bob, $deal)->set('text.confirm-'.$first->id, '15')->call('review', $first->id, 'accepted')->assertHasNoErrors();
        $this->assertTrue($first->fresh()->isPartlyAccepted());
        $this->assertEquals(15, (float) app(CommitmentProgress::class)->acceptedQuantity($wall));
        $this->panel($alice, $deal)->assertSee('data-cycle-state="partly_accepted"', false);

        // The final inspection: a question, a correction, accepted in full.
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '104')->set('report.note', 'Whole wall')->call('submitReport')->assertHasNoErrors();
        $final = Fulfillment::query()->latest('id')->firstOrFail();
        $this->panel($bob, $deal)->call('review', $final->id, 'clarification_requested')->assertHasErrors('work');
        $this->panel($bob, $deal)->set('text.review-'.$final->id, 'Measured 100 on site')->call('review', $final->id, 'clarification_requested')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('text.notes-fix-'.$final->id, 'Re-measured: 100')->call('correct', $final->id, '100')->assertHasNoErrors();
        $fixed = Fulfillment::query()->where('status', FulfillmentStatus::Submitted)->latest('id')->firstOrFail();
        $this->panel($bob, $deal)->call('review', $fixed->id, 'accepted')->assertHasNoErrors();
        $this->assertEquals(115, (float) app(CommitmentProgress::class)->acceptedQuantity($wall));
    }

    public function test_inspections_cannot_exceed_the_agreed_quantity_and_each_error_is_localized_under_its_field(): void
    {
        [$alice, $bob, $deal, $wall] = $this->wallDeal();

        app()->setLocale('fa');
        $c = $this->panel($alice, $deal)->set('report.commitment', '')->set('report.quantity', 'abc')->call('submitReport');
        $c->assertHasErrors(['report.commitment', 'report.quantity'])->assertSee(__('deals.errors.report_quantity'))->assertDontSee('Commitment')->assertDontSee('Fulfillment');

        // The rough figure of a day cannot exceed the agreed quantity either, in the person's language.
        $this->log($alice, $deal, $wall, ['date' => '2026-01-05', 'from' => '08:00', 'to' => '09:00', 'note' => 'x', 'estimate' => '500'])->assertHasErrors('work')->assertSee(__('deals.errors.estimate_exceeds'));

        // 121 of 120 square metres: refused in the person's language, never in English.
        $c = $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '121')->call('submitReport');
        $c->assertHasErrors('report.quantity')->assertSee(__('deals.errors.quantity_exceeds'))->assertDontSee('exceeds the remaining');
        $this->assertSame(0, Fulfillment::query()->count());

        // Quantity waiting for a decision still counts against what can be reported.
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '100')->call('submitReport')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '21')->call('submitReport')->assertHasErrors('report.quantity');
        $this->assertSame(1, Fulfillment::query()->count());
    }
}
