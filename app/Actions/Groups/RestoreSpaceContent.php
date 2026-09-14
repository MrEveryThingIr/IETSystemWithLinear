<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentLifecycleEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreSpaceContent
{
    public function execute(SpaceContent $content, User $user, string $reason): SpaceContent
    {
        $reason = trim($reason);
        abort_if($reason === '' || mb_strlen($reason) > 1000, 422, 'Restore reason is required and may not exceed 1000 characters.');

        return DB::transaction(function () use ($content, $user, $reason): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('restore', $current);
            abort_unless($current->status === 'archived', 422, 'Only archived Content can be restored.');

            $actor = $this->actor($user);
            $targetStatus = $current->active_revision_id !== null ? 'published' : 'draft';

            $current->applyLifecycle([
                'status' => $targetStatus,
                'archived_at' => null,
            ]);

            SpaceContentLifecycleEvent::query()->create([
                'space_content_id' => $current->id,
                'actor_id' => $actor->id,
                'event_type' => 'restored',
                'from_status' => 'archived',
                'to_status' => $targetStatus,
                'reason' => $reason,
                'metadata' => [
                    'active_revision_id' => $current->active_revision_id,
                    'draft_revision_id' => $current->draft_revision_id,
                ],
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
