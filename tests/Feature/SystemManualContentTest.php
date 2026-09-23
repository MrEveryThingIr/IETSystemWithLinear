<?php

namespace Tests\Feature;

use App\Actions\Content\EnsureSystemManualContent;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SystemManualContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

        $first = app(EnsureSystemManualContent::class)->execute($user);

        $this->assertSame(SystemManualContent::GROUP_NAME, $first['group']->name);
        $this->assertSame(SystemManualContent::SPACE_SLUG, $first['space']->slug);
        $this->assertCount(11, $first['chapters']);
        $this->assertSame('published', $first['root']->status);

        $rootRevision = $first['root']->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $rootRevision);
        $this->assertTrue($rootRevision->hasVerifiableManifest());
        $this->assertCount(11, $rootRevision->relationships()->get());

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
            $user,
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
}
