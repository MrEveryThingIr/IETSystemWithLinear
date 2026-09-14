<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\RestoreSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpaceContentReleaseContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_publication_has_stable_public_ids_and_versioned_canonical_manifest(): void
    {
        Storage::fake('local');
        [$owner, $author, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('lesson.pdf', 10, 'application/pdf'),
            'owned',
            'Lesson handout',
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $revision = $content->activeRevisionRecord();

        $this->assertInstanceOf(SpaceContentRevision::class, $revision);
        $this->assertTrue(Str::isUuid($content->uuid));
        $this->assertTrue(Str::isUuid($revision->uuid));
        $this->assertSame(SpaceContentRevision::EVIDENCE_SEALED, $revision->evidence_status);
        $this->assertSame(1, $revision->manifest_version);
        $this->assertSame(1, $revision->canonicalization_version);
        $this->assertSame('sha256', $revision->manifest_algorithm);
        $this->assertIsString($revision->canonical_manifest);
        $this->assertSame(hash('sha256', $revision->canonical_manifest), $revision->manifest_hash);

        $placementUuid = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $revision->id)
            ->value('uuid');
        $this->assertIsString($placementUuid);
        $this->assertTrue(Str::isUuid($placementUuid));
    }

    public function test_archive_and_restore_are_reversible_audited_lifecycle_transitions(): void
    {
        [$owner, $author, $content] = $this->fixture();

        $content = app(ArchiveSpaceContent::class)->execute($content, $author->user, 'Superseded while reviewing.');
        $this->assertSame('archived', $content->status);
        $this->assertNotNull($content->archived_at);
        $this->assertDatabaseHas('space_content_lifecycle_events', [
            'space_content_id' => $content->id,
            'actor_id' => $author->id,
            'event_type' => 'archived',
            'from_status' => 'draft',
            'to_status' => 'archived',
            'reason' => 'Superseded while reviewing.',
        ]);

        $content = app(RestoreSpaceContent::class)->execute($content, $author->user, 'Continue authoring this draft.');
        $this->assertSame('draft', $content->status);
        $this->assertNull($content->archived_at);
        $this->assertDatabaseHas('space_content_lifecycle_events', [
            'space_content_id' => $content->id,
            'actor_id' => $author->id,
            'event_type' => 'restored',
            'from_status' => 'archived',
            'to_status' => 'draft',
            'reason' => 'Continue authoring this draft.',
        ]);

        $events = $content->lifecycleEvents()->get();
        $this->assertCount(2, $events);
        $this->assertTrue(Str::isUuid($events->first()->uuid));
    }

    /** @return array{Actor, Actor, SpaceContent} */
    private function fixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Release contract group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Editorial', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Article',
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
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Release-ready article',
            ['body' => 'Canonical authored content.'],
        );

        return [$owner, $author, $content];
    }
}
