<?php

namespace App\Actions\Profile;

use App\Jobs\ProcessAssetMedia;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileImage;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadActorProfileImage
{
    private const MAX_IMAGES = 12;

    /** @var list<string> */
    private const MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
    ];

    public function execute(User $user, ActorProfile $profile, UploadedFile $upload): ActorProfileImage
    {
        Gate::forUser($user)->authorize('update', $profile);

        $size = $upload->getSize();
        abort_unless(is_int($size) && $size > 0 && $size <= 8 * 1024 * 1024, 422, 'Profile image must be between 1 byte and 8 MB.');

        $mime = (string) $upload->getMimeType();
        abort_unless(in_array($mime, self::MIME_TYPES, true), 422, 'Profile image must be JPEG, PNG, WebP, or AVIF.');

        $realPath = $upload->getRealPath();
        abort_unless(is_string($realPath) && is_file($realPath), 422, 'The uploaded profile image could not be read.');

        $dimensions = @getimagesize($realPath);
        abort_unless(is_array($dimensions), 422, 'The uploaded file is not a valid image.');

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        abort_unless($width >= 128 && $height >= 128, 422, 'Profile image must be at least 128 by 128 pixels.');
        abort_unless($width <= 8000 && $height <= 8000 && ($width * $height) <= 40_000_000, 422, 'Profile image dimensions are too large.');

        $sha256 = hash_file('sha256', $realPath);
        abort_unless(is_string($sha256), 422, 'The uploaded profile image could not be hashed.');

        $uuid = (string) Str::uuid();
        $extension = $upload->guessExtension();
        $filename = $uuid.($extension ? '.'.$extension : '.bin');
        $storageKey = $upload->storeAs('profile-assets/'.$profile->actor_id, $filename, 'local');
        abort_unless(is_string($storageKey), 500, 'The profile image could not be stored.');

        try {
            return DB::transaction(function () use (
                $user,
                $profile,
                $upload,
                $mime,
                $size,
                $width,
                $height,
                $sha256,
                $uuid,
                $extension,
                $storageKey,
            ): ActorProfileImage {
                $lockedProfile = ActorProfile::query()->lockForUpdate()->findOrFail($profile->id);
                Gate::forUser($user)->authorize('update', $lockedProfile);

                abort_if(
                    $lockedProfile->images()->count() >= self::MAX_IMAGES,
                    422,
                    'A profile can keep up to 12 profile images.',
                );

                $actor = $this->actor($user, $lockedProfile);
                $developmentReady = app()->environment(['local', 'testing']);

                $asset = Asset::query()->create([
                    'uuid' => $uuid,
                    'group_space_id' => null,
                    'original_filename' => mb_substr(basename($upload->getClientOriginalName()), 0, 255),
                    'mime_type' => $mime,
                    'extension' => $extension ? mb_substr($extension, 0, 32) : null,
                    'byte_size' => $size,
                    'disk' => 'local',
                    'storage_key' => $storageKey,
                    'sha256' => $sha256,
                    'uploaded_by_actor_id' => $actor->id,
                    'scan_status' => $developmentReady ? 'unavailable' : 'quarantined',
                    'scan_error' => null,
                    'processing_status' => $developmentReady ? 'ready' : 'pending',
                    'processing_error' => null,
                    'processing_completed_at' => $developmentReady ? now() : null,
                    'readiness_verified_at' => $developmentReady ? now() : null,
                    'rights_status' => 'owned',
                    'alt_text' => $lockedProfile->display_name,
                    'metadata' => [
                        'purpose' => 'actor_profile_image',
                        'width' => $width,
                        'height' => $height,
                    ],
                ]);

                $position = ((int) $lockedProfile->images()->max('position')) + 1;
                $image = $lockedProfile->images()->create([
                    'asset_id' => $asset->id,
                    'position' => $position,
                ]);

                if ($lockedProfile->display_profile_image_id === null) {
                    $lockedProfile->setDisplayImage($image);
                }

                if (! $developmentReady) {
                    ProcessAssetMedia::dispatch($asset->id)->afterCommit();
                }

                return $image->load('asset');
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);

            throw $exception;
        }
    }

    private function actor(User $user, ActorProfile $profile): Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->actor instanceof Actor
            && (int) $current->actor->id === (int) $profile->actor_id,
            403,
        );

        return $current->actor;
    }
}
