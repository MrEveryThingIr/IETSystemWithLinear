<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Deals\AmendAgreement;
use App\Actions\Deals\ManageDealMoney;
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
use App\Models\Plan;
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
    private function masonry(bool $withTask = true, ?array $terms = null, bool $agreement = true): array
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

        if (! $agreement) {
            return [$alice, $bob, $deal, null];
        }
        app(ProposeDealTerms::class)->execute($deal, $alice->user, ['schema' => 1] + ($terms ?? ['title' => 'Masonry',
            'quantity' => ['value' => '650', 'unit' => 'square_metre'],
            'price' => ['min' => '300000', 'max' => '300000', 'currency' => 'IRT', 'basis' => 'square_metre'],
            'payment' => ['mode' => 'after_confirmed_delivery'], 'completion' => ['confirmed_by' => 'beneficiary']]));
        app(RespondToProposal::class)->execute(Proposal::query()->latest('id')->firstOrFail(), $bob->user, ProposalDecisionKind::Accepted, null, 1);
        $task = $withTask ? app(ManageDealWork::class)->createTask($deal->fresh(), $bob->user, 'Floors') : null;

        return [$alice, $bob, $deal->fresh(), $task];
    }

    private function report(Actor $doer, Relationship $deal, Commitment|string $task, string $quantity): Fulfillment
    {
        Livewire::actingAs($doer->user)->test(WorkPanel::class, ['relationship' => $deal])
            ->set('report.commitment', $task instanceof Commitment ? (string) $task->id : $task)->set('report.quantity', $quantity)->call('submitReport')->assertHasNoErrors();

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

    private function rateTerms(string $masonry = '300000'): array
    {
        return ['title' => 'Villa works', 'rates' => [
            ['key' => 'masonry', 'label' => 'Masonry', 'unit' => 'square_metre', 'rate' => $masonry, 'estimate' => '650'],
            ['key' => 'plaster', 'label' => 'Plastering', 'unit' => 'square_metre', 'rate' => '150000'],
        ], 'payment' => ['mode' => 'after_confirmed_delivery'], 'completion' => ['confirmed_by' => 'beneficiary']];
    }

    public function test_agreed_work_categories_are_reported_without_any_task_and_paid_at_their_own_rates(): void
    {
        [$alice, $bob, $deal] = $this->masonry(false, $this->rateTerms());
        $money = app(ManageDealMoney::class);

        $this->accept($bob, $deal, $this->report($alice, $deal, 'rate:masonry', '12'));
        $this->accept($bob, $deal, $this->report($alice, $deal, 'rate:masonry', '15'), '10');
        $this->report($alice, $deal, 'rate:masonry', '8'); // awaiting the employer
        $this->accept($bob, $deal, $this->report($alice, $deal, 'rate:plaster', '10'));

        $this->assertSame(2, Commitment::query()->count(), 'one duty per agreed kind of work, not one per day');
        $this->assertSame(3, Transaction::query()->count(), 'each accepted report is recorded once');
        foreach (Transaction::query()->get() as $entry) {
            $money->approve($deal, $alice->user, $entry->uuid);
        }
        $this->assertSame(8100000, $this->summary($deal)['earned'], '6,600,000 masonry + 1,500,000 plastering');

        $payment = $money->payOnAccount($deal->fresh(), $bob->user, '2000000', 'pay-r');
        $money->approve($deal->fresh(), $alice->user, $payment->uuid);
        $this->assertSame(6100000, $this->summary($deal)['outstanding']);
    }

    public function test_a_later_amendment_never_reprices_work_already_accepted(): void
    {
        [$alice, $bob, $deal] = $this->masonry(false, $this->rateTerms());
        $this->accept($bob, $deal, $this->report($alice, $deal, 'rate:masonry', '10'));
        $this->assertSame(3000000, $this->summary($deal)['earned']);

        app(AmendAgreement::class)->execute(Contract::query()->sole(), $alice->user, ['schema' => 1] + $this->rateTerms('400000'), now()->addDay(), 'Asia/Tehran');
        app(AcceptContractVersion::class)->execute(Contract::query()->sole()->pendingVersionRecord(), $bob->user);
        $this->travel(3)->days();
        app(ActivateDueContractVersions::class)->executeForContract(Contract::query()->sole());
        $this->accept($bob, $deal->fresh(), $this->report($alice, $deal->fresh(), 'rate:masonry', '5'));

        $this->assertSame(3000000 + 2000000, $this->summary($deal)['earned'], 'accepted 10 m² stay at 300,000; the new 5 m² are at 400,000');
    }

    public function test_reciprocal_duties_stay_two_separate_debts(): void
    {
        [$alice, $bob, $deal] = $this->masonry(false, $this->rateTerms());
        $this->accept($bob, $deal, $this->report($alice, $deal, 'rate:masonry', '10')); // Bob owes Alice 3,000,000
        $this->accept($alice, $deal, $this->report($bob, $deal, 'rate:plaster', '20')); // Alice owes Bob 3,000,000

        $this->assertSame(2, Transaction::query()->where('type', 'obligation')->count());
        $this->assertSame(2, Transaction::query()->where('type', 'obligation')->distinct()->count('from_party_id'), 'one debt in each direction');
        $this->assertSame(6000000, $this->summary($deal)['earned']);
    }

    public function test_each_party_sees_the_same_money_in_their_own_words_in_english_and_persian(): void
    {
        [$alice, $bob, $deal] = $this->masonry(false, $this->rateTerms());
        $this->accept($bob, $deal, $this->report($alice, $deal, 'rate:masonry', '10'));
        app(ManageDealMoney::class)->approve($deal, $alice->user, Transaction::query()->firstOrFail()->uuid);
        $page = fn (Actor $who) => Livewire::actingAs($who->user)->test(MoneyPanel::class, ['relationship' => $deal->fresh()]);

        foreach (['en' => [['Owed to me', 'Received', 'Still owed to me'], ['I owe', 'I paid', 'Still to pay']], 'fa' => [['طلب من', 'دریافتی', 'مانده طلب'], ['بدهی من', 'پرداختی', 'مانده بدهی']]] as $locale => [$payee, $payer]) {
            app()->setLocale($locale);
            $doer = $page($alice);
            $employer = $page($bob);
            foreach ($payee as $label) {
                $doer->assertSeeHtml('>'.$label.'</dt>');
                $employer->assertDontSeeHtml('>'.$label.'</dt>');
            }
            foreach ($payer as $label) {
                $employer->assertSeeHtml('>'.$label.'</dt>');
                $doer->assertDontSeeHtml('>'.$label.'</dt>');
            }
            $doer->assertSeeHtml('data-sum="owed"')->assertSee('3,000,000');
            $employer->assertSeeHtml('data-sum="remaining"')->assertSee('3,000,000');
        }
    }

    public function test_work_rates_are_written_in_the_agreement_composer_and_saved_as_terms(): void
    {
        [$alice, , $deal] = $this->masonry(false, null, false);

        Livewire::actingAs($alice->user)->test(AgreementComposer::class, ['relationship' => $deal])->call('start')->set('draft.title', 'Villa')
            ->call('addItem', 'rates')->set('draft.rates.0.label', 'Masonry')->set('draft.rates.0.rate', '۳۰۰٬۰۰۰')->set('draft.rates.0.estimate', '650')
            ->call('addRate')->set('draft.rates.1.label', 'Plastering')->set('draft.rates.1.rate', '150000')
            ->call('addItem', 'payment')->set('draft.payment.mode', 'after_confirmed_delivery')
            ->call('send')->assertHasNoErrors();

        $rates = Proposal::query()->latest('id')->firstOrFail()->versions()->latest('id')->firstOrFail()->terms_data['rates'];
        $this->assertSame(['Masonry', '300000', '650'], [$rates[0]['label'], $rates[0]['rate'], $rates[0]['estimate']]);
        $this->assertSame('150000', $rates[1]['rate']);
    }

    public function test_work_is_scheduled_from_the_deal_and_the_plan_is_linked_to_it_for_both_people(): void
    {
        [$alice, $bob, $deal, $task] = $this->masonry();
        $date = now()->addDay()->format('Y-m-d');

        Livewire::actingAs($bob->user)->test(WorkPanel::class, ['relationship' => $deal])
            ->set('sched.'.$task->id.'.date', $date)->set('sched.'.$task->id.'.time', '08:00')->set('sched.'.$task->id.'.hours', '4')
            ->set('sched.'.$task->id.'.repeat', 'daily')->set('sched.'.$task->id.'.times', '5')
            ->call('schedule', $task->id)->assertHasNoErrors();

        $plan = $task->fresh()->planBinding->plan;
        $this->assertSame(5, $plan->occurrences()->count());
        $this->assertSame('commitment', $plan->origin_type);
        $this->assertSame($task->uuid, $plan->origin_uuid);
        foreach ([$alice, $bob] as $who) {
            Livewire::actingAs($who->user)->test(WorkPanel::class, ['relationship' => $deal])->assertSeeHtml('data-task-plan-link')->assertSeeHtml('data-cycle');
        }

        // Scheduling again is refused and never makes a second plan.
        Livewire::actingAs($bob->user)->test(WorkPanel::class, ['relationship' => $deal])
            ->set('sched.'.$task->id.'.date', $date)->call('schedule', $task->id)->assertHasErrors('work');
        $this->assertSame(1, Plan::query()->count());
    }

    public function test_the_same_acceptance_submitted_twice_records_one_debt(): void
    {
        [$alice, $bob, $deal] = $this->masonry(false, $this->rateTerms());
        $f = $this->report($alice, $deal, 'rate:masonry', '10');
        $this->accept($bob, $deal, $f);
        app(ManageDealMoney::class)->recordAccepted($deal->fresh(), $bob->user, $f->fresh());
        app(ManageDealMoney::class)->recordAccepted($deal->fresh(), $bob->user, $f->fresh());

        $this->assertSame(1, Transaction::query()->count());
    }
}
