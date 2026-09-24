<?php

namespace Tests\Feature;

use App\AccountType;
use App\Actions\Accounting\CorrectJournalEntry;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\CreatePersonalLedger;
use App\Actions\Accounting\PostJournalEntry;
use App\Actions\Accounting\RecordExpense;
use App\Actions\Accounting\RecordIncome;
use App\Actions\Accounting\RecordOpeningBalance;
use App\Actions\Accounting\RecordTransfer;
use App\Actions\Accounting\ReverseJournalEntry;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\JournalLine;
use App\Support\AccountingSummary;
use App\Support\MoneyAmount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccountingKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_friendly_actions_reconcile_to_balanced_immutable_ledger_truth(): void
    {
        $bob = Actor::factory()->create();
        $ledger = app(CreatePersonalLedger::class)->execute($bob->user, 'EUR');
        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        app(RecordOpeningBalance::class)->execute($ledger, $bob->user, $cash, 100000, '2026-09-01');
        app(RecordExpense::class)->execute($ledger, $bob->user, $cash, 2500, '2026-09-02', 'Work gloves');
        app(RecordIncome::class)->execute($ledger, $bob->user, $cash, 10000, '2026-09-03', 'Service income');

        $bank = app(CreateLedgerAccount::class)->execute(
            $ledger,
            $bob->user,
            'Bank',
            AccountType::Asset,
        );

        app(RecordTransfer::class)->execute($ledger, $bob->user, $cash, $bank, 3000, '2026-09-04');

        $summary = app(AccountingSummary::class);

        $this->assertSame(104500, $summary->accountBalanceMinor($cash));
        $this->assertSame(3000, $summary->accountBalanceMinor($bank));
        $this->assertSame([
            'income_minor' => 10000,
            'expense_minor' => 2500,
            'net_minor' => 7500,
        ], $summary->period($ledger, '2026-09-01', '2026-09-30'));

        foreach ($ledger->journalEntries()->with('lines')->get() as $entry) {
            $this->assertSame(
                $entry->lines->sum('debit_minor'),
                $entry->lines->sum('credit_minor'),
            );
            $this->assertGreaterThan(0, $entry->lines->sum('debit_minor'));
        }

        $this->assertSame(4, $ledger->journalEntries()->count());
    }

    public function test_unbalanced_or_cross_ledger_entry_is_rejected_atomically(): void
    {
        $bob = Actor::factory()->create();
        $ledger = app(CreatePersonalLedger::class)->execute($bob->user, 'EUR');
        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        try {
            app(PostJournalEntry::class)->execute(
                $ledger,
                $bob->user,
                JournalEntryKind::Expense,
                '2026-09-01',
                'Broken entry',
                [
                    ['account' => $cash, 'credit_minor' => 1000],
                    ['account' => $cash, 'debit_minor' => 900],
                ],
            );

            $this->fail('An unbalanced Journal Entry was posted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);

        $other = Actor::factory()->create();
        $otherLedger = app(CreatePersonalLedger::class)->execute($other->user, 'EUR');
        $otherCash = $otherLedger->accounts()->where('system_key', 'cash')->firstOrFail();

        try {
            app(PostJournalEntry::class)->execute(
                $ledger,
                $bob->user,
                JournalEntryKind::Transfer,
                '2026-09-01',
                'Cross ledger',
                [
                    ['account' => $cash, 'credit_minor' => 1000],
                    ['account' => $otherCash, 'debit_minor' => 1000],
                ],
            );

            $this->fail('A cross-Ledger Journal Line was posted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_reversal_restores_balances_without_mutating_original_entry(): void
    {
        $bob = Actor::factory()->create();
        $ledger = app(CreatePersonalLedger::class)->execute($bob->user, 'EUR');
        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        app(RecordOpeningBalance::class)->execute($ledger, $bob->user, $cash, 10000, '2026-09-01');
        $expense = app(RecordExpense::class)->execute(
            $ledger,
            $bob->user,
            $cash,
            2500,
            '2026-09-02',
            'Work gloves',
        );

        $originalDescription = $expense->description;
        $reversal = app(ReverseJournalEntry::class)->execute(
            $expense,
            $bob->user,
            '2026-09-03',
            'Gloves entry reversed',
        );

        $summary = app(AccountingSummary::class);

        $this->assertSame(JournalEntryKind::Reversal, $reversal->kind);
        $this->assertSame($expense->id, $reversal->reverses_entry_id);
        $this->assertSame(10000, $summary->accountBalanceMinor($cash));
        $this->assertSame(0, $summary->period($ledger, '2026-09-01', '2026-09-30')['expense_minor']);
        $this->assertSame($originalDescription, $expense->fresh()->description);

        $this->expectException(LogicException::class);
        $expense->update(['description' => 'Mutated']);
    }

    public function test_correction_preserves_original_and_posts_reversal_plus_replacement(): void
    {
        $bob = Actor::factory()->create();
        $ledger = app(CreatePersonalLedger::class)->execute($bob->user, 'EUR');
        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        app(RecordOpeningBalance::class)->execute($ledger, $bob->user, $cash, 10000, '2026-09-01');
        $expense = app(RecordExpense::class)->execute(
            $ledger,
            $bob->user,
            $cash,
            2500,
            '2026-09-02',
            'Work gloves',
        );
        $expenseAccount = $expense->lines()->where('debit_minor', '>', 0)->with('account')->firstOrFail()->account;

        $result = app(CorrectJournalEntry::class)->execute(
            $expense,
            $bob->user,
            '2026-09-03',
            JournalEntryKind::Expense,
            'Corrected work gloves',
            [
                ['account' => $expenseAccount, 'debit_minor' => 2000],
                ['account' => $cash, 'credit_minor' => 2000],
            ],
        );

        $summary = app(AccountingSummary::class);

        $this->assertSame($expense->id, $result['reversal']->reverses_entry_id);
        $this->assertSame($expense->id, $result['correction']->correction_of_entry_id);
        $this->assertSame(8000, $summary->accountBalanceMinor($cash));
        $this->assertSame(2000, $summary->period($ledger, '2026-09-01', '2026-09-30')['expense_minor']);
        $this->assertSame('Work gloves', $expense->fresh()->description);
    }

    public function test_personal_ledger_is_private_to_its_owner(): void
    {
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();
        $ledger = app(CreatePersonalLedger::class)->execute($bob->user, 'EUR');
        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        $this->assertTrue($bob->user->can('view', $ledger));
        $this->assertTrue($bob->user->can('manage', $ledger));
        $this->assertFalse($carol->user->can('view', $ledger));
        $this->assertFalse($carol->user->can('manage', $ledger));

        try {
            app(RecordExpense::class)->execute(
                $ledger,
                $carol->user,
                $cash,
                100,
                '2026-09-01',
            );

            $this->fail('An outsider posted into Bob\'s Ledger.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }

    public function test_money_amount_conversion_never_uses_float_math(): void
    {
        $this->assertSame(1234, MoneyAmount::parse('12.34', 2));
        $this->assertSame(1200, MoneyAmount::parse('12', 2));
        $this->assertSame(12, MoneyAmount::parse('12', 0));
        $this->assertSame('12.34', MoneyAmount::format(1234, 2));
        $this->assertSame('-12.34', MoneyAmount::format(-1234, 2));

        $this->expectException(\InvalidArgumentException::class);
        MoneyAmount::parse('12.345', 2);
    }

    public function test_journal_lines_are_immutable_and_must_have_exactly_one_side(): void
    {
        $line = JournalLine::factory()->create();

        $this->expectException(LogicException::class);
        $line->update(['debit_minor' => 200]);
    }
}
