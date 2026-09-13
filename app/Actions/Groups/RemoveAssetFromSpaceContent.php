<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentRevisionComposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveAssetFromSpaceContent
{
    public function __construct(private readonly SpaceContentRevisionComposition $composition) {}

    public function execute(SpaceContent $content, Asset $asset, User $user): SpaceContent
    {
        return DB::transaction(function () use ($content, $asset, $user): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content cannot change media.');
            abort_unless((int) $asset->group_space_id === (int) $current->group_space_id, 404);

            $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($source instanceof SpaceContentRevision, 422, 'Content has no revision to change.');
            $source = SpaceContentRevision::query()->lockForUpdate()->findOrFail($source->id);

            $placementExists = DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $source->id)
                ->where('asset_id', $asset->id)
                ->exists();
            abort_unless($placementExists, 404);

            $actor = $this->actor($user);
            $nextRevision = ((int) $current->revisions()->max('revision')) + 1;
            $revision = $current->revisions()->create([
                'definition_version_id' => $source->definition_version_id,
                'revision' => $nextRevision,
                'title' => $source->title,
                'payload' => $source->payload,
                'created_by_actor_id' => $actor->id,
            ]);

            $this->composition->copyAssets($source, $revision, [$asset->id]);
            $this->composition->copyRelationships($source, $revision);

            $current->applyLifecycle([
                'current_revision' => $nextRevision,
                'draft_revision_id' => $revision->id,
            ]);

            return $current->refresh();
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
