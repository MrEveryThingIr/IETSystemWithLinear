<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentLifecycleEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens published Content to anyone on the web, or closes it again.
 *
 * Opening requires the "share" ability (the author decides); closing is also
 * allowed to the place's Content managers. Each change is recorded as an
 * immutable lifecycle event. Only the published edition is ever shown to
 * guests, so later drafts stay private until they are published.
 */
class SetContentPublicAccess
{
    public function execute(SpaceContent $content, User $user, string $access): SpaceContent
    {
        if (! in_array($access, SpaceContent::PUBLIC_ACCESS_VALUES, true)) {
            throw ValidationException::withMessages(['publicAccess' => __('public_content.errors.unknown_access')]);
        }

        return DB::transaction(function () use ($content, $user, $access): SpaceContent {
            $current = SpaceContent::query()->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize($access === SpaceContent::ACCESS_PUBLIC ? 'share' : 'manageSharing', $current);

            if ($current->public_access === $access) {
                return $current;
            }

            $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
            abort_unless($actor instanceof Actor, 403);

            $from = $current->public_access;
            $current->forceFill([
                'public_access' => $access,
                'public_since' => $access === SpaceContent::ACCESS_PUBLIC ? now() : null,
            ])->save();

            SpaceContentLifecycleEvent::query()->create([
                'space_content_id' => $current->id,
                'actor_id' => $actor->id,
                'event_type' => $access === SpaceContent::ACCESS_PUBLIC ? 'opened_to_public' : 'closed_to_public',
                'from_status' => $from,
                'to_status' => $access,
                'reason' => $access === SpaceContent::ACCESS_PUBLIC
                    ? 'Author made the published edition readable by anyone.'
                    : 'Public access closed.',
                'metadata' => ['active_revision_id' => $current->active_revision_id],
            ]);

            return $current->refresh();
        }, 3);
    }
}
