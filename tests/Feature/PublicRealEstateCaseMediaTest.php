<?php

namespace Tests\Feature;

use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRealEstateCaseMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_submission_can_include_private_media(): void
    {
        Storage::fake('local');
        $portal = PublicIntakePortal::query()->create(['type' => 'real_estate', 'title' => 'دفتر نمونه', 'locale' => 'fa', 'is_active' => true]);

        $this->post(route('public.real-estate.store', $portal), [
            'intent' => 'offer', 'transaction_mode' => 'sale', 'contact_name' => 'نمونه', 'phone' => '09121234567',
            'property_class' => 'residential', 'price_unit' => 'toman',
            'images' => [UploadedFile::fake()->image('front.jpg', 1200, 800)],
            'audios' => [UploadedFile::fake()->create('note.webm', 500, 'audio/webm')],
            'videos' => [UploadedFile::fake()->create('walkthrough.mp4', 2500, 'video/mp4')],
        ])->assertRedirect();

        $case = PublicRealEstateCase::query()->with('media')->sole();
        $this->assertCount(3, $case->media);
        foreach ($case->media as $media) {
            Storage::disk('local')->assertExists($media->path);
        }
    }

    public function test_ungranted_user_cannot_stream_private_media(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $portal = PublicIntakePortal::query()->create(['type' => 'real_estate', 'title' => 'دفتر نمونه', 'locale' => 'fa', 'is_active' => true]);
        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->getKey(), 'reference_code' => 'RE-MEDIAAAAAA', 'intent' => 'offer', 'transaction_mode' => 'sale',
            'contact_name' => 'نمونه', 'phone' => '09121234567', 'property_class' => 'residential', 'status' => 'new', 'preview_token_hash' => hash('sha256', 'x'),
        ]);
        Storage::disk('local')->put('real-estate-cases/'.$case->getKey().'/image/private.jpg', 'bytes');
        $media = $case->media()->create(['kind' => 'image', 'origin' => 'upload', 'disk' => 'local', 'path' => 'real-estate-cases/'.$case->getKey().'/image/private.jpg', 'original_name' => 'private.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 5]);

        $this->actingAs($user)->get(route('office.real-estate.media.stream', ['portal' => $portal->uuid, 'case' => $case, 'media' => $media]))->assertForbidden();
    }

    public function test_published_and_portal_granted_user_can_stream_private_media(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $portal = PublicIntakePortal::query()->create(['type' => 'real_estate', 'title' => 'دفتر نمونه', 'locale' => 'fa', 'is_active' => true]);
        PublicIntakePortalGrant::query()->create(['public_intake_portal_id' => $portal->getKey(), 'user_id' => $user->getKey(), 'role' => 'viewer']);
        app(FeatureSurfaceGrantService::class)->sync($user, ['business'], null);

        $case = PublicRealEstateCase::query()->create([
            'public_intake_portal_id' => $portal->getKey(), 'reference_code' => 'RE-MEDIABBBBB', 'intent' => 'offer', 'transaction_mode' => 'sale',
            'contact_name' => 'نمونه', 'phone' => '09121234567', 'property_class' => 'residential', 'status' => 'new', 'preview_token_hash' => hash('sha256', 'x'),
        ]);
        Storage::disk('local')->put('real-estate-cases/'.$case->getKey().'/image/private.jpg', 'bytes');
        $media = $case->media()->create(['kind' => 'image', 'origin' => 'upload', 'disk' => 'local', 'path' => 'real-estate-cases/'.$case->getKey().'/image/private.jpg', 'original_name' => 'private.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 5]);

        $this->actingAs($user)->get(route('office.real-estate.media.stream', ['portal' => $portal->uuid, 'case' => $case, 'media' => $media]))->assertOk();
    }
}
