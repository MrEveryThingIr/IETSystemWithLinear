<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\AssetMediaVariant;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\MediaUploadSession;
use App\Models\SpaceContent;
use App\Support\Media\ResumableUploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class EducationalVideoUploadSessionTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private string $clip;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // Small chunks so the fixture travels in several requests, like a real large file.
        config()->set('media.resumable.chunk_kb', 1);
        $this->clip = (string) file_get_contents(base_path('tests/Fixtures/media/lesson-clip.mp4'));
    }

    public function test_video_arrives_in_chunks_resumes_and_becomes_a_content_asset_with_poster(): void
    {
        [$actor, $context, $content] = $this->lesson();

        $session = $this->createSession($actor, $context, $content)->assertCreated()->json();
        $this->assertSame('open', $session['status']);
        $this->assertSame(1024, $session['chunk_size']);

        // Send the first two chunks, then "refresh": the status says where to continue.
        $this->sendChunk($actor, $session, 0)->assertOk()->assertJson(['received_bytes' => 1024]);
        $this->sendChunk($actor, $session, 1024)->assertOk();
        $resumed = $this->actingAs($actor->user)->getJson($session['urls']['status'])->assertOk()->json();
        $this->assertSame(2048, $resumed['received_bytes']);

        // A repeated chunk is accepted without duplicating bytes; a gap is refused with the offset.
        $this->sendChunk($actor, $session, 1024)->assertOk()->assertJson(['received_bytes' => 2048]);
        $this->sendChunk($actor, $session, 4096)->assertStatus(409)->assertJson(['received_bytes' => 2048]);

        // Completing early is refused.
        $this->actingAs($actor->user)->postJson($session['urls']['complete'])->assertStatus(409);

        for ($offset = 2048; $offset < strlen($this->clip); $offset += 1024) {
            $this->sendChunk($actor, $session, $offset)->assertOk();
        }

        $done = $this->actingAs($actor->user)->post($session['urls']['complete'], [
            'duration_ms' => 1000,
            'width' => 64,
            'height' => 48,
            'poster' => UploadedFile::fake()->image('poster.jpg', 64, 48),
        ], ['Accept' => 'application/json'])->assertOk()->json();

        $asset = $content->fresh()->draftRevisionRecord()->assets()->where('assets.uuid', $done['asset_uuid'])->sole();
        $this->assertSame('video/mp4', $asset->mime_type);
        $this->assertSame(strlen($this->clip), $asset->byte_size);
        $this->assertSame(hash('sha256', $this->clip), $asset->sha256);
        $this->assertSame($this->clip, Storage::disk('local')->get($asset->storage_key));

        $original = AssetMediaVariant::query()->where('asset_id', $asset->id)->where('type', 'original')->sole();
        $this->assertSame(1000, $original->duration_ms);
        $this->assertSame(64, $original->width);
        $poster = AssetMediaVariant::query()->where('asset_id', $asset->id)->where('type', 'poster')->sole();
        Storage::disk('local')->assertExists($poster->storage_key);

        $record = MediaUploadSession::query()->where('uuid', $session['uuid'])->sole();
        $this->assertSame('completed', $record->status);
        Storage::disk('local')->assertMissing($record->temp_key);

        // Completing again is idempotent (no duplicate Asset).
        $this->actingAs($actor->user)->postJson($session['urls']['complete'])->assertOk()->assertJson(['asset_uuid' => $done['asset_uuid']]);
        $this->assertSame(1, $content->fresh()->draftRevisionRecord()->assets()->count());
    }

    public function test_finished_upload_is_placed_in_the_studio_document(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $session = $this->createSession($actor, $context, $content)->json();
        for ($offset = 0; $offset < strlen($this->clip); $offset += 1024) {
            $this->sendChunk($actor, $session, $offset)->assertOk();
        }
        $assetUuid = $this->actingAs($actor->user)->postJson($session['urls']['complete'])->assertOk()->json('asset_uuid');

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content->fresh()])
            ->call('openMediaDialog', 'video')
            ->call('placeUploadedAsset', $assetUuid)
            ->assertHasNoErrors()
            ->assertSet('mediaDialogOpen', false);

        $draft = $content->fresh()->draftRevisionRecord();
        $video = $draft->blocks()->where('type', 'video')->sole();
        $this->assertSame($assetUuid, $video->data['asset_uuid']);
        $this->assertTrue(DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $draft->id)
            ->where('uuid', $video->data['asset_placement_uuid'])
            ->exists());
    }

    public function test_only_the_uploader_with_edit_rights_can_use_a_session(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $session = $this->createSession($actor, $context, $content)->json();

        $stranger = Actor::factory()->create();
        $this->publishSurfaces($stranger->user, ['content']);

        $this->actingAs($stranger->user)->getJson($session['urls']['status'])->assertForbidden();
        $this->sendChunk($stranger, $session, 0)->assertForbidden();
        $this->createSession($stranger, $context, $content)->assertForbidden();
    }

    public function test_limits_and_types_are_enforced(): void
    {
        [$actor, $context, $content] = $this->lesson();

        config()->set('media.resumable.max_video_kb', 1);
        $this->createSession($actor, $context, $content, size: 5000)->assertStatus(422);

        config()->set('media.resumable.max_video_kb', 2048);
        $this->createSession($actor, $context, $content, mime: 'image/png')->assertStatus(422);

        $session = $this->createSession($actor, $context, $content)->json();
        $this->actingAs($actor->user)
            ->call('POST', $session['urls']['chunk'].'?offset=0', [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], str_repeat('x', 2048))
            ->assertStatus(413);
    }

    public function test_declared_video_that_is_not_a_video_is_rejected_on_completion(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $bytes = str_repeat('not a video ', 50);
        $session = $this->createSession($actor, $context, $content, size: strlen($bytes))->json();

        for ($offset = 0; $offset < strlen($bytes); $offset += 1024) {
            $this->actingAs($actor->user)
                ->call('POST', $session['urls']['chunk'].'?offset='.$offset, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], substr($bytes, $offset, 1024))
                ->assertOk();
        }

        $this->actingAs($actor->user)->postJson($session['urls']['complete'])->assertStatus(422);
        $this->assertSame('failed', MediaUploadSession::query()->where('uuid', $session['uuid'])->value('status'));
        $this->assertSame(0, $content->fresh()->draftRevisionRecord()?->assets()->count() ?? 0);
    }

    public function test_cancel_and_prune_remove_partial_files(): void
    {
        [$actor, $context, $content] = $this->lesson();

        $cancelled = $this->createSession($actor, $context, $content)->json();
        $this->sendChunk($actor, $cancelled, 0)->assertOk();
        $this->actingAs($actor->user)->deleteJson($cancelled['urls']['cancel'])->assertOk()->assertJson(['status' => 'cancelled']);
        Storage::disk('local')->assertMissing(MediaUploadSession::query()->where('uuid', $cancelled['uuid'])->value('temp_key'));

        $abandoned = $this->createSession($actor, $context, $content)->json();
        $record = MediaUploadSession::query()->where('uuid', $abandoned['uuid'])->sole();
        $record->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(ResumableUploads::class)->prune());
        $this->assertSame('expired', $record->fresh()->status);
        Storage::disk('local')->assertMissing($record->temp_key);
    }

    /** @return array{Actor, Context, SpaceContent} */
    private function lesson(): array
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['content']);
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'lesson')->firstOrFail()->activeVersionRecord();
        $this->assertNotNull($version);

        return [$actor, $context, app(CreateContentFromBlueprint::class)->execute(
            $context, $version, $actor->user, 'Video lesson', ['objective' => null, 'body' => 'Watch the clip.'],
        )];
    }

    private function createSession(Actor $actor, Context $context, SpaceContent $content, ?int $size = null, string $mime = 'video/mp4'): TestResponse
    {
        return $this->actingAs($actor->user)->postJson(route('contexts.contents.uploads.store', [$context, $content]), [
            'filename' => 'lesson-01.mp4',
            'byte_size' => $size ?? strlen($this->clip),
            'mime_type' => $mime,
            'rights_status' => 'owned',
        ]);
    }

    /** @param array<string, mixed> $session */
    private function sendChunk(Actor $actor, array $session, int $offset): TestResponse
    {
        return $this->actingAs($actor->user)->call(
            'POST',
            $session['urls']['chunk'].'?offset='.$offset,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
            substr($this->clip, $offset, 1024),
        );
    }
}
