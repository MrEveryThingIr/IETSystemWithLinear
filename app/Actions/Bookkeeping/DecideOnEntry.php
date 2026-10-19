<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\ConfirmationDecision;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Models\Bookkeeping\Confirmation;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\Bookkeeping\ConfirmationRules;
use App\Support\Bookkeeping\Toman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A party approves or rejects an entry waiting for them. Once everyone asked
 * has approved, the entry counts. One rejection ends it (the recorder can
 * record a corrected one).
 */
class DecideOnEntry
{
    public function __construct(
        private readonly BookkeepingRecorder $recorder,
        private readonly ConfirmationRules $rules,
    ) {}

    public function approve(Transaction $transaction, User $user, ?string $note = null): Transaction
    {
        return $this->decide($transaction, $user, ConfirmationDecision::Approved, $note);
    }

    public function reject(Transaction $transaction, User $user, string $note): Transaction
    {
        $note = Str::squish($note);
        if ($note === '') {
            throw ValidationException::withMessages(['rejectNote' => __('bookkeeping.validation.reason_required')]);
        }

        return $this->decide($transaction, $user, ConfirmationDecision::Rejected, $note);
    }

    private function decide(Transaction $transaction, User $user, ConfirmationDecision $decision, ?string $note): Transaction
    {
        $actor = $this->recorder->actor($user);

        return DB::transaction(function () use ($transaction, $user, $actor, $decision, $note): Transaction {
            $locked = Transaction::query()->with('ledger')->lockForUpdate()->findOrFail($transaction->id);
            Gate::forUser($user)->authorize('confirm', $locked);

            $party = $this->rules->awaiting($locked)
                ->first(fn ($candidate): bool => (int) $candidate->actor_id === (int) $actor->id);
            abort_if($party === null, 403);

            $request = app()->runningInConsole() ? null : request();
            Confirmation::query()->create([
                'transaction_id' => $locked->id,
                'party_id' => $party->id,
                'actor_id' => $actor->id,
                'decision' => $decision,
                'note' => $note !== null && $note !== '' ? Str::limit($note, 1000, '') : null,
                'decided_at' => now(),
                'ip' => $request?->ip(),
                'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]);

            $before = BookkeepingRecorder::snapshot($locked);
            $params = [
                'who' => $actor->user->username ?? '',
                'type' => (string) __('bookkeeping.types.'.$locked->type->value),
                'amount' => Toman::format((int) $locked->amount),
                'book' => $locked->ledger->name,
            ];

            if ($decision === ConfirmationDecision::Rejected) {
                $locked->transitionTo(TransactionStatus::Rejected);
                $this->recorder->audit($locked->ledger, $actor, 'entry.rejected', $locked, $before, ['note' => $note]);
                $this->recorder->notify($this->involvedParties($locked), $locked, 'rejected', $params, $actor);
            } elseif ($this->rules->allApproved($locked)) {
                $locked->transitionTo(TransactionStatus::Confirmed, ['confirmed_at' => now()]);
                $this->recorder->audit($locked->ledger, $actor, 'entry.confirmed', $locked, $before, BookkeepingRecorder::snapshot($locked));
                $this->recorder->notify($this->involvedParties($locked), $locked, 'confirmed', $params, $actor);
            } else {
                $this->recorder->audit($locked->ledger, $actor, 'entry.approved', $locked, $before);
            }

            return $locked->refresh();
        }, 3);
    }

    /** @return list<LedgerParty> */
    private function involvedParties(Transaction $transaction): array
    {
        $transaction->loadMissing('fromParty', 'toParty', 'ledger.activeParties');
        $recorderParty = $transaction->ledger->activeParties->first(fn ($party): bool => (int) $party->actor_id === (int) $transaction->recorded_by_actor_id);

        return array_values(array_filter([$transaction->fromParty, $transaction->toParty, $recorderParty]));
    }
}
