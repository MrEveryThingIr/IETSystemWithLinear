<?php

namespace Tests\Feature;

use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Deals\AgreementComposer;
use App\Livewire\Intents\Composer;
use App\Livewire\Relationships\Create as DealStart;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\ProposalDecisionKind;
use App\Support\Deals\DealTerms;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealAgreementValidationTest extends TestCase
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

    private function composer(Actor $who, Relationship $deal)
    {
        return Livewire::actingAs($who->user)->test(AgreementComposer::class, ['relationship' => $deal])->call('start');
    }

    public function test_price_errors_are_in_the_persons_language_and_sit_under_their_own_field(): void
    {
        app()->setLocale('fa');
        [$alice, $bob, $deal] = $this->activeDeal();

        $c = $this->composer($alice, $deal)->call('addItem', 'price')->set('draft.core.price_min', 'abc')->set('draft.core.basis_choice', 'hour')->call('send');
        $c->assertHasErrors(['draft.core.price_min'])->assertSee(__('deals.validation.amount'))->assertDontSee('Deal Terms')->assertDontSee('minimum');
        $this->assertSame(0, ProposalVersion::query()->count());

        // A range must be in order, and an amount needs something to be "per".
        $c = $this->composer($alice, $deal)->call('addItem', 'price')->set('draft.core.price_mode', 'range')->set('draft.core.price_min', '900')->set('draft.core.price_max', '100')->set('draft.core.basis_choice', 'hour')->call('send');
        $c->assertHasErrors(['draft.core.price_max'])->assertSee(__('deals.validation.price_order'));
        $c = $this->composer($alice, $deal)->call('addItem', 'price')->set('draft.core.price_min', '900')->set('draft.core.basis_choice', '')->call('send');
        $c->assertHasErrors(['draft.core.basis_choice'])->assertSee(__('deals.validation.basis_required'));
        $c = $this->composer($alice, $deal)->call('addItem', 'price')->set('draft.core.price_mode', 'range')->set('draft.core.price_min', '900')->set('draft.core.price_max', '')->set('draft.core.basis_choice', 'hour')->call('send');
        $c->assertHasErrors(['draft.core.price_max']);
    }

    public function test_amounts_in_persian_digits_with_separators_are_accepted_and_kept_as_plain_digits(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $c = $this->composer($alice, $deal)->call('addItem', 'price')->set('draft.core.price_min', '۳٬۰۰۰٬۰۰۰')->set('draft.core.basis_choice', 'square_metre');
        $c->assertSee(DealTerms::priceLabel(['min' => '3000000', 'max' => '3000000', 'currency' => 'IRT', 'basis' => 'square_metre']), false);
        $c->call('send')->assertHasNoErrors();
        $price = ProposalVersion::query()->latest('id')->firstOrFail()->terms_data['price'];
        $this->assertSame(['min' => '3000000', 'max' => '3000000', 'currency' => 'IRT', 'basis' => 'square_metre'], $price);
    }

    public function test_dates_hours_quantities_and_choices_each_report_under_their_own_field(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $c = $this->composer($alice, $deal)->call('addItem', 'time')->set('draft.core.starts_on', '2026-12-10')->set('draft.core.ends_on', '2026-12-01')->set('draft.core.daily_from', '09:00')->set('draft.core.daily_to', '')->call('send');
        $c->assertHasErrors(['draft.core.ends_on', 'draft.core.daily_to']);
        $c = $this->composer($alice, $deal)->call('addItem', 'quantity')->set('draft.core.quantity', '-3')->call('send');
        $c->assertHasErrors(['draft.core.quantity']);
        $c = $this->composer($alice, $deal)->call('addItem', 'payment')->call('addItem', 'completion')->call('send');
        $c->assertHasErrors(['draft.payment.mode', 'draft.completion.confirmed_by']);
        $c = $this->composer($alice, $deal)->call('addItem', 'recurrence')->set('draft.recurrence.unit', 'day')->set('draft.recurrence.count', '5000')->call('send');
        $c->assertHasErrors(['draft.recurrence.count']);
        $this->assertSame(0, ProposalVersion::query()->count());
    }

    public function test_a_fixed_price_is_one_number_that_the_money_tab_can_use(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $this->composer($alice, $deal)->call('addItem', 'price')->set('draft.core.price_min', '500000')->set('draft.core.basis_choice', 'square_metre')->call('send')->assertHasNoErrors();
        $price = ProposalVersion::query()->latest('id')->firstOrFail()->terms_data['price'];
        $this->assertSame($price['min'], $price['max']);
    }
}
