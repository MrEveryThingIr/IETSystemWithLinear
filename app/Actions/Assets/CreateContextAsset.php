<?php

namespace App\Actions\Assets;

use App\Jobs\ProcessAssetMedia;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Context;
use App\Models\User;
use App\Support\ContextScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CreateContextAsset
{
    public function execute(
        Context $context,
        User $user,
        UploadedFile $upload,
        string $rightsStatus,
        ?string $sourceAttribution = null,
        ?string $altText = null,
    ): Asset {
        abort_unless(in_array($rightsStatus, Asset::RIGHTS_STATUSES, true), 422, 'Choose a valid media rights status.');

        $sourceAttribution = $this->optionalText($sourceAttribution, 2000, 'Source attribution');
        $altText = $this->optionalText($altText, 1000, 'Alternative text');

        $size = $upload->getSize();
        abort_unless(is_int($size) && $size > 0 && $size <= 12 * 1024 * 1024, 422, 'Media must be between 1 byte and 12 MB.');

        $mime = (string) $upload->getMimeType();
        abort_unless(Asset::supportsMime($mime), 422, 'This media type is not supported.');

        $currentContext = Context::query()->findOrFail($context->id);
        Gate::forUser($user)->authorize('submitInteractions', $currentContext);

        $realPath = $upload->getRealPath();
        abort_unless(is_string($realPath) && is_file($realPath), 422, 'The uploaded media could not be read.');
        $sha256 = hash_file('sha256', $realPath);
        abort_unless(is_string($sha256), 422, 'The uploaded media could not be hashed.');

        $uuid = (string) Str::uuid();
        $extension = $upload->guessExtension();
        $filename = $uuid.($extension ? '.'.$extension : '.bin');
        $storageKey = $upload->storeAs(ContextScope::storageSegment($currentContext).'/assets', $filename, 'local');
        abort_unless(is_string($storageKey), 500, 'The uploaded media could not be stored.');

        try {
            return DB::transaction(function () use (
                $currentContext,
                $user,
                $upload,
                $rightsStatus,
                $sourceAttribution,
                $altText,
                $mime,
                $size,
                $sha256,
                $uuid,
                $extension,
                $storageKey,
            ): Asset {
                $lockedContext = Context::query()->lockForUpdate()->findOrFail($currentContext->id);
                Gate::forUser($user)->authorize('submitInteractions', $lockedContext);

                $actor = $this->actor($user);
                $developmentReady = app()->environment(['local', 'testing']);

                $asset = Asset::query()->create([
                    'uuid' => $uuid,
                    'context_id' => $lockedContext->id,
                    'group_space_id' => ContextScope::legacyGroupSpaceId($lockedContext),
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
                    'rights_status' => $rightsStatus,
                    'source_attribution' => $sourceAttribution,
                    'alt_text' => $altText,
                    'metadata' => null,
                ]);

                if (! $developmentReady) {
                    ProcessAssetMedia::dispatch($asset->id)->afterCommit();
                }

                return $asset;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);

            throw $exception;
        }
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }

    private function optionalText(?string $value, int $max, string $label): ?string
    {
        $value = $value !== null ? trim($value) : null;
        $value = $value === '' ? null : $value;
        abort_if($value !== null && mb_strlen($value) > $max, 422, "{$label} may not exceed {$max} characters.");

        return $value;
    }
}
