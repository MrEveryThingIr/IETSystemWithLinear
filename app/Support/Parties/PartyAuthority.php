<?php

namespace App\Support\Parties;

use App\Models\Actor;
use App\Models\Business;
use App\Models\Group;
use App\Models\User;
use App\Support\BusinessAccess;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * One place that decides whether a signed-in person may act for a party:
 * themself; a business they own or manage; a group where they hold the
 * permission to manage its categories and descriptions.
 */
final class PartyAuthority
{
    public function canActFor(User $user, Party $party): bool
    {
        return (bool) RequestMemo::remember('party-act:'.$user->getKey().':'.$party->key(), function () use ($user, $party): bool {
            $current = RequestMemo::user($user);
            if (! $current instanceof User || $current->status !== 'active' || ! $current->actor instanceof Actor) {
                return false;
            }

            $owner = $party->model();

            return match (true) {
                $owner instanceof Actor => (int) $owner->id === (int) $current->actor->id,
                $owner instanceof Business => BusinessAccess::canManage($current, $owner),
                $owner instanceof Group => Gate::forUser($current)->allows('manageConcepts', $owner),
                default => false,
            };
        });
    }

    /**
     * Every party this person may act for: themself, then businesses and
     * groups they manage. Used for "save as" choices.
     *
     * @return Collection<int, array{party: Party, label: string}>
     */
    public function partiesFor(User $user): Collection
    {
        $current = RequestMemo::user($user);
        if (! $current instanceof User || ! $current->actor instanceof Actor) {
            return collect();
        }

        $parties = collect([[
            'party' => Party::of($current->actor),
            'label' => (string) __('categories.owner.me'),
        ]]);

        Business::query()
            ->whereHas('memberships', fn ($query) => $query->where('actor_id', $current->actor->id)->where('status', 'active')->whereIn('role', ['owner', 'manager']))
            ->orderBy('name')
            ->get()
            ->filter(fn (Business $business): bool => $this->canActFor($current, Party::of($business)))
            ->each(fn (Business $business) => $parties->push(['party' => Party::of($business), 'label' => $business->name]));

        Group::query()
            ->whereHas('memberships', fn ($query) => $query->where('actor_id', $current->actor->id)->where('status', 'active'))
            ->orderBy('name')
            ->get()
            ->filter(fn (Group $group): bool => $this->canActFor($current, Party::of($group)))
            ->each(fn (Group $group) => $parties->push(['party' => Party::of($group), 'label' => $group->name]));

        return $parties->values();
    }
}
