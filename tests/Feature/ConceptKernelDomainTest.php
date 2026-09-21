<?php

namespace Tests\Feature;

use App\Actions\Concepts\AddConceptToScheme;
use App\Actions\Concepts\AssertConcept;
use App\Actions\Concepts\CreateConcept;
use App\Actions\Concepts\CreateConceptScheme;
use App\Actions\Concepts\CreateConceptVocabulary;
use App\Actions\Concepts\ManageConceptHierarchy;
use App\Actions\Concepts\ManageConceptLifecycle;
use App\Actions\Concepts\RelateConcepts;
use App\Actions\Concepts\SetConceptLabel;
use App\Actions\Groups\CreateGroup;
use App\ConceptAssertionPredicate;
use App\ConceptLabelKind;
use App\ConceptStatus;
use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use App\Models\ConceptRelation;
use App\Models\ConceptVocabulary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConceptKernelDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_multilingual_labels_preserve_one_concept_identity(): void
    {
        $actor = Actor::factory()->create();
        $vocabulary = $this->actorVocabulary($actor);
        $concept = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'chess');

        app(SetConceptLabel::class)->execute($actor->user, $concept, 'en', 'Chess');
        app(SetConceptLabel::class)->execute($actor->user, $concept, 'fa', 'شطرنج');
        app(SetConceptLabel::class)->execute(
            $actor->user,
            $concept,
            'en',
            'Royal Game',
            ConceptLabelKind::Synonym,
        );

        $this->assertSame(1, Concept::query()->count());
        $this->assertSame(3, $concept->labels()->count());
        $this->assertDatabaseHas('concept_labels', [
            'concept_id' => $concept->id,
            'locale' => 'fa',
            'label' => 'شطرنج',
            'kind' => ConceptLabelKind::Preferred->value,
        ]);
    }

    public function test_polyhierarchy_rebuilds_closure_and_rejects_cycles(): void
    {
        $actor = Actor::factory()->create();
        $vocabulary = $this->actorVocabulary($actor);
        $scheme = app(CreateConceptScheme::class)->execute(
            $actor->user,
            $vocabulary,
            'Subject',
            'subject',
        );
        $boardGames = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'board-games');
        $mindSports = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'mind-sports');
        $chess = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'chess');

        $membership = app(AddConceptToScheme::class);
        $membership->execute($actor->user, $scheme, $boardGames);
        $membership->execute($actor->user, $scheme, $mindSports);
        $membership->execute($actor->user, $scheme, $chess);

        $hierarchy = app(ManageConceptHierarchy::class);
        $boardEdge = $hierarchy->add($actor->user, $scheme, $boardGames, $chess);
        $mindEdge = $hierarchy->add($actor->user, $scheme, $mindSports, $chess);

        $this->assertDatabaseHas('concept_closure', [
            'scheme_id' => $scheme->id,
            'ancestor_concept_id' => $boardGames->id,
            'descendant_concept_id' => $chess->id,
            'min_depth' => 1,
        ]);
        $this->assertDatabaseHas('concept_closure', [
            'scheme_id' => $scheme->id,
            'ancestor_concept_id' => $mindSports->id,
            'descendant_concept_id' => $chess->id,
            'min_depth' => 1,
        ]);
        $this->assertDatabaseHas('concept_closure', [
            'scheme_id' => $scheme->id,
            'ancestor_concept_id' => $chess->id,
            'descendant_concept_id' => $chess->id,
            'min_depth' => 0,
        ]);

        $hierarchy->remove($actor->user, $mindEdge);

        $this->assertDatabaseMissing('concept_closure', [
            'scheme_id' => $scheme->id,
            'ancestor_concept_id' => $mindSports->id,
            'descendant_concept_id' => $chess->id,
        ]);

        try {
            $hierarchy->add($actor->user, $scheme, $chess, $boardGames);
            $this->fail('A hierarchy cycle was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertModelExists($boardEdge);
        $this->assertDatabaseMissing('concept_hierarchy_edges', [
            'scheme_id' => $scheme->id,
            'parent_concept_id' => $chess->id,
            'child_concept_id' => $boardGames->id,
        ]);
    }

    public function test_actor_can_assert_multiple_predicates_for_the_same_concept(): void
    {
        $actor = Actor::factory()->create();
        $vocabulary = $this->actorVocabulary($actor);
        $chess = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'chess');
        $assert = app(AssertConcept::class);

        $skill = $assert->execute(
            $actor->user,
            $actor,
            $chess,
            ConceptAssertionPredicate::HasSkill,
        );
        $learning = $assert->execute(
            $actor->user,
            $actor,
            $chess,
            ConceptAssertionPredicate::WantsToLearn,
        );

        $this->assertNotSame($skill->id, $learning->id);
        $this->assertSame(2, ConceptAssertion::query()->forSubject($actor)->count());
        $this->assertDatabaseHas('concept_assertions', [
            'subject_id' => $actor->id,
            'concept_id' => $chess->id,
            'predicate' => ConceptAssertionPredicate::HasSkill->value,
        ]);
        $this->assertDatabaseHas('concept_assertions', [
            'subject_id' => $actor->id,
            'concept_id' => $chess->id,
            'predicate' => ConceptAssertionPredicate::WantsToLearn->value,
        ]);
    }

    public function test_merge_preserves_source_identity_and_new_assertions_resolve_to_target(): void
    {
        $actor = Actor::factory()->create();
        $vocabulary = $this->actorVocabulary($actor);
        $duplicate = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'chess-game');
        $canonical = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'chess');
        $sourceUuid = $duplicate->uuid;

        $merged = app(ManageConceptLifecycle::class)->merge($actor->user, $duplicate, $canonical);
        $assertion = app(AssertConcept::class)->execute(
            $actor->user,
            $actor,
            $duplicate,
            ConceptAssertionPredicate::InterestedIn,
        );

        $this->assertSame(ConceptStatus::Merged, $merged->status);
        $this->assertSame($sourceUuid, $merged->uuid);
        $this->assertSame($canonical->id, $merged->merged_into_concept_id);
        $this->assertSame($canonical->id, $assertion->concept_id);
        $this->assertSame($canonical->id, $duplicate->refresh()->canonical()->id);
    }

    public function test_symmetric_relations_are_idempotent_in_either_direction(): void
    {
        $actor = Actor::factory()->create();
        $vocabulary = $this->actorVocabulary($actor);
        $chess = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'chess');
        $strategy = app(CreateConcept::class)->execute($actor->user, $vocabulary, 'strategy');
        $relate = app(RelateConcepts::class);

        $first = $relate->execute($actor->user, $chess, 'related_to', $strategy);
        $second = $relate->execute($actor->user, $strategy, 'related_to', $chess);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ConceptRelation::query()->count());
    }

    public function test_group_concept_governance_is_isolated_between_groups(): void
    {
        $firstOwner = Actor::factory()->create();
        $firstGroup = app(CreateGroup::class)->execute($firstOwner, 'First Concept Group', null);
        $vocabulary = app(CreateConceptVocabulary::class)->execute(
            $firstOwner->user,
            ConceptVocabularyScope::Group,
            'First Group Vocabulary',
            'first-group',
            $firstGroup,
        );

        $secondOwner = Actor::factory()->create();
        app(CreateGroup::class)->execute($secondOwner, 'Second Concept Group', null);

        $this->expectException(AuthorizationException::class);

        app(CreateConcept::class)->execute($secondOwner->user, $vocabulary, 'unauthorized-concept');
    }

    private function actorVocabulary(Actor $actor): ConceptVocabulary
    {
        return app(CreateConceptVocabulary::class)->execute(
            $actor->user,
            ConceptVocabularyScope::Actor,
            'Personal Vocabulary',
            'personal',
            $actor,
        );
    }
}
