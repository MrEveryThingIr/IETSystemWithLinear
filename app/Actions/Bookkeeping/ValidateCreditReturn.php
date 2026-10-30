<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Support\Bookkeeping\CreditAccounting;
use Illuminate\Validation\ValidationException;

/** Called only under the ledger lock, both at proposal and confirmation. */
final class ValidateCreditReturn
{
    public function execute(Ledger $ledger, LedgerParty $from, LedgerParty $to, int $amount, ?string $reason, ?Transaction $source, string $mode, ?Transaction $obligation, ?int $exclude = null): void
    {
        $source = $source === null ? null : Transaction::query()->whereKey($source->id)->lockForUpdate()->first();
        if ($source === null || $source->ledger_id !== $ledger->id || $source->type !== TransactionType::Payment || $source->status !== TransactionStatus::Confirmed || $source->from_party_id !== $to->id || $source->to_party_id !== $from->id || trim((string) $reason) === '' || ! in_array($mode, ['unused', 'applied'], true)) {
            throw ValidationException::withMessages(['refundPayment' => __('credit.validation.source')]);
        }
        $snapshot = app(CreditAccounting::class)->snapshot($ledger, excludeReturn: $exclude, currentRead: true);
        if ($snapshot['issues'] !== []) {
            throw ValidationException::withMessages(['refundPayment' => __('credit.validation.review')]);
        }
        $payment = $snapshot['payments'][$source->id];
        $capacity = $payment['available'];
        if ($mode === 'applied') {
            if ($obligation === null || $obligation->ledger_id !== $ledger->id || $obligation->type !== TransactionType::Obligation) {
                throw ValidationException::withMessages(['refundObligation' => __('credit.validation.obligation')]);
            }
            $capacity = $payment['applications'][$obligation->id] ?? 0;
            $reserved = Transaction::query()->where('ledger_id', $ledger->id)->where('type', TransactionType::Refund->value)
                ->where('related_transaction_id', $source->id)->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Disputed->value])
                ->when($exclude !== null, fn ($q) => $q->where('id', '!=', $exclude))->lockForUpdate()->with(['allocations' => fn ($q) => $q->lockForUpdate()])->get();
            foreach ($reserved as $return) {
                foreach ($return->allocations as $allocation) {
                    if ($allocation->obligation_id === $obligation->id) {
                        $capacity -= (int) $allocation->amount;
                    }
                }
            }
        }
        if ($amount > $capacity) {
            throw ValidationException::withMessages(['amount' => __('credit.validation.capacity')]);
        }
    }

    public function existing(Transaction $return): void
    {
        $this->execute($return->ledger, $return->fromParty, $return->toParty, (int) $return->amount, $return->description, $return->related, (string) ($return->meta['return_mode'] ?? ''), $return->allocations()->first()?->obligation, $return->id);
    }
}
