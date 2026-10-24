<?php

namespace App\Policies;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;

class ActorProfileIntentPolicy
{
    public function __construct(
        private readonly ActorProfilePolicy $profiles,
        private readonly PartyAuthority $authority,
    ) {}

    /** Same rule as the directory and matching (ActorProfileIntent::scopeVisibleTo). */
    public function view(?User $user, ActorProfileIntent $intent): bool
    {
        return (bool) RequestMemo::remember(
            'intent-view:'.($user?->getKey() ?? 'guest').':'.$intent->getKey(),
            fn (): bool => ActorProfileIntent::query()->whereKey($intent->getKey())->visibleTo($user)->exists(),
        );
    }

    /** The person who published it, or someone who may act for the publishing business or group. */
    public function update(User $user, ActorProfileIntent $intent): bool
    {
        $publisher = Party::fromRecord($intent, 'publisher');
        if ($publisher instanceof Party && $publisher->kind !== Party::ACTOR) {
            return $this->authority->canActFor($user, $publisher);
        }

        $intent->loadMissing('profile');

        return $this->profiles->update($user, $intent->profile);
    }
}
