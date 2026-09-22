<?php

namespace Tests\Feature;

use App\Actions\Content\CloneContentBlueprint;
use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\ContentEvidenceTarget;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentPhase6ClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_clone_records_exact_source_version_without_mutating_source(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $source = $this->blueprint('lesson');
        $sourceVersion = $source->activeVersionRecord();

        $this->assertInstanceOf(ContentBlueprintVersion::class, $sourceVersion);

        $clone = app(CloneContentBlueprint::class)->execute(
            $context,
            $sourceVersion,
            $actor->user,
            'My Laravel lesson',
            'A reusable personal teaching recipe.',
        );

        $cloneVersion = $clone->activeVersionRecord();
        $this->assertInstanceOf(ContentBlueprintVersion::class, $cloneVersion);
        $this->assertSame(ContentBlueprint::SCOPE_ACTOR, $clone->scope);
        $this->assertSame($actor->id, $clone->owner_actor_id);
        $this->assertSame($sourceVersion->id, $clone->cloned_from_version_id);
        $this->assertNotSame($sourceVersion->id, $cloneVersion->id);
        $this->assertSame($sourceVersion->content_hash, $cloneVersion->content_hash);
        $this->assertSame($sourceVersion->id, $source->refresh()->active_version_id);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $cloneVersion,
            $actor->user,
            'Laravel routing',
            [
                'objective' => 'Understand route model binding.',
                'body' => 'Build and inspect a small routing example.',
            ],
        );

        $this->assertSame($cloneVersion->id, $content->content_blueprint_version_id);
    }

    public function test_personal_media_can_be_published_and_cited_as_exact_asset_evidence(): void
    {
        Storage::fake('local');

        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $content = $this->content(
            $actor,
            $context,
            'evidence-work-sample',
            'Laravel media evidence',
            [
                'work_date' => null,
                'role' => 'Backend developer',
                'summary' => 'Implemented a Laravel feature and documented the result.',
                'outcome' => 'Reviewed.',
            ],
        );

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $actor->user,
            UploadedFile::fake()->image('proof.jpg', 640, 480),
            'owned',
            'Screenshot of the completed work.',
        );

        $draft = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);

        $placement = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $draft->id)
            ->first();
        $this->assertNotNull($placement);

        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);
        $published = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $published);

        $reference = app(CreateContentEvidenceReference::class)->execute(
            $content,
            $published,
            $actor->user,
            ContentEvidenceTarget::Asset,
            (string) $placement->uuid,
        );

        $this->assertSame(ContentEvidenceTarget::Asset, $reference->target_type);
        $this->assertSame((string) $placement->uuid, $reference->target_uuid);
        $this->assertNull($content->group_space_id);
        $this->assertSame($context->id, $content->context_id);

        $this->actingAs($actor->user)
            ->followingRedirects()
            ->get(route('content-evidence.show', $reference))
            ->assertOk()
            ->assertSee('Laravel media evidence')
            ->assertSee('Screenshot of the completed work.');
    }

    public function test_group_space_booklet_can_contain_lesson_through_same_content_kernel(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Blueprint classroom', null);
        $space = $group->spaces()->sole();
        $context = app(EnsureGroupSpaceContext::class)->execute($space);

        $lesson = $this->content(
            $owner,
            $context,
            'lesson',
            'Lesson One',
            [
                'objective' => 'Learn one reusable idea.',
                'body' => 'Lesson body.',
            ],
        );
        $lesson = app(PublishSpaceContent::class)->execute($lesson, $owner->user);

        $book = $this->content(
            $owner,
            $context,
            'book-booklet',
            'Class Booklet',
            ['summary' => 'A small course booklet.'],
        );
        $book = app(UpdateSpaceContentStructure::class)->execute(
            $book,
            $owner->user,
            [$lesson->id],
        );
        $book = app(PublishSpaceContent::class)->execute($book, $owner->user);

        $this->assertSame($context->id, $book->context_id);
        $this->assertSame($space->id, $book->group_space_id);

        $this->actingAs($owner->user)
            ->get(route('contexts.contents.show', [$context, $book]))
            ->assertOk()
            ->assertSee('Class Booklet')
            ->assertSee('Lesson One');
    }

    public function test_exact_sealed_revision_permalink_keeps_old_edition_after_new_publication(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $content = $this->content(
            $actor,
            $context,
            'note-diary',
            'First diary edition',
            ['body' => 'Original immutable entry.'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);
        $first = $content->activeRevisionRecord();

        $this->assertInstanceOf(SpaceContentRevision::class, $first);
        $permalink = route('contexts.contents.revisions.show', [$context, $content, $first]);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $actor->user,
            'Second diary edition',
            ['body' => 'Later mutable work became a new publication.'],
        );
        app(PublishSpaceContent::class)->execute($content, $actor->user);

        $this->actingAs($actor->user)
            ->get($permalink)
            ->assertRedirect();

        $this->actingAs($actor->user)
            ->followingRedirects()
            ->get($permalink)
            ->assertOk()
            ->assertSee('First diary edition')
            ->assertSee('Original immutable entry.')
            ->assertDontSee('Second diary edition')
            ->assertDontSee('Later mutable work became a new publication.');
    }

    private function blueprint(string $slug): ContentBlueprint
    {
        app(EnsureSystemContentBlueprints::class)->execute();

        return ContentBlueprint::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function content(
        Actor $actor,
        Context $context,
        string $blueprintSlug,
        string $title,
        array $payload,
    ): SpaceContent {
        $version = $this->blueprint($blueprintSlug)->activeVersionRecord();
        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        return app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            $title,
            $payload,
        );
    }
}
