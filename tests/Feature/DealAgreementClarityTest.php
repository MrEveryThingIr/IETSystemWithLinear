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
use App\Support\Deals\AgreementDocument;
use App\Support\Deals\DealPrincipals;
use App\Support\Deals\DealTerms;
use App\Support\Deals\DealTermsWarnings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** M3 final consolidation: one Deal home, printable executed Agreement, composer units/hours/grouping, drafts, navigation. */
class DealAgreementClarityTest extends TestCase
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

    /** The owner's sample: a builder paid per square metre, a price range, daily attendance, and materials nobody is assigned. */
    private function constructionTerms(?string $materialsOwner = null, ?string $dutyOwner = null): array
    {
        return [
            'schema' => 1, 'title' => 'Brick wall',
            'price' => ['min' => '450000', 'max' => '550000', 'currency' => 'IRT', 'basis' => 'square_metre'],
            'payment' => ['mode' => 'per_period', 'period' => 'week'],
            'quantity' => ['value' => '120', 'unit' => 'square_metre'],
            'time' => ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-31', 'daily_from' => '08:00', 'daily_to' => '16:00', 'timezone' => 'Asia/Tehran'],
            'recurrence' => ['every' => 1, 'unit' => 'day', 'count' => 20],
            'completion' => ['confirmed_by' => 'beneficiary'],
            'clauses' => [
                array_filter(['key' => 'duties', 'kind' => 'duties', 'title' => 'Build the wall', 'text' => 'Lay the brick wall to the agreed drawing.', 'responsible' => $dutyOwner]),
                array_filter(['key' => 'materials', 'kind' => 'materials', 'text' => 'Bricks, cement and scaffolding.', 'responsible' => $materialsOwner]),
            ],
        ];
    }

    public function test_the_construction_agreement_points_out_what_needs_clarifying_without_changing_it(): void
    {
        $terms = DealTerms::normalize($this->constructionTerms());
        $codes = array_column(DealTermsWarnings::for($terms), 'code');

        // A range cannot be turned into an amount; attendance days do not earn square-metre wages; the period follows measured work; nobody supplies the materials.
        foreach (['price_is_a_range', 'days_do_not_earn', 'period_follows_measured', 'materials_unassigned', 'duty_unassigned'] as $expected) {
            $this->assertContains($expected, $codes);
        }
        $this->assertNotContains('unit_differs', $codes, 'the quantity unit and the price unit are the same');

        // Naming the responsible parties and an exact price clears exactly those reminders; nothing else is guessed.
        $clean = $this->constructionTerms('actor:1', 'actor:2');
        $clean['price'] = ['min' => '500000', 'max' => '500000', 'currency' => 'IRT', 'basis' => 'square_metre'];
        $after = array_column(DealTermsWarnings::for(DealTerms::normalize($clean)), 'code');
        $this->assertNotContains('price_is_a_range', $after);
        $this->assertNotContains('materials_unassigned', $after);
        $this->assertNotContains('duty_unassigned', $after);
        $this->assertContains('days_do_not_earn', $after, 'attendance is still not wages');
    }

    public function test_each_duty_names_its_responsible_party_and_the_document_and_composer_show_it(): void
    {
        [$alice, $bob, $deal] = $this->activeDeal();
        $labels = DealPrincipals::labels($deal);
        $this->assertCount(2, $labels);
        $keys = array_keys($labels);

        $c = Livewire::actingAs($alice->user)->test(AgreementComposer::class, ['relationship' => $deal])->call('start')
            ->call('addItem', 'clause:duties')->call('addItem', 'clause:materials')
            ->set('draft.clauses.0.text', 'Lay the wall.')->set('draft.clauses.0.responsible', $keys[0])
            ->set('draft.clauses.1.text', 'Bricks and cement.');
        $c->assertSeeHtml('data-clause-party')->assertSeeHtml('data-warning="materials_unassigned"');
        $c->set('draft.clauses.1.responsible', $keys[1])->call('send')->assertHasNoErrors();

        $terms = ProposalVersion::query()->latest('id')->firstOrFail()->terms_data;
        $this->assertSame([$keys[0], $keys[1]], array_column($terms['clauses'], 'responsible'));
        $doc = AgreementDocument::sections($terms, $labels);
        $this->assertSame($labels[$keys[0]], collect($doc)->firstWhere('id', 'clause:duties')['who']);
        $this->actingAs($bob->user)->get(route('relationships.show', $deal))->assertOk()->assertSee('data-clause-responsible', false);

        // A malformed party is refused by the stored-Terms authority, and kinds that are not duties never carry one.
        $this->expectException(HttpException::class);
        DealTerms::normalize(['schema' => 1, 'clauses' => [['key' => 'duties', 'kind' => 'duties', 'text' => 'x', 'responsible' => 'someone']]]);
    }
}
