<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AssetMediaPipeline
{
    public function __construct(private readonly MediaScannerPolicy $policy) {}

    public function process(Asset $asset): Asset
    {
        $asset = Asset::query()->findOrFail($asset->id);

        if ($asset->scan_status === 'rejected' || $asset->processing_status === 'blocked') {
            return $asset;
        }

        if ($asset->scan_status === 'clean'
            && $asset->processing_status === 'ready'
            && $asset->readiness_verified_at !== null) {
            return $asset;
        }

        if (! $this->policy->canScan()) {
            return $this->completeWithoutScanner($asset);
        }

        return $this->scan($asset);
    }

    /**
     * Scanner-less path: never starts a process, never marks the file clean.
     * The stored file's SHA-256 is still verified against the Asset identity.
     * All outcomes are recorded on the Asset; nothing is thrown, so a
     * synchronous queue cannot turn an upload into a server error.
     */
    private function completeWithoutScanner(Asset $asset): Asset
    {
        if ($asset->scan_status === 'unavailable'
            && $asset->processing_status === 'ready'
            && $asset->readiness_verified_at !== null
            && $this->policy->allowsUnscannedAssets()) {
            return $asset;
        }

        $reason = (string) $this->policy->unavailableReason();

        if (! $this->storedFileMatchesIdentity($asset)) {
            $asset->update([
                'scan_status' => 'unavailable',
                'scan_error' => $reason,
                'scan_attempted_at' => null,
                'scan_completed_at' => null,
                'processing_status' => 'failed',
                'processing_error' => 'Stored media is missing or no longer matches its immutable Asset identity.',
                'processing_completed_at' => null,
                'readiness_verified_at' => null,
            ]);

            return $asset->refresh();
        }

        if (! $this->policy->allowsUnscannedAssets()) {
            $asset->update([
                'scan_status' => 'unavailable',
                'scan_error' => $reason,
                'scan_attempted_at' => null,
                'scan_completed_at' => null,
                'processing_status' => 'failed',
                'processing_error' => 'A malware scan is required before this media can be used, and no scanner is available.',
                'processing_completed_at' => null,
                'readiness_verified_at' => null,
            ]);

            return $asset->refresh();
        }

        $asset->update([
            'scan_status' => 'unavailable',
            'scan_error' => $reason,
            'scan_attempted_at' => null,
            'scan_completed_at' => null,
            'processing_status' => 'ready',
            'processing_error' => null,
            'processing_completed_at' => now(),
            'readiness_verified_at' => now(),
        ]);

        return $asset->refresh();
    }

    private function scan(Asset $asset): Asset
    {
        $asset->update([
            'scan_status' => 'scanning',
            'scan_error' => null,
            'scan_attempted_at' => now(),
            'processing_status' => 'pending',
            'processing_error' => null,
            'readiness_verified_at' => null,
        ]);

        $temporaryPath = null;

        try {
            $temporaryPath = $this->temporaryCopy($asset);

            $result = Process::timeout($this->policy->timeout())
                ->run([$this->policy->binary(), '--no-summary', $temporaryPath]);

            if ($result->exitCode() === 1) {
                $asset->update([
                    'scan_status' => 'rejected',
                    'scan_error' => 'Malware scanner rejected this file.',
                    'scan_completed_at' => now(),
                    'processing_status' => 'blocked',
                    'processing_error' => 'Processing is blocked because the file failed malware scanning.',
                    'readiness_verified_at' => null,
                ]);

                return $asset->refresh();
            }

            if (! $result->successful()) {
                throw new RuntimeException(trim($result->errorOutput()) ?: 'Media scanner failed.');
            }

            $actualHash = hash_file('sha256', $temporaryPath);
            if (! is_string($actualHash) || ! hash_equals($asset->sha256, $actualHash)) {
                throw new RuntimeException('Stored media hash no longer matches its immutable Asset identity.');
            }

            $asset->update([
                'scan_status' => 'clean',
                'scan_error' => null,
                'scan_completed_at' => now(),
                'processing_status' => 'processing',
            ]);

            return $this->finishProcessing($asset->refresh());
        } catch (Throwable $exception) {
            $asset->update([
                'scan_status' => 'failed',
                'scan_error' => mb_substr($exception->getMessage(), 0, 2000),
                'scan_completed_at' => now(),
                'processing_status' => 'failed',
                'processing_error' => 'Media processing could not complete.',
                'readiness_verified_at' => null,
            ]);

            throw $exception;
        } finally {
            if (is_string($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function finishProcessing(Asset $asset): Asset
    {
        $asset->update([
            'processing_status' => 'ready',
            'processing_error' => null,
            'processing_completed_at' => now(),
            'readiness_verified_at' => now(),
        ]);

        return $asset->refresh();
    }

    private function storedFileMatchesIdentity(Asset $asset): bool
    {
        $stream = Storage::disk($asset->disk)->readStream($asset->storage_key);
        if (! is_resource($stream)) {
            return false;
        }

        $context = hash_init('sha256');

        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_equals($asset->sha256, hash_final($context));
    }

    private function temporaryCopy(Asset $asset): string
    {
        $stream = Storage::disk($asset->disk)->readStream($asset->storage_key);
        if (! is_resource($stream)) {
            throw new RuntimeException('Stored media could not be opened for scanning.');
        }

        $path = tempnam(sys_get_temp_dir(), 'iet-media-');
        if (! is_string($path)) {
            fclose($stream);
            throw new RuntimeException('A temporary media scan file could not be created.');
        }

        $target = fopen($path, 'wb');
        if (! is_resource($target)) {
            fclose($stream);
            @unlink($path);
            throw new RuntimeException('A temporary media scan file could not be opened.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($stream);
            fclose($target);
        }

        return $path;
    }
}
