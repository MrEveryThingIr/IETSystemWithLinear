<?php

namespace App\Policies\Bookkeeping;

use App\Enums\Bookkeeping\DisputeStatus;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Dispute;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\ConfirmationRules;

class TransactionPolicy
{
    public function __construct(
        private readonly LedgerPolicy $ledgers,
        private readonly ConfirmationRules $rules,
    ) {}

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->ledgers->view($user, $transaction->ledger);
    }

    /** The person must be one of those still asked to confirm it. */
    public function confirm(User $user, Transaction $transaction): bool
    {
        if ($transaction->status !== TransactionStatus::Pending || $user->actor === null) {
            return false;
        }

        return $this->rules->awaiting($transaction)
            ->contains(fn ($party): bool => (int) $party->actor_id === (int) $user->actor->id);
    }

    /** Whoever recorded an entry may take it back while it is still waiting. */
    public function withdraw(User $user, Transaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::Pending
            && $user->actor !== null
            && (int) $transaction->recorded_by_actor_id === (int) $user->actor->id;
    }

    /** People an entry is between (or the book's managers) can object to it. */
    public function dispute(User $user, Transaction $transaction): bool
    {
        return $transaction->status === TransactionStatus::Confirmed
            && ! $transaction->type->isPersonal()
            && $transaction->ledger->status === LedgerStatus::Active
            && ($this->involved($user, $transaction) || $this->ledgers->manage($user, $transaction->ledger));
    }

    public function resolve(User $user, Dispute $dispute): bool
    {
        if ($dispute->status !== DisputeStatus::Open || $user->actor === null) {
            return false;
        }

        $transaction = $dispute->transaction;

        return $this->involved($user, $transaction)
            || (int) $dispute->raised_by_actor_id === (int) $user->actor->id
            || $this->ledgers->manage($user, $transaction->ledger);
    }

    /** Debt amounts are corrected by the people it is between or the managers. */
    public function adjust(User $user, Transaction $transaction): bool
    {
        return $transaction->type === TransactionType::Obligation
            && $transaction->status === TransactionStatus::Confirmed
            && $this->ledgers->record($user, $transaction->ledger)
            && ($this->involved($user, $transaction) || $this->ledgers->manage($user, $transaction->ledger));
    }

    public function attachEvidence(User $user, Transaction $transaction): bool
    {
        return ! in_array($transaction->status, [TransactionStatus::Rejected, TransactionStatus::Void], true)
            && $this->ledgers->record($user, $transaction->ledger)
            && ($this->involved($user, $transaction)
                || (int) $transaction->recorded_by_actor_id === (int) $user->actor?->id
                || $this->ledgers->manage($user, $transaction->ledger));
    }

    private function involved(User $user, Transaction $transaction): bool
    {
        if ($user->actor === null) {
            return false;
        }

        $transaction->loadMissing('fromParty', 'toParty');

        return in_array((int) $user->actor->id, [
            (int) $transaction->fromParty?->actor_id,
            (int) $transaction->toParty?->actor_id,
        ], true) || ($transaction->type->isPersonal() && $this->ledgers->view($user, $transaction->ledger));
    }
}
