<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\EntryOrigin;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\Bookkeeping\ConfirmationRules;
use App\Support\Bookkeeping\CreditAudit;
use App\Support\Bookkeeping\Toman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a confirmed debt up or down with a new entry; the original stays
 * as recorded. Needs the other side's confirmation like any entry.
 */
class RecordAdjustment
{
    public function __construct(
        private readonly BookkeepingRecorder $recorder,
        private readonly BalanceCalculator $balances,
        private readonly ConfirmationRules $rules,
    ) {}

    public function execute(Transaction $obligation, User $user, AdjustmentDirection $direction, int|string $amount, string $reason, ?string $idempotencyKey = null): Transaction
    {
        Gate::forUser($user)->authorize('adjust', $obligation);
        $amount = Toman::parse($amount);
        $reason = Str::squish($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['adjustReason' => __('bookkeeping.validation.reason_required')]);
        }

        $actor = $this->recorder->actor($user);

        if ($idempotencyKey !== null) {
            $existing = Transaction::query()->where('recorded_by_actor_id', $actor->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Transaction) {
                abort_unless($existing->ledger_id === $obligation->ledger_id, 409);

                return $existing;
            }
        }

        if ($direction === AdjustmentDirection::Decrease) {
            $progress = $this->balances->obligation($obligation);
            if ($amount > $progress['amount']) {
                throw ValidationException::withMessages(['adjustAmount' => __('bookkeeping.validation.decrease_too_large', ['max' => Toman::format($progress['amount'])])]);
            }
        }

        return DB::transaction(function () use ($obligation, $actor, $direction, $amount, $reason, $idempotencyKey): Transaction {
            Ledger::query()->whereKey($obligation->ledger_id)->lockForUpdate()->firstOrFail();
            if ($idempotencyKey !== null) {
                $existing = Transaction::query()->where('recorded_by_actor_id', $actor->id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing instanceof Transaction) {
                    abort_unless($existing->ledger_id === $obligation->ledger_id, 409);

                    return $existing;
                }
            }
            $locked = Transaction::query()->with('ledger')->lockForUpdate()->findOrFail($obligation->id);
            abort_unless($locked->type === TransactionType::Obligation && $locked->status === TransactionStatus::Confirmed, 422);

            $adjustment = Transaction::query()->create([
                'ledger_id' => $locked->ledger_id,
                'type' => TransactionType::Adjustment,
                'status' => TransactionStatus::Pending,
                'amount' => $amount,
                'direction' => $direction,
                'occurred_on' => now()->toDateString(),
                'description' => $reason,
                'from_party_id' => $locked->from_party_id,
                'to_party_id' => $locked->to_party_id,
                'related_transaction_id' => $locked->id,
                'recorded_by_actor_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'meta' => ['source' => 'correction'],
                'origin' => EntryOrigin::Correction,
            ]);

            $this->recorder->audit($locked->ledger, $actor, 'entry.recorded', $adjustment, after: BookkeepingRecorder::snapshot($adjustment) + ['direction' => $direction->value]);

            if ($this->rules->required($adjustment)->isEmpty()) {
                $adjustment->transitionTo(TransactionStatus::Confirmed, ['confirmed_at' => now()]);
            } else {
                $this->recorder->notify(
                    $this->rules->required($adjustment),
                    $adjustment,
                    'confirm_request',
                    ['who' => $actor->user->username ?? '', 'type' => (string) __('bookkeeping.types.adjustment'), 'amount' => Toman::format($amount), 'book' => $locked->ledger->name],
                    $actor,
                );
            }

            app(CreditAudit::class)->record($adjustment, $actor);

            return $adjustment->refresh();
        }, 3);
    }
}
