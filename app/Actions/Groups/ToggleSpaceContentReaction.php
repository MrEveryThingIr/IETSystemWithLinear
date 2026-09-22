<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentReaction;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\ContentInteractionSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ToggleSpaceContentReaction
{
    public function __construct(private readonly ContentInteractionSettings $interactions) {}

    public function execute(
        SpaceContent $content,
        SpaceContentRevision $revision,
        User $user,
        string $type,
    ): bool {
        abort_unless(in_array($type, SpaceContentReaction::TYPES, true), 422, 'Choose a supported reaction.');

        return DB::transaction(function () use ($content, $revision, $user, $type): bool {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('interact', $current);
            abort_unless($this->interactions->reactionsEnabled($current), 422, 'Reactions are disabled for this Content.');

            abort_unless(
                $current->active_revision_id !== null
                    && (int) $current->active_revision_id === (int) $revision->id,
                409,
                'This published edition has changed. Refresh before interacting.',
            );

            $lockedRevision = SpaceContentRevision::query()
                ->whereKey($revision->id)
                ->where('space_content_id', $current->id)
                ->lockForUpdate()
                ->firstOrFail();

            $actor = $this->actor($user);
            $existing = SpaceContentReaction::query()
                ->where('space_content_revision_id', $lockedRevision->id)
                ->where('actor_id', $actor->id)
                ->where('type', $type)
                ->first();

            if ($existing instanceof SpaceContentReaction) {
                $existing->delete();

                return false;
            }

            SpaceContentReaction::query()->create([
                'space_content_id' => $current->id,
                'space_content_revision_id' => $lockedRevision->id,
                'actor_id' => $actor->id,
                'type' => $type,
            ]);

            return true;
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
