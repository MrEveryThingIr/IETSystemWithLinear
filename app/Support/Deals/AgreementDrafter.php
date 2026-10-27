<?php

namespace App\Support\Deals;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\RelationshipParticipantStatus;
use App\Support\Parties\PartyAuthority;

/**
 * Who writes the Agreement. By default the owner of the originating Need/Offer (or anyone authorized to act for the
 * Business/Group that published it). The owner side may hand drafting to another active participant, and take it back;
 * the delegate is stored on the Deal, never inferred. Everyone else asks for an agreement or asks for changes.
 */
final class AgreementDrafter
{
    public static function ownerActorId(Relationship $deal): int
    {
        $intent = $deal->originatingIntent;

        return $intent instanceof ActorProfileIntent ? (int) $intent->profile->actor_id : (int) $deal->created_by_actor_id;
    }

    public static function delegateActorId(Relationship $deal): ?int
    {
        $id = $deal->metadata['drafter_actor_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /** Is this person on the owner side: the owner, or authorized for the principal that published the post? */
    public static function isOwnerSide(Relationship $deal, User $user): bool
    {
        if (! $user->actor instanceof Actor) {
            return false;
        }
        if ((int) $user->actor->id === self::ownerActorId($deal)) {
            return true;
        }

        $intent = $deal->originatingIntent;

        return $intent instanceof ActorProfileIntent && (new PartyAuthority)->canActFor($user, $intent->publisher());
    }

    public static function canDraft(Relationship $deal, User $user): bool
    {
        $delegate = self::delegateActorId($deal);

        return self::isOwnerSide($deal, $user) || ($delegate !== null && (int) $user->actor?->id === $delegate);
    }

    /** The owner side hands drafting to an active participant, or takes it back with null. */
    public static function delegate(Relationship $deal, User $by, ?int $actorId): void
    {
        abort_unless(self::isOwnerSide($deal, $by), 403);

        if ($actorId !== null) {
            abort_unless(
                RelationshipParticipant::query()->where('relationship_id', $deal->id)->where('actor_id', $actorId)->where('status', RelationshipParticipantStatus::Active->value)->exists(),
                422,
                'The drafter must be an active participant of this Deal.',
            );
        }

        $meta = $deal->metadata ?? [];
        if ($actorId === null || $actorId === self::ownerActorId($deal)) {
            unset($meta['drafter_actor_id']);
        } else {
            $meta['drafter_actor_id'] = $actorId;
        }
        $deal->update(['metadata' => $meta]);
    }
}
