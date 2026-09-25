<?php

namespace Tests\Feature;

use App\Actions\Content\EnsureSystemManualContent;
use App\Actions\Content\RecordContentAnnotationDisposition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\ContextKind;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentAnnotationDisposition;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SystemManualContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SystemManualContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manual_is_materialized_as_versioned_annotatable_content_and_is_idempotent(): void
    {
        $user = User::factory()->create([
            'email' => 'manual.owner@example.com',
        ]);
        $actor = $user->actor()->create();
        $this->assertInstanceOf(Actor::class, $actor);

        $reader = Actor::factory()->create();
        $first = app(EnsureSystemManualContent::class)->execute($user);

        $this->assertSame(ContextKind::Reference, $first['context']->kind);
        $this->assertCount(20, $first['chapters']);
        $this->assertTrue(Gate::forUser($reader->user)->allows('view', $first['context']));
        $this->assertTrue(Gate::forUser($reader->user)->allows('interactContent', $first['context']));
        $this->assertFalse(Gate::forUser($reader->user)->allows('createContent', $first['context']));
        $this->assertFalse(Gate::forUser($reader->user)->allows('manageContent', $first['context']));
        $this->assertTrue(Gate::forUser($user)->allows('manageContent', $first['context']));
        $this->actingAs($reader->user)
            ->get(route('manual'))
            ->assertRedirect(route('contexts.contents.show', [
                $first['context'],
                $first['root'],
                'manual' => 1,
            ]));
        $this->actingAs($reader->user)
            ->get(route('contexts.contents.show', [$first['context'], $first['root']]))
            ->assertOk()
            ->assertSee('1. The IET Mental Model');

        $submissionChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['submissions'],
        );
        $this->assertNotNull($submissionChapter);

        $endToEndChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['end-to-end'],
        );
        $this->assertNotNull($endToEndChapter);

        $relationshipChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['relationships'],
        );
        $this->assertNotNull($relationshipChapter);

        $conversationChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['conversation-timeline'],
        );
        $this->assertNotNull($conversationChapter);

        $plannerChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['planner'],
        );
        $this->assertNotNull($plannerChapter);

        $accountingChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['accounting'],
        );
        $this->assertNotNull($accountingChapter);

        $proposalChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['proposals'],
        );
        $this->assertNotNull($proposalChapter);

        $contractChapter = $first['chapters']->first(
            static fn ($content): bool => $content->activeRevisionRecord()?->title
                === SystemManualContent::CHAPTER_TITLES['contracts'],
        );
        $this->assertNotNull($contractChapter);

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'submissions']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $submissionChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'relationships']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $relationshipChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'conversation-timeline']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $conversationChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'planner']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $plannerChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'accounting']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $accountingChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'proposals']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $proposalChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->actingAs($reader->user)
            ->get(route('manual', ['topic' => 'contracts']))
            ->assertRedirect(
                route('contexts.contents.show', [
                    $first['context'],
                    $contractChapter,
                    'manual' => 1,
                ]).'#field-how_to_use',
            );

        $this->assertSame('published', $first['root']->status);

        $rootRevision = $first['root']->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $rootRevision);
        $this->assertTrue($rootRevision->hasVerifiableManifest());
        $this->assertCount(20, $rootRevision->relationships()->get());

        $chapter = $first['chapters']->first();
        $this->assertNotNull($chapter);
        $this->assertSame([
            'annotations' => true,
            'reactions' => true,
            'default_annotation_visibility' => 'space',
        ], $chapter->interaction_settings);

        $chapterRevision = $chapter->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $chapterRevision);
        $this->assertTrue($chapterRevision->hasVerifiableManifest());
        $this->assertArrayHasKey('current_behavior', $chapterRevision->payload);
        $this->assertArrayHasKey('ideal_target', $chapterRevision->payload);
        $this->assertNotSame(
            trim((string) $chapterRevision->payload['current_behavior']),
            trim((string) $chapterRevision->payload['ideal_target']),
        );

        $idea = app(AddSpaceContentAnnotation::class)->execute(
            $chapter,
            $chapterRevision,
            $reader->user,
            'This target section could explain the future capability more clearly.',
            kind: SpaceContentAnnotation::KIND_IDEA,
            visibility: SpaceContentAnnotation::VISIBILITY_SPACE,
            anchors: [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_FIELD,
                'target_uuid' => null,
                'field_key' => 'ideal_target',
                'selector' => ['intent' => 'comment'],
            ]],
        );

        $this->assertSame(SpaceContentAnnotation::KIND_IDEA, $idea->kind);
        $this->assertSame('ideal_target', $idea->anchors->sole()->field_key);

        $revisionCounts = $first['chapters']
            ->mapWithKeys(static fn ($content): array => [$content->id => $content->revisions()->count()])
            ->all();
        $rootRevisionCount = $first['root']->revisions()->count();

        $second = app(EnsureSystemManualContent::class)->execute($user);

        $this->assertSame($first['root']->id, $second['root']->id);
        $this->assertSame($rootRevisionCount, $second['root']->revisions()->count());

        foreach ($second['chapters'] as $content) {
            $this->assertSame($revisionCounts[$content->id], $content->revisions()->count());
        }

        $this->assertDatabaseHas('content_blueprints', [
            'slug' => 'guide-documentation',
            'scope' => ContentBlueprint::SCOPE_SYSTEM,
            'status' => ContentBlueprint::STATUS_ACTIVE,
        ]);
    }

    public function test_feedback_can_be_accepted_and_linked_to_the_later_official_revision_that_incorporated_it(): void
    {
        $manager = Actor::factory()->create();
        $reader = Actor::factory()->create();
        $manual = app(EnsureSystemManualContent::class)->execute($manager->user);
        $chapter = $manual['chapters']->first();
        $this->assertNotNull($chapter);

        $oldRevision = $chapter->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $oldRevision);

        $idea = app(AddSpaceContentAnnotation::class)->execute(
            $chapter,
            $oldRevision,
            $reader->user,
            'Please clarify how this target should behave for a normal user.',
            kind: SpaceContentAnnotation::KIND_IDEA,
            visibility: SpaceContentAnnotation::VISIBILITY_SPACE,
            anchors: [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_FIELD,
                'target_uuid' => null,
                'field_key' => 'ideal_target',
                'selector' => ['intent' => 'comment'],
            ]],
        );

        $accepted = app(RecordContentAnnotationDisposition::class)->execute(
            $idea,
            $manager->user,
            SpaceContentAnnotationDisposition::STATUS_ACCEPTED,
            note: 'Accepted for the next official manual edition.',
        );

        $this->assertSame(SpaceContentAnnotationDisposition::STATUS_ACCEPTED, $accepted->status);

        $payload = $oldRevision->payload;
        $payload['ideal_target'] = trim((string) $payload['ideal_target'])
            ."\n\nThe target experience must also remain understandable to a first-time user.";

        $chapter = app(ReviseSpaceContent::class)->execute(
            $chapter,
            $manager->user,
            $oldRevision->title,
            $payload,
        );
        $chapter = app(PublishSpaceContent::class)->execute($chapter, $manager->user);
        $newRevision = $chapter->activeRevisionRecord();

        $this->assertInstanceOf(SpaceContentRevision::class, $newRevision);
        $this->assertGreaterThan($oldRevision->revision, $newRevision->revision);
        $this->assertTrue($newRevision->hasVerifiableManifest());

        $this->actingAs($manager->user)
            ->get(route('contexts.contents.show', [
                $manual['context'],
                $chapter,
                'revision' => $oldRevision->uuid,
            ]))
            ->assertOk()
            ->assertSee('Accepted')
            ->assertSee('Incorporated in current edition');

        $incorporated = app(RecordContentAnnotationDisposition::class)->execute(
            $idea,
            $manager->user,
            SpaceContentAnnotationDisposition::STATUS_INCORPORATED,
            incorporatedRevision: $newRevision,
            note: 'Clarification incorporated into the official manual.',
        );

        $this->assertSame($newRevision->id, $incorporated->incorporated_revision_id);
        $this->assertSame($oldRevision->id, $idea->space_content_revision_id);
        $this->assertSame(
            SpaceContentAnnotationDisposition::STATUS_INCORPORATED,
            $idea->fresh()->latestDisposition()->firstOrFail()->status,
        );
    }

    public function test_bootstrap_preserves_authorized_manual_edits_until_explicit_source_sync(): void
    {
        $manager = Actor::factory()->create();
        $manual = app(EnsureSystemManualContent::class)->execute($manager->user);
        $chapter = $manual['chapters']->first();
        $this->assertNotNull($chapter);

        $official = $chapter->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $official);

        $editedPayload = $official->payload;
        $editedPayload['summary'] = 'Maintainer-authored official clarification.';

        $chapter = app(ReviseSpaceContent::class)->execute(
            $chapter,
            $manager->user,
            $official->title,
            $editedPayload,
        );
        $chapter = app(PublishSpaceContent::class)->execute($chapter, $manager->user);
        $edited = $chapter->activeRevisionRecord();

        $this->assertInstanceOf(SpaceContentRevision::class, $edited);
        $this->assertSame('Maintainer-authored official clarification.', $edited->payload['summary']);

        app(EnsureSystemManualContent::class)->execute($manager->user);

        $preserved = $chapter->fresh()->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $preserved);
        $this->assertSame($edited->id, $preserved->id);
        $this->assertSame('Maintainer-authored official clarification.', $preserved->payload['summary']);

        app(EnsureSystemManualContent::class)->execute($manager->user, syncSource: true);

        $synced = $chapter->fresh()->activeRevisionRecord();
        $source = app(SystemManualContent::class)->english()['chapters'][0];

        $this->assertInstanceOf(SpaceContentRevision::class, $synced);
        $this->assertGreaterThan($edited->revision, $synced->revision);
        $this->assertSame($source['summary'], $synced->payload['summary']);
        $this->assertTrue($synced->hasVerifiableManifest());
    }
}
