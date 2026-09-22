<?php

namespace App\Actions\Groups;

use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateAssetRightsStatus
{
    public function execute(SpaceContent $content, Asset $asset, User $user, string $rightsStatus): Asset
    {
        abort_unless(in_array($rightsStatus, Asset::RIGHTS_STATUSES, true), 422, 'Choose a valid media rights status.');

        return DB::transaction(function () use ($content, $asset, $user, $rightsStatus): Asset {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content media cannot be changed.');

            $currentAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            abort_unless((int) $currentAsset->context_id === (int) $current->context_id, 404);

            $draft = $current->draftRevisionRecord();
            abort_unless($draft instanceof SpaceContentRevision, 422, 'Create a private draft before changing media rights.');
            $attachedToDraft = DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $draft->id)
                ->where('asset_id', $currentAsset->id)
                ->exists();
            abort_unless($attachedToDraft, 404);

            $active = $current->activeRevisionRecord();
            if ($active instanceof SpaceContentRevision && ! in_array($rightsStatus, Asset::PUBLISHABLE_RIGHTS_STATUSES, true)) {
                $visibleInPublishedEdition = DB::table('space_content_revision_assets')
                    ->where('space_content_revision_id', $active->id)
                    ->where('asset_id', $currentAsset->id)
                    ->exists();
                abort_if(
                    $visibleInPublishedEdition,
                    422,
                    'Media already visible in the published edition cannot be changed to private-only rights.',
                );
            }

            $currentAsset->update(['rights_status' => $rightsStatus]);

            return $currentAsset->refresh();
        }, 3);
    }
}
