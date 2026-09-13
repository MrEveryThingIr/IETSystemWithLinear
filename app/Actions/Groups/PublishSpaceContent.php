<?php

namespace App\Actions\Groups;

use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishSpaceContent
{
    public function execute(SpaceContent $content, User $user): SpaceContent
    {
        return DB::transaction(function () use ($content, $user): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('publish', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content cannot be published.');

            $draft = $current->draftRevisionRecord();
            abort_unless($draft instanceof SpaceContentRevision, 422, 'Content has no draft revision to publish.');
            $draft = SpaceContentRevision::query()->lockForUpdate()->findOrFail($draft->id);

            $current->applyLifecycle([
                'status' => 'published',
                'current_revision' => $draft->revision,
                'active_revision_id' => $draft->id,
                'draft_revision_id' => null,
                'published_at' => now(),
                'archived_at' => null,
            ]);

            return $current->refresh();
        }, 3);
    }
}
