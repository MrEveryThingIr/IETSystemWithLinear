<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Support\AssetMediaPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

    public function handle(AssetMediaPipeline $pipeline): void
    {
        $asset = Asset::query()->find($this->assetId);
        if (! $asset instanceof Asset) {
            return;
        }

        $pipeline->process($asset);
    }
}
