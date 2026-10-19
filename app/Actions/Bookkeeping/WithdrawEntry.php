<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\TransactionStatus;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** The person who recorded an entry takes it back before anyone confirmed it. */
class WithdrawEntry
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(Transaction $transaction, User $user): Transaction
    {
        $actor = $this->recorder->actor($user);

        return DB::transaction(function () use ($transaction, $user, $actor): Transaction {
            $locked = Transaction::query()->with('ledger')->lockForUpdate()->findOrFail($transaction->id);
            Gate::forUser($user)->authorize('withdraw', $locked);

            $before = BookkeepingRecorder::snapshot($locked);
            $locked->transitionTo(TransactionStatus::Void, ['voided_at' => now(), 'void_reason' => 'withdrawn']);
            $this->recorder->audit($locked->ledger, $actor, 'entry.withdrawn', $locked, $before);

            return $locked->refresh();
        }, 3);
    }
}
