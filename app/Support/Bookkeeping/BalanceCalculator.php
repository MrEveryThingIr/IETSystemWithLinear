<?php

namespace App\Support\Bookkeeping;

use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\Bookkeeping\Wallet;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for every number in Bookkeeping. Nothing else
 * adds or subtracts money.
 *
 * Between two people A and B, "A owes B" is:
 *     debts A→B (+ increases, − decreases)
 *   − payments, write-offs, in-kind settlements and refunds A→B
 *   − (the same B→A, mirrored)
 * Only confirmed entries count. Entries waiting for confirmation and entries
 * under dispute are reported separately, so a person always sees
 * "confirmed / waiting / disputed" side by side.
 */
final class BalanceCalculator
{
    /**
     * Signed effect of an entry on "from owes to".
     */
    public static function sign(TransactionType $type, ?AdjustmentDirection $direction = null): int
    {
        return match ($type) {
            TransactionType::Obligation => 1,
            TransactionType::Adjustment => $direction === AdjustmentDirection::Decrease ? -1 : 1,
            TransactionType::Payment, TransactionType::Refund, TransactionType::WriteOff, TransactionType::SettlementInKind => -1,
            TransactionType::Income, TransactionType::Expense, TransactionType::Transfer => 0,
        };
    }

    /**
     * Net positions between every pair of people in a shared or project book.
     *
     * @return list<array{debtor: LedgerParty, creditor: LedgerParty, confirmed: int, pending: int, disputed: int}>
     */
    public function pairs(Ledger $ledger): array
    {
        $net = $this->netByPair($ledger);
        $parties = $ledger->parties()->with('actor.user', 'actor.profile')->get()->keyBy('id');
        $rows = [];

        foreach ($net as $key => $values) {
            [$low, $high] = array_map('intval', explode(':', $key));
            // values are "low owes high"; flip when negative.
            $confirmed = $values[TransactionStatus::Confirmed->value] ?? 0;
            $pending = $values[TransactionStatus::Pending->value] ?? 0;
            $disputed = $values[TransactionStatus::Disputed->value] ?? 0;

            if ($confirmed === 0 && $pending === 0 && $disputed === 0) {
                continue;
            }

            $lowOwes = $confirmed > 0 || ($confirmed === 0 && $pending + $disputed >= 0);
            $debtor = $parties->get($lowOwes ? $low : $high);
            $creditor = $parties->get($lowOwes ? $high : $low);

            if (! $debtor instanceof LedgerParty || ! $creditor instanceof LedgerParty) {
                continue;
            }

            $factor = $lowOwes ? 1 : -1;
            $rows[] = [
                'debtor' => $debtor,
                'creditor' => $creditor,
                'confirmed' => $confirmed * $factor,
                'pending' => $pending * $factor,
                'disputed' => $disputed * $factor,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['confirmed'] <=> $a['confirmed']);

        return $rows;
    }

    /**
     * What a person is owed and owes in one book, from their side.
     * `pending_change` is how their net position would move if everything
     * waiting for confirmation were confirmed (positive = in their favour).
     *
     * @return array{owed_to_me: int, i_owe: int, net: int, pending_change: int, disputed: int}
     */
    public function forParty(Ledger $ledger, LedgerParty $party): array
    {
        $owedToMe = 0;
        $iOwe = 0;
        $pendingChange = 0;
        $disputed = 0;

        foreach ($this->netByPair($ledger) as $key => $values) {
            [$low, $high] = array_map('intval', explode(':', $key));

            if ($low !== $party->id && $high !== $party->id) {
                continue;
            }

            // Positive = this party owes the other one.
            $factor = $low === $party->id ? 1 : -1;
            $confirmed = ($values[TransactionStatus::Confirmed->value] ?? 0) * $factor;

            $confirmed > 0 ? $iOwe += $confirmed : $owedToMe += -$confirmed;
            $pendingChange -= ($values[TransactionStatus::Pending->value] ?? 0) * $factor;
            $disputed += abs($values[TransactionStatus::Disputed->value] ?? 0);
        }

        return [
            'owed_to_me' => $owedToMe,
            'i_owe' => $iOwe,
            'net' => $owedToMe - $iOwe,
            'pending_change' => $pendingChange,
            'disputed' => $disputed,
        ];
    }

    /**
     * One debt: its current amount after adjustments, how much has been paid
     * (confirmed), what is waiting for confirmation, and what is left.
     *
     * @return array{amount: int, paid: int, pending: int, disputed: int, outstanding: int, state: string}
     */
    public function obligation(Transaction $obligation): array
    {
        $adjustments = Transaction::query()
            ->where('related_transaction_id', $obligation->id)
            ->where('type', TransactionType::Adjustment->value)
            ->where('status', TransactionStatus::Confirmed->value)
            ->get(['amount', 'direction']);

        $amount = (int) $obligation->amount;
        foreach ($adjustments as $adjustment) {
            $amount += $adjustment->direction === AdjustmentDirection::Decrease ? -(int) $adjustment->amount : (int) $adjustment->amount;
        }
        $amount = max(0, $amount);

        $rows = DB::table('bk_allocations as allocation')
            ->join('bk_transactions as entry', 'entry.id', '=', 'allocation.transaction_id')
            ->where('allocation.obligation_id', $obligation->id)
            ->groupBy('entry.type', 'entry.status')
            ->selectRaw('entry.type as type, entry.status as status, SUM(allocation.amount) as total')
            ->get();

        $paid = 0;
        $pending = 0;
        $disputed = 0;
        foreach ($rows as $row) {
            $value = (int) $row->total * ((string) $row->type === TransactionType::Refund->value ? -1 : 1);
            match ((string) $row->status) {
                TransactionStatus::Confirmed->value => $paid += $value,
                TransactionStatus::Pending->value => $pending += $value,
                TransactionStatus::Disputed->value => $disputed += $value,
                default => null,
            };
        }

        $paid = max(0, $paid);
        $outstanding = max(0, $amount - $paid);
        $state = match (true) {
            $obligation->status !== TransactionStatus::Confirmed => $obligation->status->value,
            $amount === 0 || $outstanding === 0 => 'paid',
            $paid > 0 => 'partially_paid',
            default => 'unpaid',
        };

        return [
            'amount' => $amount,
            'paid' => $paid,
            'pending' => $pending,
            'disputed' => $disputed,
            'outstanding' => $outstanding,
            'state' => $state,
        ];
    }

    /**
     * Debts a payment can still be applied to, oldest first, for allocation.
     *
     * @return Collection<int, array{obligation: Transaction, open: int<1, max>}>
     */
    public function openObligations(Ledger $ledger, LedgerParty $debtor, LedgerParty $creditor): Collection
    {
        return Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', TransactionType::Obligation->value)
            ->whereIn('status', [TransactionStatus::Confirmed->value, TransactionStatus::Pending->value])
            ->where('from_party_id', $debtor->id)
            ->where('to_party_id', $creditor->id)
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->map(function (Transaction $obligation): array {
                $progress = $this->obligation($obligation);
                $amount = $obligation->status === TransactionStatus::Pending ? (int) $obligation->amount : $progress['amount'];

                return ['obligation' => $obligation, 'open' => max(0, $amount - $progress['paid'] - max(0, $progress['pending']))];
            })
            ->filter(fn (array $row): bool => $row['open'] > 0)
            ->values()
            ->toBase();
    }

    /**
     * Personal book: each wallet's balance from its opening amount, personal
     * entries, and confirmed payments in other books that used this wallet.
     *
     * @return Collection<int, array{wallet: Wallet, balance: int}>
     */
    public function wallets(Ledger $personal): Collection
    {
        $wallets = $personal->wallets()->whereNull('archived_at')->orderBy('id')->get();
        if ($wallets->isEmpty()) {
            return collect();
        }

        $ids = $wallets->pluck('id')->all();
        $delta = array_fill_keys($ids, 0);

        $personalRows = Transaction::query()
            ->where('ledger_id', $personal->id)
            ->where('status', TransactionStatus::Confirmed->value)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value, TransactionType::Transfer->value])
            ->get(['type', 'amount', 'wallet_id', 'counter_wallet_id']);

