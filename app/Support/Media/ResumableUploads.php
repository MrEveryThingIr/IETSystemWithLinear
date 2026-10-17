<?php

namespace App\Support\Media;

use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Contracts\Media\VideoProcessor;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\MediaUploadSession;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\MediaLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Resumable, chunked uploads for large audio and video.
 *
 * Each chunk is a small request below the host's PHP upload limits, so a
 * 79 MB or 1 GB lesson video never has to fit in one request. Chunks must
 * arrive in order (offset = bytes already received); a repeated chunk is
 * accepted idempotently, a gap is refused with the expected offset, so a
 * dropped connection or page refresh simply continues where it stopped.
 */
final class ResumableUploads
{
    public const DISK = 'local';

    public function __construct(
        private readonly AttachAssetToSpaceContent $attach,
        private readonly VideoProcessor $processor,
    ) {}

    /** Media kinds that use this path. */
    public static function kindForMime(string $mime): ?string
    {
        return match (true) {
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => null,
        };
    }

    /** Largest file accepted on this path, in bytes (not capped by PHP limits). */
    public static function maxBytes(string $kind): int
    {
        return (int) config('media.resumable.max_'.$kind.'_kb', 0) * 1024;
    }

    /** Chunk size that always fits below the host's per-request limits. */
    public static function chunkBytes(): int
    {
        $configured = max(1, (int) config('media.resumable.chunk_kb', 4096)) * 1024;
        $host = app()->runningInConsole() ? null : MediaLimits::hostLimitKilobytes();
        if ($host === null) {
            return $configured;
        }

        // Leave room for request overhead below upload_max_filesize/post_max_size.
        return max(64 * 1024, min($configured, ($host - 64) * 1024));
    }

    public function create(
        SpaceContent $content,
        User $user,
        string $filename,
        int $byteSize,
        string $mime,
        string $rightsStatus,
        ?string $caption = null,
    ): MediaUploadSession {
        $current = SpaceContent::query()->findOrFail($content->id);
        Gate::forUser($user)->authorize('update', $current);
        abort_if($current->status === 'archived', 422, 'Archived Content cannot receive media.');
        abort_unless(in_array($rightsStatus, Asset::RIGHTS_STATUSES, true), 422, 'Choose a valid media rights status.');

        $kind = self::kindForMime($mime);
        abort_unless($kind !== null && Asset::supportsMime($mime), 422, __('studio_ui.upload.unsupported_type'));
        abort_unless($byteSize > 0 && $byteSize <= self::maxBytes($kind), 422, __('studio_ui.upload.too_large_resumable', [
            'limit' => Number::fileSize(self::maxBytes($kind)),
        ]));

        $actor = $this->actor($user);
        $open = MediaUploadSession::query()
            ->where('actor_id', $actor->id)
            ->where('status', MediaUploadSession::STATUS_OPEN)
            ->where('expires_at', '>', now())
            ->count();
        abort_if($open >= (int) config('media.resumable.max_open_sessions', 5), 429, __('studio_ui.upload.too_many_open'));

        $uuid = (string) Str::uuid();
        $tempKey = 'upload-sessions/'.$uuid.'.part';
        Storage::disk(self::DISK)->put($tempKey, '');

        return MediaUploadSession::query()->create([
            'uuid' => $uuid,
            'context_id' => $current->context_id,
            'space_content_id' => $current->id,
            'actor_id' => $actor->id,
            'original_filename' => mb_substr(basename($filename), 0, 255) ?: 'upload',
            'mime_type' => $mime,
            'byte_size' => $byteSize,
            'chunk_size' => self::chunkBytes(),
            'received_bytes' => 0,
            'status' => MediaUploadSession::STATUS_OPEN,
            'temp_key' => $tempKey,
            'rights_status' => $rightsStatus,
            'caption' => $caption !== null && trim($caption) !== '' ? mb_substr(trim($caption), 0, 1000) : null,
            'expires_at' => now()->addHours((int) config('media.resumable.session_hours', 48)),
        ]);
    }

    /**
     * Append one chunk. Returns the number of bytes received so far.
     *
     * @param  resource  $stream
     */
    public function append(MediaUploadSession $session, User $user, int $offset, $stream): int
    {
        $this->authorize($session, $user);

        return DB::transaction(function () use ($session, $offset, $stream): int {
            $locked = MediaUploadSession::query()->lockForUpdate()->findOrFail($session->id);
            abort_unless($locked->isOpen(), 409, __('studio_ui.upload.session_closed'));

            $path = Storage::disk(self::DISK)->path($locked->temp_key);
            clearstatcache(true, $path);
            $onDisk = is_file($path) ? (int) filesize($path) : -1;
            abort_unless($onDisk === (int) $locked->received_bytes, 409, __('studio_ui.upload.session_closed'));

            $chunk = stream_get_contents($stream, $locked->chunk_size + 1);
            abort_unless(is_string($chunk), 422, 'The chunk could not be read.');
            $length = strlen($chunk);
            abort_if($length === 0 || $length > $locked->chunk_size, 413, 'Chunk size is not allowed.');

            if ($offset < $locked->received_bytes) {
                // Retried chunk that already arrived: accept without writing again.
                abort_unless($offset + $length <= $locked->received_bytes, 409, json_encode(['expected_offset' => $locked->received_bytes]) ?: '');

                return (int) $locked->received_bytes;
            }

            abort_unless($offset === (int) $locked->received_bytes, 409, json_encode(['expected_offset' => $locked->received_bytes]) ?: '');
            abort_if($offset + $length > $locked->byte_size, 422, 'More data than the declared file size.');

            $handle = fopen($path, 'ab');
            abort_unless(is_resource($handle), 500, 'The upload could not be stored.');
            try {
                abort_unless(fwrite($handle, $chunk) === $length, 500, 'The upload could not be stored.');
            } finally {
                fclose($handle);
            }

            $locked->update([
                'received_bytes' => $offset + $length,
                'expires_at' => now()->addHours((int) config('media.resumable.session_hours', 48)),
            ]);

            return $offset + $length;
        });
    }

