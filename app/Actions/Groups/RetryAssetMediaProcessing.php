<?php

namespace App\Actions\Groups;

use App\Jobs\ProcessAssetMedia;
use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RetryAssetMediaProcessing
{
    public function execute(SpaceContent $content, Asset $asset, User $user): Asset
    {
        return DB::transaction(function () use ($content, $asset, $user): Asset {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);

            $currentAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            abort_unless((int) $currentAsset->context_id === (int) $current->context_id, 404);

            $draft = $current->draftRevisionRecord();
            abort_unless($draft instanceof SpaceContentRevision, 422, 'Create a private draft before retrying media processing.');

            $attached = DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $draft->id)
                ->where('asset_id', $currentAsset->id)
                ->exists();
            abort_unless($attached, 404);
            abort_unless(
                in_array($currentAsset->scan_status, ['failed', 'quarantined'], true)
                    || in_array($currentAsset->processing_status, ['failed', 'pending'], true),
                422,
                'This media item is not waiting for a retry.',
            );

            $currentAsset->update([
                'scan_status' => 'quarantined',
                'scan_error' => null,
                'processing_status' => 'pending',
                'processing_error' => null,
                'readiness_verified_at' => null,
            ]);

            ProcessAssetMedia::dispatch($currentAsset->id)->afterCommit();

            return $currentAsset->refresh();
        }, 3);
    }
}
