<?php

namespace Tests\Feature;

use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\RemoveActorProfileImage;
use App\Actions\Profile\SetDisplayedProfileImage;
use App\Actions\Profile\UpdateActorProfile;
use App\Actions\Profile\UploadActorProfileImage;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileImage;
use App\Models\Asset;
use App\ProfileVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActorProfileFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_verified_actor_gets_one_private_profile_with_public_uuid(): void
    {
        $actor = Actor::factory()->create();

        $first = app(EnsureActorProfile::class)->execute($actor->user);
        $second = app(EnsureActorProfile::class)->execute($actor->user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($actor->id, $first->actor_id);
        $this->assertSame(ProfileVisibility::Private, $first->visibility);
        $this->assertNotEmpty($first->public_id);
        $this->assertStringContainsString($first->public_id, route('profiles.show', $first));
        $this->assertSame(1, ActorProfile::query()->count());
    }

    public function test_profile_visibility_is_privacy_first_and_public_view_does_not_expose_email(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        $this->get(route('profiles.show', $profile))->assertForbidden();

        $viewer = Actor::factory()->create();
        $this->actingAs($viewer->user)
            ->get(route('profiles.show', $profile))
            ->assertForbidden();

        $profile = app(UpdateActorProfile::class)->execute($actor->user, $profile, [
            'display_name' => 'Professional Name',
            'headline' => 'Systems Builder',
            'bio' => 'A professional profile biography.',
            'location_text' => 'Toronto',
            'website_url' => 'https://example.com',
            'visibility' => ProfileVisibility::Authenticated->value,
        ]);

        $this->actingAs($viewer->user)
            ->get(route('profiles.show', $profile))
            ->assertOk()
            ->assertSee('Professional Name')
            ->assertDontSee($actor->user->email);

        $this->get(route('profiles.show', $profile))->assertOk();

        auth()->logout();

        $this->get(route('profiles.show', $profile))->assertForbidden();

        $profile = app(UpdateActorProfile::class)->execute($actor->user, $profile, [
            'display_name' => 'Professional Name',
            'headline' => 'Systems Builder',
            'bio' => 'A professional profile biography.',
            'location_text' => 'Toronto',
            'website_url' => 'https://example.com',
            'visibility' => ProfileVisibility::Public->value,
        ]);

        $this->get(route('profiles.show', $profile))
            ->assertOk()
            ->assertSee('Professional Name')
            ->assertDontSee($actor->user->email);
    }

    public function test_another_actor_cannot_edit_a_profile(): void
    {
        $owner = Actor::factory()->create();
        $intruder = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);

        $this->expectException(AuthorizationException::class);

        app(UpdateActorProfile::class)->execute($intruder->user, $profile, [
            'display_name' => 'Hijacked',
            'headline' => null,
            'bio' => null,
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Public->value,
        ]);
    }

    public function test_profile_image_upload_uses_private_asset_pipeline_and_first_image_becomes_displayed(): void
    {
        Storage::fake('local');

        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $upload = UploadedFile::fake()->image('portrait.jpg', 512, 512)->size(300);

        $image = app(UploadActorProfileImage::class)->execute($actor->user, $profile, $upload);
        $profile->refresh();

        $this->assertSame($image->id, $profile->display_profile_image_id);
        $this->assertDatabaseHas('assets', [
            'id' => $image->asset_id,
            'group_space_id' => null,
            'uploaded_by_actor_id' => $actor->id,
            'mime_type' => 'image/jpeg',
            'rights_status' => 'owned',
        ]);
        $this->assertSame('actor_profile_image', $image->asset->metadata['purpose'] ?? null);
        Storage::disk('local')->assertExists($image->asset->storage_key);

        $this->actingAs($actor->user)
            ->get(route('profiles.images.show', [$profile, $image]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $other = Actor::factory()->create();
        $this->actingAs($other->user)
            ->get(route('profiles.images.show', [$profile, $image]))
            ->assertForbidden();
    }

    public function test_display_image_can_change_clear_and_remove_without_orphaning_storage(): void
    {
        Storage::fake('local');

        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $upload = app(UploadActorProfileImage::class);

        $first = $upload->execute(
            $actor->user,
            $profile,
            UploadedFile::fake()->image('first.jpg', 400, 400),
        );
        $second = $upload->execute(
            $actor->user,
            $profile,
            UploadedFile::fake()->image('second.png', 400, 400),
        );

        $profile = app(SetDisplayedProfileImage::class)->execute($actor->user, $profile, $second);
        $this->assertSame($second->id, $profile->display_profile_image_id);

        $profile = app(SetDisplayedProfileImage::class)->execute($actor->user, $profile, null);
        $this->assertNull($profile->display_profile_image_id);

        $storageKey = $first->asset->storage_key;
        app(RemoveActorProfileImage::class)->execute($actor->user, $profile, $first);

        $this->assertDatabaseMissing('actor_profile_images', ['id' => $first->id]);
        $this->assertDatabaseMissing('assets', ['id' => $first->asset_id]);
        Storage::disk('local')->assertMissing($storageKey);

        $this->assertModelExists(ActorProfileImage::query()->findOrFail($second->id));
        $this->assertModelExists(Asset::query()->findOrFail($second->asset_id));
    }

    public function test_profile_image_upload_rejects_non_image_media(): void
    {
        Storage::fake('local');

        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        try {
            app(UploadActorProfileImage::class)->execute(
                $actor->user,
                $profile,
                UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            );
            $this->fail('Non-image media was accepted as a profile image.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(0, ActorProfileImage::query()->count());
        $this->assertSame(0, Asset::query()->whereNull('group_space_id')->count());
    }
}
