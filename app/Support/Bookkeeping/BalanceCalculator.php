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

/** Shared balances derive from CreditAccounting's directed obligation and credit
 * projection. Cash movement never establishes an opposite-direction debt.
 * Personal wallet and period calculations remain separate cash-flow views.
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

    /** @return array<string, mixed> */
    public function credit(Ledger $ledger, bool $currentRead = false): array
    {
        return app(CreditAccounting::class)->snapshot($ledger, currentRead: $currentRead);
    }

    /** Outstanding obligations remain directional, including reciprocal debts.
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array{debtor: LedgerParty, creditor: LedgerParty, confirmed: int, pending: int, disputed: int}>
     */
    public function pairs(Ledger $ledger, ?array $snapshot = null): array
    {
        $parties = $ledger->parties()->with('actor.user', 'actor.profile')->get()->keyBy('id');
        $rows = [];
        foreach (($snapshot ?? $this->credit($ledger))['obligations'] as $obligation) {
            $key = $obligation['from'].':'.$obligation['to'];
            if (! isset($parties[$obligation['from']], $parties[$obligation['to']])) {
                continue;
            }
            $rows[$key] ??= ['debtor' => $parties[$obligation['from']], 'creditor' => $parties[$obligation['to']], 'confirmed' => 0, 'pending' => 0, 'disputed' => 0];
            $rows[$key]['confirmed'] += $obligation['outstanding'];
            if ($obligation['status'] === 'pending') {
                $rows[$key]['pending'] += $obligation['amount'];
            } elseif ($obligation['status'] === 'disputed') {
                $rows[$key]['disputed'] += $obligation['amount'];
            }
            $rows[$key]['pending'] -= $obligation['pending'];
            $rows[$key]['disputed'] += $obligation['disputed'];
        }

        return array_values(array_filter($rows, fn (array $row): bool => $row['confirmed'] !== 0 || $row['pending'] !== 0 || $row['disputed'] !== 0));
    }

    /** @return array{owed_to_me: int, i_owe: int, net: int, pending_change: int, disputed: int, advance_paid: int, advance_received: int} */
    public function forParty(Ledger $ledger, LedgerParty $party): array
    {
        $snapshot = $this->credit($ledger);
        $owed = 0;
        $owe = 0;
        $pending = 0;
        $disputed = 0;
        foreach ($this->pairs($ledger, $snapshot) as $pair) {
            if ($pair['debtor']->id === $party->id) {
                $owe += $pair['confirmed'];
                $pending -= $pair['pending'];
                $disputed += abs($pair['disputed']);
            } elseif ($pair['creditor']->id === $party->id) {
                $owed += $pair['confirmed'];
                $pending += $pair['pending'];
                $disputed += abs($pair['disputed']);
            }
        }
        $paid = 0;
        $received = 0;
        foreach ($snapshot['payments'] as $payment) {
            if ($payment['from'] === $party->id) {
                $paid += $payment['available'] + ($payment['status'] === 'confirmed' ? $payment['reserved'] : 0);
            } elseif ($payment['to'] === $party->id) {
                $received += $payment['available'] + ($payment['status'] === 'confirmed' ? $payment['reserved'] : 0);
            }
        }

        return ['owed_to_me' => $owed, 'i_owe' => $owe, 'net' => $owed - $owe, 'pending_change' => $pending, 'disputed' => $disputed, 'advance_paid' => $paid, 'advance_received' => $received];
    }

    /** @return array{amount: int, paid: int, pending: int, disputed: int, outstanding: int, state: string} */
    public function obligation(Transaction $obligation, bool $currentRead = false): array
    {
        $row = $this->credit($obligation->ledger, $currentRead)['obligations'][$obligation->id];

        return array_intersect_key($row, array_flip(['amount', 'paid', 'pending', 'disputed', 'outstanding', 'state']));
    }

    /**
     * Debts a payment can still be applied to, oldest first, for allocation.
     *
     * @return Collection<int, array{obligation: Transaction, open: int<1, max>}>
     */
    public function openObligations(Ledger $ledger, LedgerParty $debtor, LedgerParty $creditor, bool $currentRead = false): Collection
    {
        return Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->when($currentRead, fn ($q) => $q->lockForUpdate())
            ->where('type', TransactionType::Obligation->value)
            ->whereIn('status', [TransactionStatus::Confirmed->value, TransactionStatus::Pending->value])
            ->where('from_party_id', $debtor->id)
            ->where('to_party_id', $creditor->id)
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->map(function (Transaction $obligation) use ($currentRead): array {
                $progress = $this->obligation($obligation, $currentRead);
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
