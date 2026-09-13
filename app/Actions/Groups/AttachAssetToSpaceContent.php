<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AttachAssetToSpaceContent
{
    public function execute(
        SpaceContent $content,
        User $user,
        UploadedFile $upload,
        string $rightsStatus,
        ?string $caption = null,
    ): SpaceContent {
        abort_unless(in_array($rightsStatus, Asset::RIGHTS_STATUSES, true), 422, 'Choose a valid media rights status.');

        $caption = $caption !== null ? trim($caption) : null;
        $caption = $caption === '' ? null : $caption;
        abort_if($caption !== null && mb_strlen($caption) > 1000, 422, 'Media caption may not exceed 1000 characters.');

        $size = $upload->getSize();
        abort_unless(is_int($size) && $size > 0 && $size <= 12 * 1024 * 1024, 422, 'Media must be between 1 byte and 12 MB.');

        $mime = (string) $upload->getMimeType();
        abort_unless(Asset::supportsMime($mime), 422, 'This media type is not supported.');

        $preflight = SpaceContent::query()->with('space')->findOrFail($content->id);
        Gate::forUser($user)->authorize('update', $preflight);

        $realPath = $upload->getRealPath();
        abort_unless(is_string($realPath) && is_file($realPath), 422, 'The uploaded media could not be read.');
        $sha256 = hash_file('sha256', $realPath);
        abort_unless(is_string($sha256), 422, 'The uploaded media could not be hashed.');

        $uuid = (string) Str::uuid();
        $extension = $upload->guessExtension();
        $filename = $uuid.($extension ? '.'.$extension : '.bin');
        $directory = 'assets/'.$preflight->group_space_id;
        $storageKey = $upload->storeAs($directory, $filename, 'local');
        abort_unless(is_string($storageKey), 500, 'The uploaded media could not be stored.');

        try {
            return DB::transaction(function () use (
                $content,
                $user,
                $upload,
                $rightsStatus,
                $caption,
                $mime,
                $size,
                $sha256,
                $uuid,
                $extension,
                $storageKey,
            ): SpaceContent {
                $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
                Gate::forUser($user)->authorize('update', $current);
                abort_if($current->status === 'archived', 422, 'Archived Content cannot receive media.');

                $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
                abort_unless($source instanceof SpaceContentRevision, 422, 'Content has no revision to attach media to.');
                $source = SpaceContentRevision::query()->lockForUpdate()->findOrFail($source->id);
                $actor = $this->actor($user);

                $asset = Asset::query()->create([
                    'uuid' => $uuid,
                    'group_space_id' => $current->group_space_id,
                    'original_filename' => mb_substr(basename($upload->getClientOriginalName()), 0, 255),
                    'mime_type' => $mime,
                    'extension' => $extension ? mb_substr($extension, 0, 32) : null,
                    'byte_size' => $size,
                    'disk' => 'local',
                    'storage_key' => $storageKey,
                    'sha256' => $sha256,
                    'uploaded_by_actor_id' => $actor->id,
                    'scan_status' => 'unavailable',
                    'processing_status' => 'ready',
                    'rights_status' => $rightsStatus,
                    'metadata' => null,
                ]);

                $nextRevision = ((int) $current->revisions()->max('revision')) + 1;
                $revision = $current->revisions()->create([
                    'definition_version_id' => $source->definition_version_id,
                    'revision' => $nextRevision,
                    'title' => $source->title,
                    'payload' => $source->payload,
                    'created_by_actor_id' => $actor->id,
                ]);

                $this->copyPlacements($source, $revision);
                $maxPosition = DB::table('space_content_revision_assets')
                    ->where('space_content_revision_id', $revision->id)
                    ->max('position');
                $nextPosition = $maxPosition === null ? 0 : ((int) $maxPosition) + 1;

                DB::table('space_content_revision_assets')->insert([
                    'space_content_revision_id' => $revision->id,
                    'asset_id' => $asset->id,
                    'role' => 'inline',
                    'position' => $nextPosition,
                    'caption' => $caption,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $current->applyLifecycle([
                    'current_revision' => $nextRevision,
                    'draft_revision_id' => $revision->id,
                ]);

                return $current->refresh();
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storageKey);

            throw $exception;
        }
    }

    private function copyPlacements(SpaceContentRevision $source, SpaceContentRevision $target): void
    {
        $placements = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $source->id)
            ->orderBy('position')
            ->get();

        foreach ($placements as $placement) {
            DB::table('space_content_revision_assets')->insert([
                'space_content_revision_id' => $target->id,
                'asset_id' => $placement->asset_id,
                'role' => $placement->role,
                'position' => $placement->position,
                'caption' => $placement->caption,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
