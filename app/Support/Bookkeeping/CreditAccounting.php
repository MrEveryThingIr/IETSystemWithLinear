<?php

namespace App\Support\Bookkeeping;

use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use Illuminate\Support\Facades\DB;

/** Read-only allocation projection. Stored allocations are immutable instructions;
 * automatic applications are deterministic and audited with every entry change.
 * No projection ever creates a debt or changes a historical entry.
 */
final class CreditAccounting
{
    /** @return array<string, mixed> */
    public function snapshot(Ledger $ledger, bool $previewHistorical = false, ?int $excludeReturn = null, bool $currentRead = false): array
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->snapshot($ledger, $previewHistorical, $excludeReturn, $currentRead), 3);
        }
        $entries = Transaction::query()->where('ledger_id', $ledger->id)->when($currentRead, fn ($q) => $q->lockForUpdate())->with(['allocations' => fn ($q) => $q->when($currentRead, fn ($q) => $q->lockForUpdate())])->orderBy('occurred_on')->orderBy('id')->get()->keyBy('id');
        $obligations = [];
        $payments = [];
        $issues = [];
        foreach ($entries as $entry) {
            if ($entry->type === TransactionType::Obligation) {
                $amount = (int) $entry->amount;
                foreach ($entries as $adjustment) {
                    if ($adjustment->type === TransactionType::Adjustment && $adjustment->related_transaction_id === $entry->id && $adjustment->status === TransactionStatus::Confirmed) {
                        $amount += $adjustment->direction === AdjustmentDirection::Decrease ? -(int) $adjustment->amount : (int) $adjustment->amount;
                    }
                }
                $obligations[$entry->id] = ['uuid' => $entry->uuid, 'from' => (int) $entry->from_party_id, 'to' => (int) $entry->to_party_id, 'status' => $entry->status->value, 'amount' => max(0, $amount), 'paid' => 0, 'pending' => 0, 'disputed' => 0];
            }
            if ($entry->type === TransactionType::Payment) {
                $payments[$entry->id] = ['uuid' => $entry->uuid, 'from' => (int) $entry->from_party_id, 'to' => (int) $entry->to_party_id, 'status' => $entry->status->value, 'amount' => (int) $entry->amount, 'returned' => 0, 'reserved' => 0, 'applied' => 0, 'available' => 0, 'application_sources' => [], 'applications' => array_fill_keys($entries->keys()->all(), 0), 'blocked' => array_fill_keys($entries->keys()->all(), 0), 'automatic' => $previewHistorical || ($entry->meta['credit_policy'] ?? null) === 'fifo_v1'];
            }
        }
        // A return reduces only its identifiable source. Never guess an old refund's source.
        foreach ($entries as $entry) {
            if ($entry->id === $excludeReturn || $entry->type !== TransactionType::Refund || in_array($entry->status, [TransactionStatus::Rejected, TransactionStatus::Void], true)) {
                continue;
            }
            $source = $entry->related_transaction_id;
            if (! isset($payments[$source]) || ! in_array($entry->meta['return_mode'] ?? null, ['unused', 'applied'], true)) {
                $issues[] = ['entry' => $entry->uuid, 'reason' => 'Unlinked historical refund requires explicit review'];

                continue;
            }
            $payment = &$payments[$source];
            if ($entry->from_party_id !== $payment['to'] || $entry->to_party_id !== $payment['from']) {
                $issues[] = ['entry' => $entry->uuid, 'reason' => 'Return direction does not match source'];
                unset($payment);

                continue;
            }
            if ($entry->status === TransactionStatus::Confirmed) {
                $payment['returned'] += (int) $entry->amount;
                if ($entry->meta['return_mode'] === 'applied') {
                    foreach ($entry->allocations as $allocation) {
                        $payment['blocked'][$allocation->obligation_id] = ($payment['blocked'][$allocation->obligation_id] ?? 0) + (int) $allocation->amount;
                    }
                }
            } elseif ($entry->meta['return_mode'] === 'unused') {
                // Pending/disputed returns reserve unused credit but do not change confirmed debt.
                $payment['reserved'] += (int) $entry->amount;
            }
            unset($payment);
        }
        // Noncash settlements have no advance remainder.
        foreach ($entries as $entry) {
            if (! in_array($entry->type, [TransactionType::WriteOff, TransactionType::SettlementInKind], true)) {
                continue;
            }
            $left = (int) $entry->amount;
            foreach ($entry->allocations as $allocation) {
                $id = $allocation->obligation_id;
                if (! isset($obligations[$id]) || $obligations[$id]['from'] !== $entry->from_party_id || $obligations[$id]['to'] !== $entry->to_party_id) {
                    continue;
                }
                $field = match ($entry->status) {
                    TransactionStatus::Confirmed => 'paid', TransactionStatus::Pending => 'pending', TransactionStatus::Disputed => 'disputed', default => null
                };
                if ($field === 'paid' && $obligations[$id]['status'] !== 'confirmed') {
                    continue;
                }
                if ($field !== null) {
                    $take = min($left, (int) $allocation->amount, max(0, $obligations[$id]['amount'] - $obligations[$id][$field]));
                    $obligations[$id][$field] += $take;
                    $left -= $take;
                }
            }
        }
        // Explicit instructions have priority over automatic applications.
        foreach ($payments as $id => &$payment) {
            $left = max(0, $payment['amount'] - $payment['returned']);
            foreach ($entries[$id]->allocations as $allocation) {
                $oid = (int) $allocation->obligation_id;
                if (! isset($obligations[$oid]) || $obligations[$oid]['from'] !== $payment['from'] || $obligations[$oid]['to'] !== $payment['to']) {
                    continue;
                }
                $value = max(0, (int) $allocation->amount - ($payment['blocked'][$oid] ?? 0));
                if ($payment['status'] === 'confirmed' && $obligations[$oid]['status'] === 'confirmed') {
                    $take = min($left, $value, max(0, $obligations[$oid]['amount'] - $obligations[$oid]['paid']));
                    $obligations[$oid]['paid'] += $take;
                    $payment['applications'][$oid] = $take;
                    $payment['applied'] += $take;
                    $left -= $take;
                } elseif (in_array($payment['status'], ['pending', 'disputed'], true)) {
                    $field = $payment['status'];
                    $take = min($left, $value);
                    $obligations[$oid][$field] += $take;
                    $left -= $take;
                }
            }
        }
        unset($payment);
        foreach ($payments as &$payment) {
            if ($payment['status'] !== 'confirmed') {
                $payment['applications'] = [];

                continue;
            }
            $left = max(0, $payment['amount'] - $payment['returned'] - $payment['applied'] - $payment['reserved']);
            if ($payment['automatic'] && $issues === []) {
                foreach ($obligations as $oid => &$obligation) {
                    if ($obligation['status'] !== 'confirmed' || $obligation['from'] !== $payment['from'] || $obligation['to'] !== $payment['to']) {
                        continue;
                    }
                    $capacity = max(0, $obligation['amount'] - ($payment['blocked'][$oid] ?? 0) - ($payment['applications'][$oid] ?? 0));
                    $take = min($left, $capacity, max(0, $obligation['amount'] - $obligation['paid']));
                    if ($take > 0) {
                        $obligation['paid'] += $take;
                        $payment['applications'][$oid] = ($payment['applications'][$oid] ?? 0) + $take;
                        $payment['applied'] += $take;
                        $left -= $take;
                    }
                }
                unset($obligation);
            }
            $payment['applications'] = array_filter($payment['applications'], fn (int $amount): bool => $amount > 0);
            foreach ($payment['applications'] as $oid => $amount) {
                $payment['application_sources'][] = ['obligation' => $obligations[$oid]['uuid'], 'amount' => $amount];
            }
            $payment['available'] = $left;
            if ($payment['amount'] < $payment['returned'] + $payment['applied'] + $payment['reserved']) {
                $issues[] = ['entry' => $payment['uuid'], 'reason' => 'Credit conservation requires review'];
            }
        }
        unset($payment);
        foreach ($obligations as $oid => &$obligation) {
            $obligation['cash_paid'] = 0;
            foreach ($payments as $payment) {
                $obligation['cash_paid'] += $payment['applications'][$oid] ?? 0;
            }
        }
        unset($obligation);
        foreach ($obligations as &$obligation) {
            $obligation['outstanding'] = $obligation['status'] === 'confirmed' ? max(0, $obligation['amount'] - $obligation['paid']) : 0;
            $obligation['state'] = $obligation['status'] !== 'confirmed' ? $obligation['status'] : ($obligation['outstanding'] === 0 ? 'paid' : ($obligation['paid'] > 0 ? 'partially_paid' : 'unpaid'));
        }
        unset($obligation);

        return ['obligations' => $obligations, 'payments' => $payments, 'issues' => $issues];
    }
}