        foreach ($personalRows as $row) {
            $amount = (int) $row->amount;
            match ($row->type) {
                TransactionType::Income => isset($delta[$row->wallet_id]) ? $delta[$row->wallet_id] += $amount : null,
                TransactionType::Expense => isset($delta[$row->wallet_id]) ? $delta[$row->wallet_id] -= $amount : null,
                TransactionType::Transfer => $this->applyTransfer($delta, (int) $row->wallet_id, (int) $row->counter_wallet_id, $amount),
                default => null,
            };
        }

        // Money that moved in shared/project books through these wallets.
        $shared = Transaction::query()
            ->whereIn('wallet_id', $ids)
            ->where('ledger_id', '!=', $personal->id)
            ->where('status', TransactionStatus::Confirmed->value)
            ->whereIn('type', [TransactionType::Payment->value, TransactionType::Refund->value])
            ->with(['fromParty', 'toParty'])
            ->get();

        foreach ($shared as $row) {
            if ((int) $row->fromParty?->actor_id === (int) $personal->owner_actor_id) {
                $delta[$row->wallet_id] -= (int) $row->amount;
            } elseif ((int) $row->toParty?->actor_id === (int) $personal->owner_actor_id) {
                $delta[$row->wallet_id] += (int) $row->amount;
            }
        }

