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

    public function test_irregular_measured_work_is_reported_with_period_note_and_photo_and_only_confirmed_quantity_counts(): void
    {
        [$alice, $bob, $deal, $wall] = $this->wallDeal();

        // The doer reports a few days' work with a photo; nothing counts yet.
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '12.5')->set('report.from', '2026-12-01')->set('report.to', '2026-12-02')->set('report.note', 'North side')
            ->set('evidence', [UploadedFile::fake()->image('wall.jpg', 600, 400)])->call('submitReport')->assertHasNoErrors();
        $first = Fulfillment::query()->sole();
        $this->assertSame(FulfillmentStatus::Submitted, $first->status);
        $this->assertSame(1, $first->assets()->count(), 'the photo is kept with the report');
        $this->assertNotNull($first->actual_start_at);
        $this->assertEquals(0, (float) $this->accepted($wall));
        $this->panel($bob, $deal)->assertSee('data-cycle-state="reported"', false)->assertSee('wall.jpg');

        // Only the doer reports; only the receiver decides.
        $this->panel($bob, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '1')->call('submitReport')->assertStatus(403);
        $this->panel($alice, $deal)->set('text.confirm-'.$first->id, '10')->call('review', $first->id, 'accepted')->assertStatus(403);

        // The receiver confirms part of it: partly accepted, and exactly that quantity counts.
        $this->panel($bob, $deal)->set('text.confirm-'.$first->id, '13')->call('review', $first->id, 'accepted')->assertHasErrors('work');
        $this->panel($bob, $deal)->set('text.confirm-'.$first->id, '10')->call('review', $first->id, 'accepted')->assertHasNoErrors();
        $this->assertTrue($first->fresh()->isPartlyAccepted());
        $this->assertEquals(10, (float) $this->accepted($wall));
        $this->panel($alice, $deal)->assertSee('data-cycle-state="partly_accepted"', false);

        // A second, irregular report: a question, a correction, then accepted in full.
        $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '20')->set('report.from', '2026-12-05')->call('submitReport')->assertHasNoErrors();
        $second = Fulfillment::query()->latest('id')->firstOrFail();
        $this->panel($bob, $deal)->call('review', $second->id, 'clarification_requested')->assertHasErrors('work');
        $this->panel($bob, $deal)->set('text.review-'.$second->id, 'Measured 18 on site')->call('review', $second->id, 'clarification_requested')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('text.notes-fix-'.$second->id, 'Re-measured: 18')->call('correct', $second->id, '18')->assertHasNoErrors();
        $fixed = Fulfillment::query()->where('status', FulfillmentStatus::Submitted)->latest('id')->firstOrFail();
        $this->panel($bob, $deal)->call('review', $fixed->id, 'accepted')->assertHasNoErrors();
        $this->assertEquals(28, (float) $this->accepted($wall));
        $this->assertFalse($fixed->fresh()->isPartlyAccepted());
    }

    public function test_reports_cannot_exceed_the_agreed_quantity_and_each_error_is_localized_under_its_field(): void
    {
        [$alice, $bob, $deal, $wall] = $this->wallDeal();

        app()->setLocale('fa');
        $c = $this->panel($alice, $deal)->set('report.commitment', '')->set('report.quantity', 'abc')->call('submitReport');
        $c->assertHasErrors(['report.commitment', 'report.quantity'])->assertSee(__('deals.errors.report_quantity'))->assertDontSee('Commitment')->assertDontSee('Fulfillment');
        $c = $this->panel($alice, $deal)->set('report.commitment', (string) $wall->id)->set('report.quantity', '5')->set('report.from', '2026-12-10')->set('report.to', '2026-12-01')->call('submitReport');
        $c->assertHasErrors(['report.to'])->assertSee(__('deals.errors.report_period'));

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
