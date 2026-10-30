<?php

namespace Tests\Feature\Bookkeeping;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\RaiseDispute;
use App\Actions\Bookkeeping\RecordAdjustment;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Actions\Bookkeeping\ResolveDispute;
use App\Actions\Bookkeeping\WithdrawEntry;
use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\DisputeOutcome;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Livewire\Bookkeeping\BookShow;
use App\Models\Actor;
use App\Models\Bookkeeping\AuditLog;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\CreditAccounting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CreditAccountingTest extends TestCase
{
    use RefreshDatabase;

    private Actor $payer;

    private Actor $payee;

    private Ledger $book;

    private LedgerParty $from;

    private LedgerParty $to;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payer = Actor::factory()->create();
        $this->payee = Actor::factory()->create();
        $this->book = app(CreateLedger::class)->execute($this->payer->user, LedgerKind::Shared, 'Credit regression');
        $this->to = app(AddLedgerParty::class)->execute($this->book, $this->payer->user, $this->payee->user->username, null);
        $this->from = $this->book->partyFor($this->payer);
    }

    private function entry(TransactionType $type, int $amount, bool $confirmed = true, bool $reverse = false, ?string $key = null): Transaction
    {
        [$a, $b, $from, $to] = $reverse ? [$this->payee, $this->payer, $this->to, $this->from] : [$this->payer, $this->payee, $this->from, $this->to];
        $entry = app(RecordSharedEntry::class)->execute($this->book, $a->user, $type, $from, $to, $amount, '2026-10-29', allocations: [], idempotencyKey: $key);
        if ($confirmed) {
            app(DecideOnEntry::class)->approve($entry, $b->user);
        }

        return $entry->fresh();
    }

    private function snapshot(): array
    {
        $s = app(CreditAccounting::class)->snapshot($this->book);
        $this->assertSame([], $s['issues']);
        foreach ($s['payments'] as $payment) {
            if ($payment['status'] === 'confirmed') {
                $this->assertSame($payment['amount'], $payment['applied'] + $payment['returned'] + $payment['available'] + $payment['reserved']);
                $this->assertSame($payment['applied'], array_sum($payment['applications']));
            }
        }
        $calc = app(BalanceCalculator::class);
        $a = $calc->forParty($this->book, $this->from);
        $b = $calc->forParty($this->book, $this->to);
        $this->assertSame($a['i_owe'], $b['owed_to_me']);
        $this->assertSame($a['owed_to_me'], $b['i_owe']);
        $this->assertSame($a['advance_paid'], $b['advance_received']);
        $this->assertSame($a['net'], -$b['net']);

        return $s;
    }

    private function refund(Transaction $payment, int $amount, string $mode = 'unused', ?Transaction $obligation = null, bool $confirm = true, ?string $key = null): Transaction
    {
        $return = app(RecordSharedEntry::class)->execute($this->book, $this->payee->user, TransactionType::Refund, $this->to, $this->from, $amount, '2026-10-29', 'Return agreed with payer', idempotencyKey: $key, refundPayment: $payment, returnMode: $mode, refundObligation: $obligation);
        if ($confirm) {
            app(DecideOnEntry::class)->approve($return, $this->payer->user);
        }

        return $return->fresh();
    }

    public function test_advance_late_obligations_and_multiple_cycles_use_each_toman_once(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000, key: 'advance');
        $s = $this->snapshot();
        $this->assertSame(1_000_000, $s['payments'][$payment->id]['available']);
        $this->assertSame(0, app(BalanceCalculator::class)->forParty($this->book, $this->to)['i_owe']);
        $first = $this->entry(TransactionType::Obligation, 600_000);
        $s = $this->snapshot();
        $this->assertSame(600_000, $s['obligations'][$first->id]['paid']);
        $this->assertSame(400_000, $s['payments'][$payment->id]['available']);
        $second = $this->entry(TransactionType::Obligation, 700_000);
        $s = $this->snapshot();
        $this->assertSame(400_000, $s['obligations'][$second->id]['paid']);
        $this->assertSame(300_000, app(BalanceCalculator::class)->obligation($second)['outstanding']);
        $this->assertSame(2, count($s['payments'][$payment->id]['applications']));
        $this->assertSame($s, $this->snapshot());
        $this->assertSame(0, $payment->allocations()->count(), 'Immutable original instructions are not rewritten.');
        $audit = AuditLog::query()->where('transaction_id', $second->id)->where('action', 'credit.projected')->latest('id')->firstOrFail();
        $this->assertEquals($s, $audit->after['snapshot']);
    }

    public function test_unused_return_is_source_linked_and_does_not_reopen_paid_work(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $return = $this->refund($payment, 400_000, key: 'return-one');
        $s = $this->snapshot();
        $this->assertSame($payment->id, $return->related_transaction_id);
        $this->assertSame(400_000, $s['payments'][$payment->id]['returned']);
        $this->assertSame(0, $s['payments'][$payment->id]['available']);
        $this->assertSame('paid', $s['obligations'][$debt->id]['state']);
        $this->assertSame($return->id, $this->refund($payment, 400_000, key: 'return-one')->id);
    }

    public function test_applied_return_reopens_only_the_named_obligation_even_with_other_credit(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $this->refund($payment, 200_000, 'applied', $debt);
        $s = $this->snapshot();
        $this->assertSame(400_000, $s['obligations'][$debt->id]['paid']);
        $this->assertSame(200_000, $s['obligations'][$debt->id]['outstanding']);
        $this->assertSame(400_000, $s['payments'][$payment->id]['available']);
    }

    public function test_pending_returns_reserve_credit_and_rejection_releases_it(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $return = $this->refund($payment, 700_000, confirm: false);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $s = $this->snapshot();
        $this->assertSame(300_000, $s['obligations'][$debt->id]['paid']);
        $this->assertSame(700_000, $s['payments'][$payment->id]['reserved']);
        app(DecideOnEntry::class)->reject($return, $this->payer->user, 'No return occurred');
        $s = $this->snapshot();
        $this->assertSame(600_000, $s['obligations'][$debt->id]['paid']);
        $this->assertSame(400_000, $s['payments'][$payment->id]['available']);
    }

    public function test_opposite_direction_and_genuine_reciprocal_debts_stay_separate(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $reverse = $this->entry(TransactionType::Obligation, 300_000, reverse: true);
        $debt = $this->entry(TransactionType::Obligation, 1_200_000);
        $s = $this->snapshot();
        $this->assertSame(0, $s['obligations'][$reverse->id]['paid']);
        $this->assertSame(1_000_000, $s['obligations'][$debt->id]['paid']);
        $this->assertCount(2, app(BalanceCalculator::class)->pairs($this->book));
        $reversePay = $this->entry(TransactionType::Payment, 500_000, reverse: true);
        $s = $this->snapshot();
        $this->assertSame(300_000, $s['payments'][$reversePay->id]['applied']);
        $this->assertSame(200_000, $s['payments'][$reversePay->id]['available']);
        $this->assertSame(0, $s['payments'][$payment->id]['available']);
    }

    public function test_pending_rejected_and_voided_payments_never_settle_an_obligation(): void
    {
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $pending = $this->entry(TransactionType::Payment, 700_000, false);
        $this->assertSame(0, $this->snapshot()['obligations'][$debt->id]['paid']);
        app(DecideOnEntry::class)->reject($pending, $this->payee->user, 'Not received');
        $void = $this->entry(TransactionType::Payment, 700_000, false);
        app(WithdrawEntry::class)->execute($void, $this->payer->user);
        $this->assertSame(600_000, $this->snapshot()['obligations'][$debt->id]['outstanding']);
        $pendingDebt = $this->entry(TransactionType::Obligation, 500_000, false);
        $this->entry(TransactionType::Payment, 1_000_000);
        $s = $this->snapshot();
        $this->assertSame(0, $s['obligations'][$pendingDebt->id]['paid']);
        $this->assertSame(0, $s['obligations'][$pendingDebt->id]['outstanding']);
    }

    public function test_disputing_and_voiding_payment_reopens_debt_and_keeps_history(): void
    {
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $dispute = app(RaiseDispute::class)->execute($payment, $this->payee->user, 'Wrong receipt');
        $s = $this->snapshot();
        $this->assertSame(600_000, $s['obligations'][$debt->id]['outstanding']);
        $this->assertSame(0, $s['payments'][$payment->id]['available']);
        app(ResolveDispute::class)->execute($dispute, $this->payer->user, DisputeOutcome::Voided);
        $this->assertSame(600_000, $this->snapshot()['obligations'][$debt->id]['outstanding']);
        $this->assertSame(TransactionStatus::Void, $payment->fresh()->status);
        $this->assertSame(1_000_000, (int) $payment->fresh()->amount);
    }

    public function test_disputed_obligation_releases_credit_and_upholding_recomputes_once(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $dispute = app(RaiseDispute::class)->execute($debt, $this->payee->user, 'Quantity wrong');
        $this->assertSame(1_000_000, $this->snapshot()['payments'][$payment->id]['available']);
        app(ResolveDispute::class)->execute($dispute, $this->payee->user, DisputeOutcome::Upheld);
        $this->assertSame(400_000, $this->snapshot()['payments'][$payment->id]['available']);
    }

    public function test_adjustment_releases_excess_and_later_cycles_consume_only_available_credit(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 900_000);
        $adjustment = app(RecordAdjustment::class)->execute($debt, $this->payer->user, AdjustmentDirection::Decrease, 300_000, 'Agreed smaller quantity');
        app(DecideOnEntry::class)->approve($adjustment, $this->payee->user);
        $this->assertSame(400_000, $this->snapshot()['payments'][$payment->id]['available']);
        $next = $this->entry(TransactionType::Obligation, 700_000);
        $this->assertSame(300_000, $this->snapshot()['obligations'][$next->id]['outstanding']);
    }

    public function test_duplicates_do_not_add_cash_or_audit_events(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000, key: 'duplicate');
        $before = AuditLog::query()->count();
        $duplicate = $this->entry(TransactionType::Payment, 1_000_000, key: 'duplicate');
        $this->assertSame($payment->id, $duplicate->id);
        $this->assertSame($before, AuditLog::query()->count());
        $this->snapshot();
    }

    public function test_unlinked_or_excessive_returns_are_refused(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute($this->book, $this->payee->user, TransactionType::Refund, $this->to, $this->from, 1, '2026-10-29', 'No source'), ValidationException::class);
        $this->assertThrows(fn () => $this->refund($payment, 1_000_001), ValidationException::class);
        $this->refund($payment, 700_000, confirm: false);
        $this->assertThrows(fn () => $this->refund($payment, 400_000), ValidationException::class);
        $this->snapshot();
    }

    public function test_legacy_unallocated_credit_has_read_only_preview_and_no_automatic_reconciliation(): void
    {
        $payment = Transaction::query()->create(['ledger_id' => $this->book->id, 'type' => TransactionType::Payment, 'status' => TransactionStatus::Confirmed, 'amount' => 1_000_000, 'occurred_on' => '2026-10-29', 'from_party_id' => $this->from->id, 'to_party_id' => $this->to->id, 'recorded_by_actor_id' => $this->payer->id, 'meta' => ['source' => 'manual']]);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $original = $payment->fresh()->getAttributes();
        $this->assertSame(1_000_000, $this->snapshot()['payments'][$payment->id]['available']);
        $preview = app(CreditAccounting::class)->snapshot($this->book, true);
        $this->assertSame(600_000, $preview['obligations'][$debt->id]['paid']);
        $this->assertSame($original, $payment->fresh()->getAttributes());
        $this->assertSame(0, $payment->allocations()->count());
    }

    public function test_voided_obligation_releases_credit_without_erasing_the_original_application_evidence(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $dispute = app(RaiseDispute::class)->execute($debt, $this->payee->user, 'Cancelled contract obligation');
        app(ResolveDispute::class)->execute($dispute, $this->payer->user, DisputeOutcome::Voided);
        $s = $this->snapshot();
        $this->assertSame(1_000_000, $s['payments'][$payment->id]['available']);
        $this->assertSame(0, $s['obligations'][$debt->id]['paid']);
        $this->assertTrue(AuditLog::query()->where('transaction_id', $debt->id)->where('action', 'credit.projected')->count() >= 3);
        $later = $this->entry(TransactionType::Obligation, 700_000);
        $this->assertSame(700_000, $this->snapshot()['obligations'][$later->id]['paid']);
    }

    public function test_upheld_payment_restores_application_and_disputed_refund_is_not_a_new_debt(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $dispute = app(RaiseDispute::class)->execute($payment, $this->payee->user, 'Receipt needs checking');
        app(ResolveDispute::class)->execute($dispute, $this->payee->user, DisputeOutcome::Upheld);
        $this->assertSame(600_000, $this->snapshot()['obligations'][$debt->id]['paid']);
        $return = $this->refund($payment, 400_000);
        $dispute = app(RaiseDispute::class)->execute($return, $this->payer->user, 'Returned money not received');
        $s = $this->snapshot();
        $this->assertSame(400_000, $s['payments'][$payment->id]['reserved']);
        $this->assertSame(0, $s['payments'][$payment->id]['returned']);
        app(ResolveDispute::class)->execute($dispute, $this->payee->user, DisputeOutcome::Voided);
        $this->assertSame(400_000, $this->snapshot()['payments'][$payment->id]['available']);
    }

    public function test_pending_applied_return_cannot_reserve_the_same_application_twice(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $first = $this->refund($payment, 400_000, 'applied', $debt, false);
        $this->assertThrows(fn () => $this->refund($payment, 300_000, 'applied', $debt), ValidationException::class);
        app(WithdrawEntry::class)->execute($first, $this->payee->user);
        $this->refund($payment, 600_000, 'applied', $debt);
        $this->assertSame(600_000, $this->snapshot()['obligations'][$debt->id]['outstanding']);
    }

    public function test_explicit_allocations_and_automatic_credit_share_one_payment_capacity(): void
    {
        $a = $this->entry(TransactionType::Obligation, 500_000);
        $b = $this->entry(TransactionType::Obligation, 900_000);
        $pay = app(RecordSharedEntry::class)->execute($this->book, $this->payer->user, TransactionType::Payment, $this->from, $this->to, 800_000, '2026-10-29', allocations: [$b->uuid => 600_000]);
        app(DecideOnEntry::class)->approve($pay, $this->payee->user);
        $s = $this->snapshot();
        $this->assertSame(600_000, $s['payments'][$pay->id]['applications'][$b->id]);
        $this->assertSame(200_000, $s['payments'][$pay->id]['applications'][$a->id]);
        $this->assertSame(600_000, (int) $pay->allocations()->sole()->amount);
        $return = $this->refund($pay, 200_000, 'applied', $b);
        $this->assertSame(400_000, $this->snapshot()['payments'][$pay->id]['applications'][$b->id]);
        $d = app(RaiseDispute::class)->execute($return, $this->payer->user, 'Check correction');
        app(ResolveDispute::class)->execute($d, $this->payer->user, DisputeOutcome::Upheld);
        $this->assertSame(400_000, $this->snapshot()['payments'][$pay->id]['applications'][$b->id]);
    }

    public function test_a_second_decrease_cannot_make_an_obligation_negative_at_confirmation(): void
    {
        $debt = $this->entry(TransactionType::Obligation, 600_000);
        $a = app(RecordAdjustment::class)->execute($debt, $this->payer->user, AdjustmentDirection::Decrease, 400_000, 'First correction');
        $b = app(RecordAdjustment::class)->execute($debt, $this->payer->user, AdjustmentDirection::Decrease, 400_000, 'Second correction');
        app(DecideOnEntry::class)->approve($a, $this->payee->user);
        $this->assertThrows(fn () => app(DecideOnEntry::class)->approve($b, $this->payee->user), ValidationException::class);
        $this->assertSame(200_000, $this->snapshot()['obligations'][$debt->id]['amount']);
    }

    public function test_unlinked_historical_refund_is_flagged_without_guessing_a_source_or_rewriting_rows(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000);
        $old = Transaction::query()->create(['ledger_id' => $this->book->id, 'type' => TransactionType::Refund, 'status' => TransactionStatus::Confirmed, 'amount' => 200_000, 'occurred_on' => '2026-10-29', 'from_party_id' => $this->to->id, 'to_party_id' => $this->from->id, 'recorded_by_actor_id' => $this->payee->id]);
        $original = $old->fresh()->getAttributes();
        $s = app(CreditAccounting::class)->snapshot($this->book);
        $this->assertSame($old->uuid, $s['issues'][0]['entry']);
        $this->assertSame($original, $old->fresh()->getAttributes());
        $this->assertThrows(fn () => $this->refund($payment, 100_000), ValidationException::class);
    }

    public function test_credit_history_and_source_controls_render_for_both_accounts_in_persian_and_english(): void
    {
        $this->entry(TransactionType::Payment, 1_000_000);
        foreach (['en', 'fa'] as $locale) {
            app()->setLocale($locale);
            foreach ([$this->payer, $this->payee] as $person) {
                Livewire::actingAs($person->user)->test(BookShow::class, ['ledger' => $this->book])
                    ->assertSee(__('credit.not_debt'))->assertSeeHtml('data-credit-history')
                    ->call('openRecord', 'refund')->assertSee(__('credit.source'))->assertSee(__('credit.return_mode'));
            }
        }
    }

    public function test_mysql_enforces_idempotency_column_length_and_unique_request_keys(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL column constraints are verified by the disposable MySQL harness.');
        }
        $payment = $this->entry(TransactionType::Payment, 1_000_000, key: 'constraint-key');
        $attributes = $payment->getAttributes();
        unset($attributes['id']);
        $attributes['uuid'] = (string) Str::uuid();
        $this->assertThrows(fn () => DB::table('bk_transactions')->insert($attributes), QueryException::class);
        $attributes['idempotency_key'] = str_repeat('x', 81);
        $this->assertThrows(fn () => DB::table('bk_transactions')->insert($attributes), QueryException::class);
        $this->snapshot();
    }

    public function test_noncash_settlement_reduces_debt_without_inventing_received_money_or_advance_credit(): void
    {
        $debt = $this->entry(TransactionType::Obligation, 1_000_000);
        $cash = $this->entry(TransactionType::Payment, 600_000);
        $writeOff = app(RecordSharedEntry::class)->execute($this->book, $this->payee->user, TransactionType::WriteOff, $this->from, $this->to, 400_000, '2026-10-29', 'Agreed waiver');
        app(DecideOnEntry::class)->approve($writeOff, $this->payer->user);
        $s = $this->snapshot();
        $this->assertSame(600_000, $s['obligations'][$debt->id]['cash_paid']);
        $this->assertSame(1_000_000, $s['obligations'][$debt->id]['paid']);
        $this->assertSame(0, $s['payments'][$cash->id]['available']);
    }

    public function test_an_unused_credit_return_also_works_in_the_opposite_direction(): void
    {
        $payment = $this->entry(TransactionType::Payment, 1_000_000, reverse: true);
        $return = app(RecordSharedEntry::class)->execute($this->book, $this->payer->user, TransactionType::Refund, $this->from, $this->to, 300_000, '2026-10-29', 'Return reverse advance', refundPayment: $payment);
        app(DecideOnEntry::class)->approve($return, $this->payee->user);
        $s = $this->snapshot();
        $this->assertSame(700_000, $s['payments'][$payment->id]['available']);
        $this->assertSame(0, app(BalanceCalculator::class)->forParty($this->book, $this->from)['i_owe']);
    }
}
