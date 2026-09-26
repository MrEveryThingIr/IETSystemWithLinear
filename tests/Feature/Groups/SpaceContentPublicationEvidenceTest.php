<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpaceContentPublicationEvidenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publication_seals_manifest_and_snapshots_media_evidence(): void
    {
        Storage::fake('local');
        [$owner, $author, $space, $content] = $this->fixture();

        $initialHash = $content->draftRevisionRecord()?->content_hash;
        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('voice.webm', 12, 'audio/webm'),
            'owned',
            'Explanation',
        );

        $draft = $content->draftRevisionRecord();
        $this->assertNotNull($draft);
        $this->assertSame($initialHash, $draft->content_hash);
        $this->assertNull($draft->manifest_hash);

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $published = $content->activeRevisionRecord();
        $asset = Asset::query()->sole();

        $this->assertNotNull($published);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $published->manifest_hash);
        $this->assertNotNull($published->manifest_sealed_at);
        $this->assertNotSame($published->content_hash, $published->manifest_hash);
        $this->assertDatabaseHas('space_content_revision_assets', [
            'space_content_revision_id' => $published->id,
            'asset_id' => $asset->id,
            'asset_sha256_snapshot' => $asset->sha256,
            'rights_status_snapshot' => 'owned',
            'scan_status_snapshot' => 'development_exempt',
            'processing_status_snapshot' => 'ready',
            'evidence_origin' => 'development_exempt',
        ]);

        $sealedManifest = $published->manifest_hash;
        $asset->update(['rights_status' => 'licensed']);

        $this->assertDatabaseHas('space_content_revision_assets', [
            'space_content_revision_id' => $published->id,
            'asset_id' => $asset->id,
            'rights_status_snapshot' => 'owned',
        ]);
        $this->assertSame($sealedManifest, $published->fresh()->manifest_hash);
    }

    public function test_processing_must_be_ready_before_publication(): void
    {
        Storage::fake('local');
        [$owner, $author, $space, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('clip.webm', 12, 'audio/webm'),
            'owned',
        );
        $asset = Asset::query()->sole();
        $asset->update(['processing_status' => 'pending']);

        try {
            app(PublishSpaceContent::class)->execute($content, $author->user);
            $this->fail('Media that is not processing-ready was published.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $content = $content->fresh();
        $this->assertNull($content->active_revision_id);
        $this->assertNull($content->draftRevisionRecord()?->manifest_hash);
    }

    /** @return array{Actor, Actor, GroupSpace, SpaceContent} */
    private function fixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Evidence group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Evidence', 'restricted');

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
            'Evidence article',
            ['body' => 'Same authored payload'],
        );

        return [$owner, $author, $space, $content];
    }
}