        return $wallets->map(fn (Wallet $wallet): array => [
            'wallet' => $wallet,
            'balance' => (int) $wallet->opening_balance + $delta[$wallet->id],
        ])->values();
    }

    /**
     * Personal book: income and expense totals in a date range, overall and
     * by category.
     *
     * @return array{income: int, expense: int, net: int, by_category: list<array{category_id: int|null, direction: string, total: int}>}
     */
    public function period(Ledger $personal, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Transaction::query()
            ->where('ledger_id', $personal->id)
            ->where('status', TransactionStatus::Confirmed->value)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('type', 'category_id')
            ->selectRaw('type, category_id, SUM(amount) as total')
            ->get();

        $income = 0;
        $expense = 0;
        $byCategory = [];
        foreach ($rows as $row) {
            $total = (int) $row->getAttribute('total');
            $type = $row->type;
            $type === TransactionType::Income ? $income += $total : $expense += $total;
            $byCategory[] = [
                'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                'direction' => $type->value,
                'total' => $total,
            ];
        }

        usort($byCategory, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense, 'by_category' => $byCategory];
    }

    /**
     * Sums of "low owes high" per pair and status, from SQL aggregates.
     *
     * @return array<string, array<string, int>>
     */
    private function netByPair(Ledger $ledger): array
    {
        $rows = DB::table('bk_transactions')
            ->where('ledger_id', $ledger->id)
            ->whereIn('type', array_map(fn (TransactionType $type): string => $type->value, TransactionType::shared()))
            ->whereIn('status', [TransactionStatus::Confirmed->value, TransactionStatus::Pending->value, TransactionStatus::Disputed->value])
            ->whereNotNull('from_party_id')
            ->whereNotNull('to_party_id')
            ->groupBy('type', 'direction', 'status', 'from_party_id', 'to_party_id')
            ->selectRaw('type, direction, status, from_party_id, to_party_id, SUM(amount) as total')
            ->get();

        $net = [];
        foreach ($rows as $row) {
            $from = (int) $row->from_party_id;
            $to = (int) $row->to_party_id;
            if ($from === $to) {
                continue;
            }

            $sign = self::sign(
                TransactionType::from((string) $row->type),
                $row->direction !== null ? AdjustmentDirection::from((string) $row->direction) : null,
            );
            // Normalize to "low owes high".
            [$low, $high, $orientation] = $from < $to ? [$from, $to, 1] : [$to, $from, -1];
            $key = $low.':'.$high;
            $net[$key][(string) $row->status] = ($net[$key][(string) $row->status] ?? 0) + $sign * $orientation * (int) $row->total;
        }

        return $net;
    }

    /** @param  array<int, int>  $delta */
    private function applyTransfer(array &$delta, int $from, int $to, int $amount): void
    {
        if (isset($delta[$from])) {
            $delta[$from] -= $amount;
        }
        if (isset($delta[$to])) {
            $delta[$to] += $amount;
        }
    }
}
