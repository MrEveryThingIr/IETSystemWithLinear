<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Support\AssetMediaPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessAssetMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $assetId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('asset-media-'.$this->assetId))->expireAfter(240)];
    }

    public function handle(AssetMediaPipeline $pipeline): void
    {
        $asset = Asset::query()->find($this->assetId);
        if (! $asset instanceof Asset) {
            return;
        }

        try {
            $pipeline->process($asset);
        } catch (Throwable $exception) {
            // The pipeline has already recorded the failure on the Asset. On a
            // synchronous queue (shared hosting) rethrowing would turn the
            // user's upload request into an HTTP 500, and there is no worker
            // to retry anyway, so report it and let the Asset show "failed".
            if ($this->runsSynchronously()) {
                report($exception);

                return;
            }

            throw $exception;
        }
    }

    private function runsSynchronously(): bool
    {
        return $this->job === null || $this->job->getConnectionName() === 'sync';
    }
}
