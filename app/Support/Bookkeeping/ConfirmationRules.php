<?php

namespace App\Support\Bookkeeping;

use App\Enums\Bookkeeping\ConfirmationDecision;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use Illuminate\Support\Collection;

/**
 * Who must agree before an entry counts.
 *
 * Every person the entry is between must approve it, except the one who
 * recorded it. People without an account (name-only) cannot confirm, so
 * entries that involve only them and the recorder count immediately and are
 * shown as "recorded by one side". Personal-book entries never need anyone.
 */
final class ConfirmationRules
{
    /** @return Collection<int, LedgerParty> */
    public function required(Transaction $transaction): Collection
    {
        if ($transaction->type->isPersonal()) {
            return collect();
        }

        $transaction->loadMissing('fromParty.actor.user', 'toParty.actor.user');
        $recorder = (int) $transaction->recorded_by_actor_id;

        return collect([$transaction->fromParty, $transaction->toParty])
            ->filter(fn (?LedgerParty $party): bool => $party instanceof LedgerParty
                && (int) $party->actor_id !== $recorder
                && $party->canConfirm())
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, LedgerParty> */
    public function awaiting(Transaction $transaction): Collection
    {
        $decided = $transaction->confirmations()->pluck('party_id')->map(fn ($id): int => (int) $id)->all();

        return $this->required($transaction)
            ->reject(fn (LedgerParty $party): bool => in_array((int) $party->id, $decided, true))
            ->values();
    }

    public function allApproved(Transaction $transaction): bool
    {
        $approved = $transaction->confirmations()
            ->where('decision', ConfirmationDecision::Approved->value)
            ->pluck('party_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->required($transaction)
            ->every(fn (LedgerParty $party): bool => in_array((int) $party->id, $approved, true));
    }

    /** Recorded by one side only: nobody with an account could confirm it. */
    public function oneSided(Transaction $transaction): bool
    {
        return ! $transaction->type->isPersonal() && $this->required($transaction)->isEmpty();
    }
}
