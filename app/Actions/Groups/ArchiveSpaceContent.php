<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentLifecycleEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveSpaceContent
{
    public function execute(SpaceContent $content, User $user, ?string $reason = null): SpaceContent
    {
        $reason = trim((string) ($reason ?? 'Archived by an authorized author or Space manager.'));
        abort_if($reason === '' || mb_strlen($reason) > 1000, 422, 'Archive reason is required and may not exceed 1000 characters.');

        return DB::transaction(function () use ($content, $user, $reason): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);

            if ($current->status === 'archived') {
                Gate::forUser($user)->authorize('view', $current);

                return $current;
            }

            Gate::forUser($user)->authorize('archive', $current);
            $actor = $this->actor($user);
            $fromStatus = $current->status;

            $current->applyLifecycle([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

            SpaceContentLifecycleEvent::query()->create([
                'space_content_id' => $current->id,
                'actor_id' => $actor->id,
                'event_type' => 'archived',
                'from_status' => $fromStatus,
                'to_status' => 'archived',
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
