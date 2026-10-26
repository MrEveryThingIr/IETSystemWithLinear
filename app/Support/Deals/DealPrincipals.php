<?php

namespace App\Support\Deals;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Relationship;
use App\Support\Parties\Party;

/**
 * Which principal an Actor stands for in a Deal: the publisher of the pinned Need/Offer for its owner,
 * the Business/Group the responder chose to speak as, otherwise the person. Labels such as Buyer or Provider play no part.
 */
final class DealPrincipals
{
    public static function forActor(Relationship $relationship, Actor $actor): Party
    {
        foreach ([$relationship->originatingIntent, $relationship->matchedIntent] as $intent) {
            if ($intent instanceof ActorProfileIntent && (int) $intent->profile->actor_id === (int) $actor->id) {
                return $intent->publisher();
            }
        }

        $chosen = (string) ($relationship->metadata['responder_party'] ?? '');
        if ($chosen !== '' && (int) $relationship->created_by_actor_id === (int) $actor->id) {
            [$kind, $id] = array_pad(explode(':', $chosen, 2), 2, '0');

            return Party::make($kind, (int) $id);
        }

        return Party::of($actor);
    }
}
