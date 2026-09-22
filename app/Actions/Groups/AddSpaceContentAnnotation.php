<?php

namespace App\Actions\Groups;

use App\Jobs\ProcessAssetMedia;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\ContextScope;
use App\Support\SpaceContentAnnotationAnchors;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AddSpaceContentAnnotation
{
    public function __construct(private readonly SpaceContentAnnotationAnchors $anchorNormalizer) {}

    /** @param list<array<string, mixed>> $anchors */
    public function execute(
        SpaceContent $content,
        SpaceContentRevision $revision,
        User $user,
        string $body,
        ?SpaceContentAnnotation $parent = null,
        string $kind = SpaceContentAnnotation::KIND_COMMENT,
        string $visibility = SpaceContentAnnotation::VISIBILITY_SPACE,
        array $anchors = [],
        ?UploadedFile $upload = null,
        string $rightsStatus = 'unknown',
        ?string $caption = null,
    ): SpaceContentAnnotation {
        $body = trim($body);
        abort_if(mb_strlen($body) > 5000, 422, 'Annotation text may not exceed 5000 characters.');
        abort_if($body === '' && ! $upload instanceof UploadedFile, 422, 'Add text, media, or a recording to this annotation.');

        if ($parent instanceof SpaceContentAnnotation) {
            if ($kind === SpaceContentAnnotation::KIND_COMMENT) {
                $kind = SpaceContentAnnotation::KIND_REPLY;
            }
            $visibility = $parent->visibility;
        }

        abort_unless(in_array($kind, SpaceContentAnnotation::KINDS, true), 422, 'Choose a supported annotation role.');
        abort_unless(in_array($visibility, SpaceContentAnnotation::VISIBILITIES, true), 422, 'Choose a supported annotation visibility.');

        $uploadContext = $upload instanceof UploadedFile
            ? $this->prepareUpload($content, $user, $upload, $rightsStatus, $caption)
            : null;
        $storageKey = is_array($uploadContext) ? $uploadContext['storage_key'] : null;

        try {
            return DB::transaction(function () use (
                $content,
                $revision,
                $user,
                $body,
                $parent,
                $kind,
                $visibility,
                $anchors,
                $uploadContext,
            ): SpaceContentAnnotation {
                $current = SpaceContent::query()->with(['context', 'space'])->lockForUpdate()->findOrFail($content->id);
                Gate::forUser($user)->authorize('interact', $current);

                abort_unless(
                    $current->active_revision_id !== null
                        && (int) $current->active_revision_id === (int) $revision->id,
                    409,
                    'This published edition has changed. Refresh before interacting.',
                );

                $lockedRevision = SpaceContentRevision::query()
                    ->whereKey($revision->id)
                    ->where('space_content_id', $current->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $parentAnnotation = null;
                if ($parent instanceof SpaceContentAnnotation) {
                    $parentAnnotation = SpaceContentAnnotation::query()
                        ->with('anchors')
                        ->lockForUpdate()
                        ->findOrFail($parent->id);
                    abort_unless(
                        (int) $parentAnnotation->space_content_id === (int) $current->id
                            && (int) $parentAnnotation->space_content_revision_id === (int) $lockedRevision->id
                            && $parentAnnotation->parent_annotation_id === null
                            && $parentAnnotation->status === SpaceContentAnnotation::STATUS_ACTIVE,
                        422,
                        'Replies must target an active top-level annotation on this edition.',
                    );
                    if ($kind === SpaceContentAnnotation::KIND_ANSWER) {
                        abort_unless($parentAnnotation->kind === SpaceContentAnnotation::KIND_QUESTION, 422, 'Answers must target questions.');
                    }
                }

                $normalizedAnchors = $parentAnnotation instanceof SpaceContentAnnotation
                    ? $parentAnnotation->anchors->map(static fn (SpaceContentAnnotationAnchor $anchor): array => [
                        'target_type' => $anchor->target_type,
                        'target_uuid' => $anchor->target_uuid,
                        'field_key' => $anchor->field_key,
                        'selector' => $anchor->selector,
                    ])->all()
                    : $this->anchorNormalizer->normalize($lockedRevision, $anchors);

                $actor = $this->actor($user);
                $annotation = SpaceContentAnnotation::query()->create([
                    'space_content_id' => $current->id,
                    'space_content_revision_id' => $lockedRevision->id,
                    'parent_annotation_id' => $parentAnnotation?->id,
                    'author_actor_id' => $actor->id,
                    'kind' => $kind,
                    'visibility' => $visibility,
                    'status' => SpaceContentAnnotation::STATUS_ACTIVE,
                    'body' => $body === '' ? null : $body,
                ]);

                foreach ($normalizedAnchors as $position => $anchor) {
                    $annotation->anchors()->create([
                        'target_type' => $anchor['target_type'],
                        'target_uuid' => $anchor['target_uuid'],
                        'field_key' => $anchor['field_key'],
                        'selector' => $anchor['selector'],
                        'position' => $position,
                    ]);
                }

                if (is_array($uploadContext)) {
                    $developmentReady = app()->environment(['local', 'testing']);
                    $asset = Asset::query()->create([
                        'uuid' => $uploadContext['uuid'],
                        'context_id' => $current->context_id,
                        'group_space_id' => $current->group_space_id,
                        'original_filename' => $uploadContext['original_filename'],
                        'mime_type' => $uploadContext['mime'],
                        'extension' => $uploadContext['extension'],
                        'byte_size' => $uploadContext['size'],
                        'disk' => 'local',
                        'storage_key' => $uploadContext['storage_key'],
                        'sha256' => $uploadContext['sha256'],
                        'uploaded_by_actor_id' => $actor->id,
                        'scan_status' => $developmentReady ? 'unavailable' : 'quarantined',
                        'scan_error' => null,
                        'processing_status' => $developmentReady ? 'ready' : 'pending',
                        'processing_error' => null,
                        'processing_completed_at' => $developmentReady ? now() : null,
                        'readiness_verified_at' => $developmentReady ? now() : null,
                        'rights_status' => $uploadContext['rights_status'],
                        'metadata' => null,
                    ]);

                    DB::table('space_content_annotation_assets')->insert([
                        'uuid' => (string) Str::uuid(),
                        'annotation_id' => $annotation->id,
                        'asset_id' => $asset->id,
                        'role' => 'attachment',
                        'position' => 0,
                        'caption' => $uploadContext['caption'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if (! $developmentReady) {
                        ProcessAssetMedia::dispatch($asset->id)->afterCommit();
                    }
                }

                return $annotation->load(['anchors', 'assets', 'author.user']);
            }, 3);
        } catch (Throwable $exception) {
            if (is_string($storageKey)) {
                Storage::disk('local')->delete($storageKey);
            }

            throw $exception;
        }
    }

    /** @return array{uuid: string, original_filename: string, mime: string, extension: ?string, size: int, storage_key: string, sha256: string, rights_status: string, caption: ?string} */
    private function prepareUpload(
        SpaceContent $content,
        User $user,
        UploadedFile $upload,
        string $rightsStatus,
        ?string $caption,
    ): array {
        abort_unless(in_array($rightsStatus, Asset::RIGHTS_STATUSES, true), 422, 'Choose a valid media rights status.');
        $caption = $caption !== null ? trim($caption) : null;
        $caption = $caption === '' ? null : $caption;
        abort_if($caption !== null && mb_strlen($caption) > 1000, 422, 'Media caption may not exceed 1000 characters.');

        $preflight = SpaceContent::query()->with(['context', 'space'])->findOrFail($content->id);
        Gate::forUser($user)->authorize('interact', $preflight);

        $size = $upload->getSize();
        abort_unless(is_int($size) && $size > 0 && $size <= 12 * 1024 * 1024, 422, 'Media must be between 1 byte and 12 MB.');
        $mime = (string) $upload->getMimeType();
        abort_unless(Asset::supportsMime($mime), 422, 'This media type is not supported.');

        $realPath = $upload->getRealPath();
        abort_unless(is_string($realPath) && is_file($realPath), 422, 'The uploaded media could not be read.');
        $sha256 = hash_file('sha256', $realPath);
        abort_unless(is_string($sha256), 422, 'The uploaded media could not be hashed.');

        $uuid = (string) Str::uuid();
        $extension = $upload->guessExtension();
        $filename = $uuid.($extension ? '.'.$extension : '.bin');
        $storageKey = $upload->storeAs(ContextScope::storageSegment($preflight->context).'/assets', $filename, 'local');
        abort_unless(is_string($storageKey), 500, 'The uploaded media could not be stored.');

        return [
            'uuid' => $uuid,
            'original_filename' => mb_substr(basename($upload->getClientOriginalName()), 0, 255),
            'mime' => $mime,
            'extension' => $extension ? mb_substr($extension, 0, 32) : null,
            'size' => $size,
            'storage_key' => $storageKey,
            'sha256' => $sha256,
            'rights_status' => $rightsStatus,
            'caption' => $caption,
        ];
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
