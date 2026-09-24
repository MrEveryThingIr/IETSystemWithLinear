<?php

namespace App\Support;

use App\AccountType;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Ledger;
use Carbon\CarbonImmutable;

class AccountingSummary
{
    public function accountBalanceMinor(Account $account): int
    {
        $totals = JournalLine::query()
            ->where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit_minor), 0) as debits, COALESCE(SUM(credit_minor), 0) as credits')
            ->first();

        $debits = (int) ($totals?->getAttribute('debits') ?? 0);
        $credits = (int) ($totals?->getAttribute('credits') ?? 0);

        return $account->type->debitNormal()
            ? $debits - $credits
            : $credits - $debits;
    }

    /**
     * @return array{income_minor: int, expense_minor: int, net_minor: int}
     */
    public function period(Ledger $ledger, string $from, string $through): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $through);

        abort_unless(
            $start !== null
            && $end !== null
            && $start->format('Y-m-d') === $from
            && $end->format('Y-m-d') === $through
            && $start->lte($end),
            422,
            'Invalid accounting summary period.',
        );

        $income = $this->typeAmount($ledger, AccountType::Income, $from, $through, debitNormal: false);
        $expense = $this->typeAmount($ledger, AccountType::Expense, $from, $through, debitNormal: true);

        return [
            'income_minor' => $income,
            'expense_minor' => $expense,
            'net_minor' => $income - $expense,
        ];
    }

    /** @return array<int, int> */
    public function balances(Ledger $ledger): array
    {
        return $ledger->accounts()
            ->get()
            ->mapWithKeys(fn (Account $account): array => [
                $account->id => $this->accountBalanceMinor($account),
            ])
            ->all();
    }

    private function typeAmount(
        Ledger $ledger,
        AccountType $type,
        string $from,
        string $through,
        bool $debitNormal,
    ): int {
        $totals = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.ledger_id', $ledger->id)
            ->where('accounts.type', $type->value)
            ->whereBetween('journal_entries.occurred_on', [$from, $through])
            ->selectRaw('COALESCE(SUM(journal_lines.debit_minor), 0) as debits, COALESCE(SUM(journal_lines.credit_minor), 0) as credits')
            ->first();

        $debits = (int) ($totals?->getAttribute('debits') ?? 0);
        $credits = (int) ($totals?->getAttribute('credits') ?? 0);

        return $debitNormal ? $debits - $credits : $credits - $debits;
    }
}