    /**
     * Turn the assembled file into an Asset on the Content's draft and record
     * video derivatives. Returns the new Asset.
     *
     * @param  array{duration_ms?: mixed, width?: mixed, height?: mixed}  $metadata
     */
    public function complete(MediaUploadSession $session, User $user, array $metadata = [], ?UploadedFile $poster = null): Asset
    {
        $this->authorize($session, $user);

        $claimed = DB::transaction(function () use ($session): MediaUploadSession {
            $locked = MediaUploadSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status === MediaUploadSession::STATUS_COMPLETED && $locked->asset_id !== null) {
                return $locked;
            }
            abort_unless($locked->isOpen(), 409, __('studio_ui.upload.session_closed'));
            abort_unless((int) $locked->received_bytes === (int) $locked->byte_size, 409, json_encode(['expected_offset' => $locked->received_bytes]) ?: '');
            $locked->update(['status' => MediaUploadSession::STATUS_COMPLETING]);

            return $locked;
        });

        if ($claimed->status === MediaUploadSession::STATUS_COMPLETED) {
            return Asset::query()->findOrFail($claimed->asset_id);
        }

        $path = Storage::disk(self::DISK)->path($claimed->temp_key);

        try {
            $file = new UploadedFile($path, $claimed->original_filename, null, null, true);
            $detected = (string) $file->getMimeType();
            abort_unless(self::kindForMime($detected) === self::kindForMime($claimed->mime_type), 422, __('studio_ui.upload.type_mismatch'));

            $kind = (string) self::kindForMime($detected);
            $content = $this->attach->execute(
                $claimed->content()->firstOrFail(),
                $user,
                $file,
                $claimed->rights_status,
                $claimed->caption,
                self::maxBytes($kind),
            );

            $draft = $content->draftRevisionRecord();
            abort_unless($draft !== null, 500, 'The media could not be attached.');
            $assetId = DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $draft->id)
                ->orderByDesc('id')
                ->value('asset_id');
            $asset = Asset::query()->findOrFail($assetId);

            // The Asset now exists; a poster or metadata problem must not make
            // the author upload again (or create a duplicate on retry).
            try {
                $this->processor->process($asset, $metadata, $poster);
            } catch (Throwable $processingFailure) {
                report($processingFailure);
            }

            $claimed->update([
                'status' => MediaUploadSession::STATUS_COMPLETED,
                'asset_id' => $asset->id,
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            // A rejected file (wrong type, too large) is final; anything else
            // (a timeout, a storage hiccup) leaves the bytes so completion can be retried.
            $final = $exception instanceof HttpException && $exception->getStatusCode() === 422;
            $claimed->update([
                'status' => $final ? MediaUploadSession::STATUS_FAILED : MediaUploadSession::STATUS_OPEN,
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
            if ($final) {
                Storage::disk(self::DISK)->delete($claimed->temp_key);
            }

            throw $exception;
        }

        Storage::disk(self::DISK)->delete($claimed->temp_key);

        return $asset;
    }

    public function cancel(MediaUploadSession $session, User $user): void
    {
        $this->authorize($session, $user);

        DB::transaction(function () use ($session): void {
            $locked = MediaUploadSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->status !== MediaUploadSession::STATUS_OPEN) {
                return;
            }
            $locked->update(['status' => MediaUploadSession::STATUS_CANCELLED]);
            Storage::disk(self::DISK)->delete($locked->temp_key);
        });
    }

    /** Expire abandoned sessions and remove their partial files. */
    public function prune(): int
    {
        $count = 0;

        MediaUploadSession::query()
            ->whereIn('status', [MediaUploadSession::STATUS_OPEN, MediaUploadSession::STATUS_COMPLETING])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($sessions) use (&$count): void {
                foreach ($sessions as $session) {
                    Storage::disk(self::DISK)->delete($session->temp_key);
                    $session->update(['status' => MediaUploadSession::STATUS_EXPIRED]);
                    $count++;
                }
            });

        return $count;
    }

    public function authorize(MediaUploadSession $session, User $user): void
    {
        $actor = $this->actor($user);
        abort_unless((int) $session->actor_id === (int) $actor->id, 403);
        Gate::forUser($user)->authorize('update', $session->content()->firstOrFail());
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
