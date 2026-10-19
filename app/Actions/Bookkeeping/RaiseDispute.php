<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\DisputeStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Models\Bookkeeping\Dispute;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\Bookkeeping\Toman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Objects to a confirmed entry. While disputed it no longer counts in the
 * confirmed balance and is shown separately as "disputed".
 */
class RaiseDispute
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(Transaction $transaction, User $user, string $reason): Dispute
    {
        $reason = Str::squish($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['disputeReason' => __('bookkeeping.validation.reason_required')]);
        }

        $actor = $this->recorder->actor($user);

        return DB::transaction(function () use ($transaction, $user, $actor, $reason): Dispute {
            $locked = Transaction::query()->with('ledger.activeParties')->lockForUpdate()->findOrFail($transaction->id);
            Gate::forUser($user)->authorize('dispute', $locked);

            $before = BookkeepingRecorder::snapshot($locked);
            $locked->transitionTo(TransactionStatus::Disputed);

            $dispute = Dispute::query()->create([
                'transaction_id' => $locked->id,
                'raised_by_actor_id' => $actor->id,
                'reason' => $reason,
                'status' => DisputeStatus::Open,
            ]);

            $this->recorder->audit($locked->ledger, $actor, 'entry.disputed', $locked, $before, ['reason' => $reason]);
            $this->recorder->notify(
                $locked->ledger->activeParties->filter(fn ($party): bool => in_array((int) $party->id, [(int) $locked->from_party_id, (int) $locked->to_party_id], true)
                    || $party->role->canManage()),
                $locked,
                'disputed',
                ['who' => $actor->user->username ?? '', 'amount' => Toman::format((int) $locked->amount), 'book' => $locked->ledger->name],
                $actor,
            );

            return $dispute->refresh();
        }, 3);
    }
}
