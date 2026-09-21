<?php

namespace Tests\Feature;

use App\Actions\Concepts\AssertConcept;
use App\Actions\Concepts\CreateConcept;
use App\Actions\Concepts\CreateConceptVocabulary;
use App\Actions\Concepts\ManageConceptLifecycle;
use App\Actions\Concepts\SetConceptLabel;
use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use App\Models\ConceptVocabulary;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConceptKernelPublicationEvidenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_content_and_exact_revision_semantics_have_distinct_evidence_lifecycles(): void
    {
        [$author, $content] = $this->contentFixture();
        [$vocabulary, $chess] = $this->chessConcept($author);
        $draft = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);

        $assert = app(AssertConcept::class);
        $catalogAssertion = $assert->execute(
            $author->user,
            $content,
            $chess,
            ConceptAssertionPredicate::About,
        );
        $revisionAssertion = $assert->execute(
            $author->user,
            $draft,
            $chess,
            ConceptAssertionPredicate::Teaches,
            weight: 0.75,
            confidence: 0.9,
            metadata: ['evidence' => 'author_selected'],
        );

        $this->assertSame(
            1,
            ConceptAssertion::query()->forSubject($content)->count(),
        );
        $this->assertSame($chess->id, $catalogAssertion->concept_id);

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $published = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $published);
        $this->assertSame(3, $published->manifest_version);

        $manifest = json_decode(
            (string) $published->canonical_manifest,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertCount(1, $manifest['semantic_assertions']);
        $semantic = $manifest['semantic_assertions'][0];

        $this->assertSame($revisionAssertion->uuid, $semantic['assertion_uuid']);
        $this->assertSame(ConceptAssertionPredicate::Teaches->value, $semantic['predicate']);
        $this->assertSame($chess->uuid, $semantic['concept_uuid']);
        $this->assertNull($semantic['scheme_uuid']);
        $this->assertSame('0.7500', $semantic['weight']);
        $this->assertSame('0.9000', $semantic['confidence']);
        $this->assertSame('manual', $semantic['source']);
        $this->assertSame('inherited', $semantic['visibility']);
        $this->assertSame(['evidence' => 'author_selected'], $semantic['metadata']);

        try {
            $assert->execute(
                $author->user,
                $published,
                $chess,
                ConceptAssertionPredicate::FocusesOn,
            );
            $this->fail('A semantic assertion was added to a sealed published revision.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        try {
            $revisionAssertion->update(['weight' => 0.5]);
            $this->fail('Published revision semantic evidence was rewritten.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Published revision-bound Concept assertions are immutable evidence.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('concept_assertions', [
            'id' => $revisionAssertion->id,
            'subject_type' => ConceptAssertionSubject::SpaceContentRevision->value,
            'subject_id' => $published->id,
            'concept_id' => $chess->id,
            'predicate' => ConceptAssertionPredicate::Teaches->value,
        ]);

        $sealedManifest = $published->manifest_hash;

        app(SetConceptLabel::class)->execute($author->user, $chess, 'en', 'Chess Game');
        $canonical = app(CreateConcept::class)->execute(
            $author->user,
            $vocabulary,
            'chess-strategy',
        );
        app(ManageConceptLifecycle::class)->merge($author->user, $chess, $canonical);

        $this->assertSame($sealedManifest, $published->fresh()->manifest_hash);
        $this->assertSame(
            $chess->uuid,
            json_decode(
                (string) $published->fresh()->canonical_manifest,
                true,
                flags: JSON_THROW_ON_ERROR,
            )['semantic_assertions'][0]['concept_uuid'],
        );
    }

    public function test_revision_semantics_are_copied_forward_as_new_draft_evidence(): void
    {
        [$author, $content] = $this->contentFixture();
        [, $chess] = $this->chessConcept($author);
        $draft = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);

        $sourceAssertion = app(AssertConcept::class)->execute(
            $author->user,
            $draft,
            $chess,
            ConceptAssertionPredicate::Teaches,
        );

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $published = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $published);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Chess lesson revised',
            ['body' => 'Revised lesson body.'],
        );
        $nextDraft = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $nextDraft);

        $copied = ConceptAssertion::query()
            ->forSubject($nextDraft)
            ->where('predicate', ConceptAssertionPredicate::Teaches->value)
            ->sole();

        $this->assertNotSame($sourceAssertion->uuid, $copied->uuid);
        $this->assertSame($chess->id, $copied->concept_id);
        $this->assertSame($author->id, $copied->created_by_actor_id);
        $this->assertSame(
            $sourceAssertion->uuid,
            $copied->metadata['copied_from_assertion_uuid'] ?? null,
        );
        $this->assertFalse($nextDraft->hasVerifiableManifest());

        $additional = app(AssertConcept::class)->execute(
            $author->user,
            $nextDraft,
            $chess,
            ConceptAssertionPredicate::About,
        );

        $this->assertNotSame($copied->id, $additional->id);
        $this->assertSame(2, ConceptAssertion::query()->forSubject($nextDraft)->count());
    }

    /** @return array{Actor, SpaceContent} */
    private function contentFixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Semantic Content Group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Learning', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute(
            $space,
            $owner,
            $owner->user,
            'allow',
            'manager',
        );
        app(SetGroupSpaceParticipant::class)->execute(
            $space,
            $author,
            $owner->user,
            'allow',
            'participant',
        );

        $definition = $this->activeDefinition($space, $owner);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Chess lesson',
            ['body' => 'Opening principles.'],
        );

        return [$author, $content];
    }

    private function activeDefinition(GroupSpace $space, Actor $owner): SpaceContentDefinition
    {
        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Lesson',
            null,
            [[
                'key' => 'body',
                'label' => 'Body',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );

        return app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);
    }

    /** @return array{ConceptVocabulary, Concept} */
    private function chessConcept(Actor $author): array
    {
        $vocabulary = app(CreateConceptVocabulary::class)->execute(
            $author->user,
            ConceptVocabularyScope::Actor,
            'Author Concepts',
            'author-concepts',
            $author,
        );
        $concept = app(CreateConcept::class)->execute(
            $author->user,
            $vocabulary,
            'chess',
        );
        app(SetConceptLabel::class)->execute($author->user, $concept, 'en', 'Chess');

        return [$vocabulary, $concept];
    }
}
