<?php

namespace App\Support\Content;

use App\Models\Actor;
use App\Models\ActorBlock;
use App\Models\ActorContact;
use App\Models\SpaceContent;
use App\Models\User;

/**
 * "My contacts" as a Content audience: the author's accepted, mutual contacts (Messaging's ActorContact), nobody who is
 * pending, declined, removed or blocked. Always read fresh, never memoised, so removing a contact closes access at once.
 */
final class ContactAudience
{
    public static function includes(int $authorActorId, int $readerActorId): bool
    {
        if ($authorActorId === $readerActorId) {
            return false;
        }

        return ActorContact::query()->between($authorActorId, $readerActorId)->where('status', ActorContact::ACCEPTED)->exists()
            && ! ActorBlock::between($authorActorId, $readerActorId);
    }

    /** Whether this user may read the content as the author's contact (published edition of contacts-only content). */
    public static function mayRead(User $user, SpaceContent $content): bool
    {
        if ($content->public_access !== SpaceContent::ACCESS_CONTACTS || $content->status !== 'published' || $content->active_revision_id === null) {
            return false;
        }
        $actor = User::query()->with('actor')->find($user->id)?->actor;

        return $actor instanceof Actor && $actor->status === 'active' && self::includes((int) $content->author_actor_id, (int) $actor->id);
    }

    /** @return list<int> actor ids of the accepted contacts of an actor, blocks excluded */
    public static function contactIds(int $actorId): array
    {
        return ActorContact::query()->involving($actorId)->where('status', ActorContact::ACCEPTED)->get()
            ->map(fn (ActorContact $c): int => $c->otherThan($actorId))
            ->reject(fn (int $other): bool => ActorBlock::between($actorId, $other))
            ->values()->all();
    }
}
