<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\DisputeOutcome;
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

/**
 * Ends a dispute: either the entry stands (the objector or a manager drops
 * the objection) or it was wrong and is voided (someone other than the
 * objector agrees: the other side or a manager).
 */
class ResolveDispute
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(Dispute $dispute, User $user, DisputeOutcome $outcome, ?string $note = null): Dispute
    {
        $actor = $this->recorder->actor($user);
        $note = $note !== null ? Str::limit(Str::squish($note), 1000, '') : null;

        return DB::transaction(function () use ($dispute, $user, $actor, $outcome, $note): Dispute {
            $locked = Dispute::query()->lockForUpdate()->findOrFail($dispute->id);
            Gate::forUser($user)->authorize('resolve', $locked);

            $transaction = Transaction::query()->with('ledger.activeParties')->lockForUpdate()->findOrFail($locked->transaction_id);
            $raisedByMe = (int) $locked->raised_by_actor_id === (int) $actor->id;
            $manager = Gate::forUser($user)->allows('manage', $transaction->ledger);

            // Keeping the entry after someone objected: only the objector or a manager.
            abort_if($outcome === DisputeOutcome::Upheld && ! $raisedByMe && ! $manager, 403);
            // Voiding needs someone other than the objector to agree, so nobody
            // can erase the other side's entry on their own.
            abort_if($outcome === DisputeOutcome::Voided && $raisedByMe, 403);

            $before = BookkeepingRecorder::snapshot($transaction);
            $outcome === DisputeOutcome::Upheld
                ? $transaction->transitionTo(TransactionStatus::Confirmed)
                : $transaction->transitionTo(TransactionStatus::Void, ['voided_at' => now(), 'void_reason' => 'dispute']);

            $locked->forceFill([
                'status' => DisputeStatus::Resolved,
                'outcome' => $outcome,
                'resolution_note' => $note !== '' ? $note : null,
                'resolved_by_actor_id' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            $this->recorder->audit($transaction->ledger, $actor, 'dispute.'.$outcome->value, $transaction, $before, BookkeepingRecorder::snapshot($transaction));
            $this->recorder->notify(
                $transaction->ledger->activeParties->filter(fn ($party): bool => in_array((int) $party->id, [(int) $transaction->from_party_id, (int) $transaction->to_party_id], true)),
                $transaction,
                'dispute_'.$outcome->value,
                ['who' => $actor->user->username ?? '', 'amount' => Toman::format((int) $transaction->amount), 'book' => $transaction->ledger->name],
                $actor,
            );

            return $locked->refresh();
        }, 3);
    }
}
