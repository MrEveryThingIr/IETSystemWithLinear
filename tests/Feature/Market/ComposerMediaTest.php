<?php

namespace Tests\Feature\Market;

use App\Actions\Market\ManageIntentMedia;
use App\Actions\Market\PublishIntent;
use App\Models\ActorProfileIntent;
use App\Support\Market\IntentPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComposerMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_file_is_hidden_and_private_file_is_revoked_immediately(): void
    {
        Storage::fake('local');
        $post = ActorProfileIntent::factory()->create(['visibility' => 'public']);
        $owner = $post->profile->actor->user;
        app(PublishIntent::class)->execute($owner, $post);
        $media = app(ManageIntentMedia::class)->add($owner, $post, UploadedFile::fake()->image('example.jpg'), publish: false);
        $url = route('intents.media.show', [$post, $media]);
        $this->get($url)->assertNotFound();
        app(PublishIntent::class)->execute($owner, $post);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        app(ManageIntentMedia::class)->setVisibility($owner, $media, 'private');
        $this->get($url)->assertForbidden();
        $this->assertSame([], app(IntentPresentation::class)->published($post->fresh(), null)['media']);
        $this->actingAs($owner)->get($url)->assertOk();
    }

    public function test_twelve_files_use_the_shared_asset_pipeline_and_thirteenth_is_refused(): void
    {
        Storage::fake('local');
        $post = ActorProfileIntent::factory()->create(['status' => 'draft']);
        $owner = $post->profile->actor->user;
        for ($index = 0; $index < 12; $index++) {
            app(ManageIntentMedia::class)->add($owner, $post, UploadedFile::fake()->image($index.'.jpg'), publish: false);
        }
        $this->assertSame(12, $post->media()->count());
        $this->assertDatabaseCount('assets', 12);
        $this->expectException(ValidationException::class);
        app(ManageIntentMedia::class)->add($owner, $post, UploadedFile::fake()->image('thirteenth.jpg'), publish: false);
    }

    public function test_discard_restores_published_files_and_order_but_keeps_private_restrictions(): void
    {
        Storage::fake('local');
        $post = ActorProfileIntent::factory()->create(['visibility' => 'public']);
        $owner = $post->profile->actor->user;
        $manager = app(ManageIntentMedia::class);
        $first = $manager->add($owner, $post, UploadedFile::fake()->image('one.jpg'));
        $second = $manager->add($owner, $post, UploadedFile::fake()->image('two.jpg'));
        $manager->setVisibility($owner, $first, 'private');
        $manager->remove($owner, $first, publish: false);
        $extra = $manager->add($owner, $post, UploadedFile::fake()->image('pending.jpg'), publish: false);
        $manager->moveUp($owner, $second, publish: false);
        app(PublishIntent::class)->discard($owner, $post);
        $rows = $post->media()->get();
        $this->assertSame([$first->uuid, $second->uuid], $rows->pluck('uuid')->all());
        $this->assertSame('private', $rows->first()->visibility);
        $this->assertFalse($rows->contains('uuid', $extra->uuid));
        $this->assertDatabaseCount('assets', 3);
    }
}
