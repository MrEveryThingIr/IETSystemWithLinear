<?php

namespace Tests\Feature;

use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Deals\ManageDealWork;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\FulfillmentStatus;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Deals\WorkPanel;
use App\Livewire\Intents\Composer;
use App\Livewire\Relationships\Create as DealStart;
use App\Livewire\Relationships\Show as DealShow;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\Fulfillment;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\Deals\DealLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealWorkTest extends TestCase
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

    /** @return array<string, int> actor id => ContractVersionParty id of the version in force */
    private function parties(): array
    {
        return Contract::query()->sole()->activeVersionRecord()->parties->pluck('id', 'actor_id')->all();
    }

    private function duty(array $over = []): array
    {
        return $over + ['title' => 'Clean the office', 'kind' => 'service', 'quantity' => '2', 'unit' => 'hour', 'cycles' => 3, 'obligor_party' => 0, 'beneficiary_party' => 0,
            'starts_on' => now()->addDay()->format('Y-m-d'), 'start_time' => '09:00', 'duration' => 120, 'every' => 1, 'period' => 'day'];
    }

    public function test_a_duty_becomes_work_only_after_the_receiving_party_confirms(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agreed($alice, $bob, $deal);
        $p = $this->parties();
        $work = app(ManageDealWork::class);

        $item = $work->propose($deal, $alice->user, $this->duty(['obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id]]));
        $this->assertSame(0, Commitment::query()->count());
        $this->assertCount(1, $work->pending($deal->fresh()));

        // The person who would do the work cannot confirm it for the receiver; a stranger cannot do anything.
        $this->assertSame(403, $this->http(fn () => $work->confirm($deal->fresh(), $alice->user, $item['id'])));
        $this->assertSame(403, $this->http(fn () => $work->confirm($deal->fresh(), $this->account()->user, $item['id'])));
        $this->assertSame(0, Commitment::query()->count());

        $commitment = $work->confirm($deal->fresh(), $bob->user, $item['id']);
        $this->assertSame($alice->id, (int) $commitment->obligor_actor_id);
        $this->assertSame($bob->id, (int) $commitment->beneficiary_actor_id);
        $this->assertEquals(6, (float) $commitment->quantity);
        $this->assertSame(3, $commitment->planBinding->plan->occurrences()->count());
        $this->assertSame([], $work->pending($deal->fresh()));

        // Confirming again creates nothing: the pending item is gone, and the derivation key is unique.
        $this->assertSame(404, $this->http(fn () => $work->confirm($deal->fresh(), $bob->user, $item['id'])));
        $this->assertSame(1, Commitment::query()->count());
    }

    private function panel(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(WorkPanel::class, ['relationship' => $deal]);
    }

    /** Set the form and propose, as the person would in the Work tab. */
    private function proposeInPanel(Actor $who, Relationship $deal, array $form)
    {
        $c = $this->panel($who, $deal);
        foreach ($form as $key => $value) {
            if ($key === 'unit') {
                $known = in_array($value, WorkPanel::UNITS, true);
                $c->set('form.unit_choice', $known ? $value : '__other')->set('form.unit_custom', $known ? '' : $value);

                continue;
            }
            if (in_array($key, ['kind', 'beneficiary'], true)) {
                continue; // the receiver is the other party; every duty here is plain work
            }
            $c->set('form.'.$key, $value);
        }

        return $c->call('propose')->assertHasNoErrors();
    }

    private function pendingId(Relationship $deal, string $title): string
    {
        return collect(app(ManageDealWork::class)->pending($deal->fresh()))->firstWhere('title', $title)['id'];
    }

    public function test_reciprocal_work_runs_end_to_end_with_cycles_partial_correction_and_dispute(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agreed($alice, $bob, $deal);
        $p = $this->parties();
        $start = now()->addDay()->startOfDay()->addHours(9);

        // Both sides owe something: Alice cleans three days; Bob provides the key once.
        $this->proposeInPanel($alice, $deal, ['title' => 'Clean the office', 'kind' => 'service', 'quantity' => '2', 'unit' => 'hour', 'cycles' => 3, 'obligor' => $p[$alice->id], 'beneficiary' => $p[$bob->id], 'starts_on' => $start->format('Y-m-d'), 'start_time' => '09:00', 'duration' => 120]);
        $this->proposeInPanel($bob, $deal, ['title' => 'Provide the key', 'kind' => 'resource', 'quantity' => '1', 'unit' => 'key', 'cycles' => 1, 'obligor' => $p[$bob->id], 'beneficiary' => $p[$alice->id]]);
        $this->assertSame(0, Commitment::query()->count(), 'a proposal is not an obligation');

        // Only the receiving side sees Confirm; the proposer cannot confirm their own proposal for the other side.
        $this->panel($alice, $deal)->assertSee(__('deals.work.waiting_receiver', ['name' => $bob->user->username]))->call('confirm', $this->pendingId($deal, 'Clean the office'))->assertStatus(403);
        $this->panel($bob, $deal)->call('confirm', $this->pendingId($deal, 'Clean the office'))->assertHasNoErrors();
        $this->panel($alice, $deal)->call('confirm', $this->pendingId($deal, 'Provide the key'))->assertHasNoErrors();
        $this->assertSame(2, Commitment::query()->count());
        $clean = Commitment::query()->where('title', 'Clean the office')->sole();
        $key = Commitment::query()->where('title', 'Provide the key')->sole();
        $this->assertSame($bob->id, (int) $clean->beneficiary_actor_id);
        $this->assertSame($bob->id, (int) $key->obligor_actor_id);
        $occurrences = $clean->planBinding->plan->occurrences()->orderBy('scheduled_start_at')->get();
        $this->assertCount(3, $occurrences);

        // Cycle 1: finishing it in the Planner alone proves nothing.
        $this->travelTo($occurrences[0]->scheduled_start_at->copy()->addMinutes(30));
        app(TransitionPlanOccurrence::class)->markDone($occurrences[0], $alice->user);
        $this->assertSame(0, Fulfillment::query()->count());
        $this->panel($bob, $deal)->assertSee('data-cycle-state="open"', false);

        // Bob cannot report Alice's work; Alice reports cycle 1 and Bob confirms it.
        $this->panel($bob, $deal)->call('report', $clean->id, $occurrences[0]->uuid, '2')->assertStatus(403);
        $this->panel($alice, $deal)->call('report', $clean->id, $occurrences[0]->uuid, '2')->assertHasNoErrors();
        $f1 = Fulfillment::query()->sole();
        $this->assertSame(FulfillmentStatus::Submitted, $f1->status);
        $this->panel($alice, $deal)->call('review', $f1->id, 'accepted')->assertStatus(403);
        $this->panel($bob, $deal)->call('review', $f1->id, 'accepted')->assertHasNoErrors();
        $this->assertSame(FulfillmentStatus::Accepted, $f1->fresh()->status);

        // Cycle 2 is only partly done; the confirmed amount is what counts.
        $this->travelTo($occurrences[1]->scheduled_start_at->copy()->addMinutes(30));
        $this->panel($alice, $deal)->call('report', $clean->id, $occurrences[1]->uuid, '1')->assertHasNoErrors();
        $f2 = Fulfillment::query()->where('plan_occurrence_id', $occurrences[1]->id)->sole();
        $this->panel($bob, $deal)->call('review', $f2->id, 'accepted');
        $this->panel($bob, $deal)->assertSee('data-cycle-state="accepted"', false); // what was reported was fully accepted; the cycle's shortfall shows as 1 of 2

        // Cycle 3: Bob asks a question, Alice corrects, Bob rejects the correction, Alice disputes, Bob resolves.
        $this->travelTo($occurrences[2]->scheduled_start_at->copy()->addMinutes(30));
        $this->panel($alice, $deal)->call('report', $clean->id, $occurrences[2]->uuid, '2');
        $f3 = Fulfillment::query()->where('plan_occurrence_id', $occurrences[2]->id)->sole();
        $this->panel($bob, $deal)->call('review', $f3->id, 'clarification_requested')->assertHasErrors('work'); // a question needs a note
        $this->panel($bob, $deal)->set('text.review-'.$f3->id, 'Which rooms?')->call('review', $f3->id, 'clarification_requested')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('text.notes-fix-'.$f3->id, 'All three rooms')->call('correct', $f3->id, '2')->assertHasNoErrors();
        $f3b = Fulfillment::query()->where('plan_occurrence_id', $occurrences[2]->id)->where('status', FulfillmentStatus::Submitted)->sole();
        $this->panel($bob, $deal)->set('text.review-'.$f3b->id, 'Not clean')->call('review', $f3b->id, 'rejected')->assertHasNoErrors();
        $this->panel($alice, $deal)->set('text.dispute-'.$f3b->id, 'It was clean')->call('dispute', $f3b->id)->assertHasNoErrors();
        $this->assertSame(FulfillmentStatus::Disputed, $f3b->fresh()->status);
        $this->panel($bob, $deal)->assertSee('data-cycle-state="disputed"', false)->call('resolve', $f3b->dispute->id, 'accepted')->assertHasNoErrors();
        $this->assertSame(FulfillmentStatus::Accepted, $f3b->fresh()->status);

        // The one-time duty: Bob reports, Alice confirms. Nothing here touched money.
        $this->panel($bob, $deal)->call('report', $key->id, null, '1')->assertHasNoErrors();
        $this->panel($alice, $deal)->call('review', Fulfillment::query()->where('commitment_id', $key->id)->sole()->id, 'accepted')->assertHasNoErrors();
        $this->assertSame(0, DB::table('bk_ledgers')->count());

        // The old Commitment page for a Deal's work opens the Work tab instead.
        $this->actingAs($alice->user)->get(route('commitments.show', $clean))->assertRedirect(DealLinks::workspace($deal, 'work'));
    }

    public function test_an_amendment_never_rewrites_existing_work_and_old_proposals_must_be_made_again(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agreed($alice, $bob, $deal);
        $p = $this->parties();
        $this->proposeInPanel($alice, $deal, ['title' => 'Deliver the car', 'kind' => 'deliverable', 'quantity' => '1', 'unit' => 'car', 'cycles' => 1, 'obligor' => $p[$alice->id], 'beneficiary' => $p[$bob->id]]);
        $this->panel($bob, $deal)->call('confirm', $this->pendingId($deal, 'Deliver the car'));
        $this->proposeInPanel($alice, $deal, ['title' => 'Wash the car', 'kind' => 'service', 'quantity' => '1', 'unit' => 'wash', 'cycles' => 1, 'obligor' => $p[$alice->id], 'beneficiary' => $p[$bob->id]]);
        $v1 = Contract::query()->sole()->activeVersionRecord();
        $delivered = Commitment::query()->sole();
        $this->assertSame($v1->id, (int) $delivered->contract_version_id);

        // Amend the Agreement; the new version takes over at its effective time.
        Livewire::actingAs($alice->user)->test(AgreementComposer::class, ['relationship' => $deal])->call('startAmendment')->call('addItem', 'place')->set('draft.core.place', 'Karaj')->call('send')->assertHasNoErrors();
        Livewire::actingAs($bob->user)->test(DealShow::class, ['relationship' => $deal])->call('acceptContract');
        $this->travel(3)->days();
        app(ActivateDueContractVersions::class)->executeForContract(Contract::query()->sole());
        $v2 = Contract::query()->sole()->activeVersionRecord();
        $this->assertNotSame($v1->id, $v2->id);

        // The finished obligation keeps its original version; the proposal made under v1 cannot be confirmed under v2.
        $this->assertSame($v1->id, (int) $delivered->fresh()->contract_version_id);
        $this->panel($bob, $deal)->assertSee(__('deals.work.stale'))->call('confirm', $this->pendingId($deal, 'Wash the car'))->assertHasErrors('work');
        $this->assertSame(1, Commitment::query()->count());

        // Existing work still runs under its own version: Alice reports, Bob confirms.
        $this->panel($alice, $deal)->call('report', $delivered->id, null, '1')->assertHasNoErrors();
        $this->panel($bob, $deal)->call('review', Fulfillment::query()->sole()->id, 'accepted')->assertHasNoErrors();

        // Work proposed again under v2 is bound to v2.
        $p2 = Contract::query()->sole()->activeVersionRecord()->parties->pluck('id', 'actor_id')->all();
        $this->panel($alice, $deal)->call('withdraw', $this->pendingId($deal, 'Wash the car'));
        $this->proposeInPanel($alice, $deal, ['title' => 'Wash the car', 'kind' => 'service', 'quantity' => '1', 'unit' => 'wash', 'cycles' => 1, 'obligor' => $p2[$alice->id], 'beneficiary' => $p2[$bob->id]]);
        $this->panel($bob, $deal)->call('confirm', $this->pendingId($deal, 'Wash the car'))->assertHasNoErrors();
        $this->assertSame($v2->id, (int) Commitment::query()->where('title', 'Wash the car')->sole()->contract_version_id);
        $this->assertSame($v1->id, (int) Commitment::query()->where('title', 'Deliver the car')->sole()->contract_version_id);
    }

    public function test_work_rules_are_enforced_before_anything_is_created(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->agreed($alice, $bob, $deal);
        $p = $this->parties();
        $base = ['title' => 'Task', 'kind' => 'work', 'quantity' => '1', 'unit' => 'item', 'cycles' => 1, 'obligor' => $p[$alice->id], 'beneficiary' => $p[$bob->id]];

        // Money is not set up here, the same person cannot be both sides, repeated work needs a start, a stranger cannot propose.
        $form = ['title' => 'Task', 'quantity' => '1', 'unit_choice' => 'item', 'cycles' => 1, 'obligor' => $p[$alice->id]] + $this->blankSchedule();
        $this->assertSame(422, $this->http(fn () => app(ManageDealWork::class)->propose($deal, $alice->user, $this->duty(['kind' => 'payment', 'obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id]]))));
        $this->assertSame(422, $this->http(fn () => app(ManageDealWork::class)->propose($deal, $alice->user, $this->duty(['obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$alice->id]]))));
        // In the Work tab each problem shows under its own field, in the person's language.
        $this->panel($alice, $deal)->set('form', ['title' => '', 'obligor' => '', 'quantity' => 'x', 'unit_choice' => ''] + $form)->call('propose')->assertHasErrors(['form.title', 'form.obligor', 'form.quantity', 'form.unit_choice']);
        $this->panel($alice, $deal)->set('form', ['cycles' => 3] + $form)->call('propose')->assertHasErrors(['form.starts_on']);
        $this->assertSame([], app(ManageDealWork::class)->pending($deal->fresh()));
        $this->assertSame(403, $this->http(fn () => app(ManageDealWork::class)->propose($deal, $this->account()->user, $this->duty(['obligor_party' => $p[$alice->id], 'beneficiary_party' => $p[$bob->id]]))));

        // A deal without an agreement in force offers no work at all.
        [$carol, $dan, $open] = $this->activeDeal();
        $this->panel($carol, $open)->assertSee(__('deals.work.need_agreement'))->assertDontSeeHtml('data-work-form');
        $this->assertSame(0, Commitment::query()->count());

        $this->panel($bob, $deal)->call('confirm', 'missing')->assertStatus(404);
    }

    private function blankSchedule(): array
    {
        return ['starts_on' => '', 'start_time' => '09:00', 'duration' => 60, 'every' => 1, 'period' => 'day'];
    }

    public function test_when_the_agreement_needs_no_confirmation_a_report_closes_the_cycle_without_a_decision(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->compose($alice, $deal)->set('draft.title', 'Garden help')->call('addItem', 'completion')->set('draft.completion.confirmed_by', 'none')->call('send')->assertHasNoErrors();
        app(RespondToProposal::class)->execute(Proposal::query()->latest('id')->firstOrFail(), $bob->user, ProposalDecisionKind::Accepted, null, 1);
        $p = $this->parties();

        $this->proposeInPanel($alice, $deal, ['title' => 'Water the plants', 'kind' => 'service', 'quantity' => '1', 'unit' => 'visit', 'cycles' => 1, 'obligor' => $p[$alice->id], 'beneficiary' => $p[$bob->id]]);
        $this->panel($bob, $deal)->call('confirm', $this->pendingId($deal, 'Water the plants'));
        $duty = Commitment::query()->sole();

        $this->panel($alice, $deal)->call('report', $duty->id, null, '1')->assertHasNoErrors();
        $this->panel($bob, $deal)->assertSee(__('deals.work.confirmed_by.none'))->assertSee('data-cycle-state="reported_final"', false);
        $this->assertSame(FulfillmentStatus::Submitted, Fulfillment::query()->sole()->status, 'no decision is invented: the report simply stands');
        $this->assertSame(0, DB::table('bk_ledgers')->count());
    }
}
