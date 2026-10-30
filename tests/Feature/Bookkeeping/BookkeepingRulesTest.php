<?php

namespace Tests\Feature\Bookkeeping;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Actions\Bookkeeping\ManageWallets;
use App\Actions\Bookkeeping\RaiseDispute;
use App\Actions\Bookkeeping\RecordAdjustment;
use App\Actions\Bookkeeping\RecordPersonalEntry;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Actions\Bookkeeping\RemoveLedgerParty;
use App\Actions\Bookkeeping\ResolveDispute;
use App\Actions\Bookkeeping\WithdrawEntry;
use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\DisputeOutcome;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Enums\Bookkeeping\WalletKind;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Support\Bookkeeping\BalanceCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookkeepingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dispute_moves_a_payment_out_of_the_confirmed_balance_until_resolved(): void
    {
        [$book, $owner, $worker, $ownerParty, $workerParty] = $this->book();
        $calc = app(BalanceCalculator::class);

        $debt = $this->confirmed($book, $owner, $worker, TransactionType::Obligation, $workerParty, $ownerParty, 5_000_000);
        $payment = $this->confirmed($book, $worker, $owner, TransactionType::Payment, $workerParty, $ownerParty, 2_000_000);
        $this->assertSame(3_000_000, $calc->forParty($book, $ownerParty)['owed_to_me']);

        $dispute = app(RaiseDispute::class)->execute($payment, $owner->user, 'This money never arrived.');
        $this->assertSame(TransactionStatus::Disputed, $payment->fresh()->status);
        $this->assertSame(5_000_000, $calc->forParty($book, $ownerParty)['owed_to_me']);
        $this->assertSame(2_000_000, $calc->forParty($book, $ownerParty)['disputed']);

        // The one who objected cannot void it alone; the other side can.
        $this->expectDenied(fn () => app(ResolveDispute::class)->execute($dispute, $owner->user, DisputeOutcome::Voided));
        app(ResolveDispute::class)->execute($dispute, $worker->user, DisputeOutcome::Voided, 'My mistake, wrong card.');
        $this->assertSame(TransactionStatus::Void, $payment->fresh()->status);
        $this->assertSame(5_000_000, $calc->forParty($book, $ownerParty)['owed_to_me']);
        $this->assertSame(0, $calc->forParty($book, $ownerParty)['disputed']);
        $this->assertSame('unpaid', $calc->obligation($debt->fresh())['state']);
    }

    public function test_corrections_are_new_entries_never_edits(): void
    {
        [$book, $owner, $worker, $ownerParty, $workerParty] = $this->book();
        $calc = app(BalanceCalculator::class);

        $debt = $this->confirmed($book, $owner, $worker, TransactionType::Obligation, $workerParty, $ownerParty, 1_000_000);

        $this->assertThrows(fn () => $debt->fresh()->forceFill(['amount' => 5])->save(), LogicException::class);
        $this->assertThrows(fn () => $debt->fresh()->delete(), LogicException::class);
        $this->assertThrows(fn () => $debt->fresh()->transitionTo(TransactionStatus::Pending), LogicException::class);

        $adjustment = app(RecordAdjustment::class)->execute($debt, $owner->user, AdjustmentDirection::Increase, '250,000', 'Two extra hours');
        $this->assertSame(1_000_000, $calc->obligation($debt->fresh())['amount']);
        app(DecideOnEntry::class)->approve($adjustment, $worker->user);
        $this->assertSame(1_250_000, $calc->obligation($debt->fresh())['amount']);
        $this->assertSame(1_250_000, $calc->forParty($book, $ownerParty)['owed_to_me']);

        // Refund: the creditor returns part of an overpayment.
        $overpayment = $this->confirmed($book, $worker, $owner, TransactionType::Payment, $workerParty, $ownerParty, 1_500_000);
        $this->assertSame(0, $calc->forParty($book, $ownerParty)['owed_to_me']);
        $this->assertSame(0, $calc->forParty($book, $workerParty)['owed_to_me']);
        $this->assertSame(250_000, $calc->forParty($book, $workerParty)['advance_paid']);
        $refund = app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Refund, $ownerParty, $workerParty, 250_000, '2026-10-01', 'Return unused overpayment', refundPayment: $overpayment);
        app(DecideOnEntry::class)->approve($refund, $worker->user);
        $this->assertSame(0, $calc->forParty($book, $workerParty)['owed_to_me']);
        $this->assertSame(0, $calc->forParty($book, $ownerParty)['owed_to_me']);
    }

    public function test_write_off_and_settlement_in_kind_reduce_the_debt(): void
    {
        [$book, $owner, $worker, $ownerParty, $workerParty] = $this->book();
        $calc = app(BalanceCalculator::class);

        $debt = $this->confirmed($book, $owner, $worker, TransactionType::Obligation, $workerParty, $ownerParty, 3_000_000);
        $this->confirmed($book, $worker, $owner, TransactionType::SettlementInKind, $workerParty, $ownerParty, 2_000_000, 'Two days of tiling');
        $this->confirmed($book, $owner, $worker, TransactionType::WriteOff, $workerParty, $ownerParty, 1_000_000, 'Forgiven');

        $this->assertSame(0, $calc->forParty($book, $ownerParty)['owed_to_me']);
        $this->assertSame('paid', $calc->obligation($debt->fresh())['state']);
    }

    public function test_rejection_and_withdrawal_end_an_entry_without_counting(): void
    {
        [$book, $owner, $worker, $ownerParty, $workerParty] = $this->book();
        $calc = app(BalanceCalculator::class);

        $claim = app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Obligation, $workerParty, $ownerParty, 900_000, '2026-10-01');
        $this->expectDenied(fn () => app(DecideOnEntry::class)->approve($claim, $owner->user));
        app(DecideOnEntry::class)->reject($claim, $worker->user, 'I never borrowed this.');
        $this->assertSame(TransactionStatus::Rejected, $claim->fresh()->status);
        $this->assertSame(0, $calc->forParty($book, $ownerParty)['owed_to_me']);
        $this->assertSame(0, $calc->forParty($book, $ownerParty)['pending_change']);

        $draft = app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Obligation, $workerParty, $ownerParty, 100_000, '2026-10-01');
        $this->expectDenied(fn () => app(WithdrawEntry::class)->execute($draft, $worker->user));
        app(WithdrawEntry::class)->execute($draft, $owner->user);
        $this->assertSame(TransactionStatus::Void, $draft->fresh()->status);
    }

    public function test_name_only_people_are_recorded_by_one_side_and_count_at_once(): void
    {
        [$book, $owner, , $ownerParty] = $this->book(LedgerKind::Project);
        $ali = app(AddLedgerParty::class)->execute($book, $owner->user, null, 'Ali (tiler)', PartyRole::Member, '0912 *** 4567');
        $this->assertNull($ali->actor->user);

        $paid = app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Payment, $ownerParty, $ali, 2_000_000, '2026-10-05', 'Daily wage');
        $this->assertSame(TransactionStatus::Confirmed, $paid->status);
        $this->assertSame(0, app(BalanceCalculator::class)->forParty($book, $ownerParty)['owed_to_me']);
        $this->assertSame(2_000_000, app(BalanceCalculator::class)->forParty($book, $ownerParty)['advance_paid']);
    }

    public function test_privacy_roles_and_validation(): void
    {
        [$book, $owner, $worker, $ownerParty, $workerParty] = $this->book(LedgerKind::Project);
        $stranger = Actor::factory()->create();
        $viewer = Actor::factory()->create();
        $other = Actor::factory()->create();
        $viewerParty = app(AddLedgerParty::class)->execute($book, $owner->user, $viewer->user->username, null, PartyRole::Viewer);
        $otherParty = app(AddLedgerParty::class)->execute($book, $owner->user, $other->user->username, null, PartyRole::Member);

        $this->assertFalse(Gate::forUser($stranger->user)->allows('view', $book));
        $this->assertTrue(Gate::forUser($viewer->user)->allows('view', $book));
        $this->assertFalse(Gate::forUser($viewer->user)->allows('record', $book));
        $this->assertFalse(Gate::forUser($worker->user)->allows('manage', $book));
        $this->actingAs($stranger->user)->get(route('bookkeeping.books.show', $book))->assertForbidden();

        // A member records only what they are part of.
        $this->assertThrows(
            fn () => app(RecordSharedEntry::class)->execute($book, $worker->user, TransactionType::Obligation, $otherParty, $ownerParty, 1000, '2026-10-01'),
            ValidationException::class,
        );
        $this->expectDenied(fn () => app(RecordSharedEntry::class)->execute($book, $viewer->user, TransactionType::Payment, $viewerParty, $ownerParty, 1000, '2026-10-01'));
        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Payment, $ownerParty, $ownerParty, 1000, '2026-10-01'), ValidationException::class);
        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Payment, $workerParty, $ownerParty, '-5', '2026-10-01'), ValidationException::class);
        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Payment, $workerParty, $ownerParty, '12.5', '2026-10-01'), ValidationException::class);
        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Payment, $workerParty, $ownerParty, 10, '2026-02-30'), ValidationException::class);

        // When a manager records for two others, both must confirm.
        $entry = app(RecordSharedEntry::class)->execute($book, $owner->user, TransactionType::Obligation, $otherParty, $workerParty, 7000, '2026-10-01');
        app(DecideOnEntry::class)->approve($entry, $worker->user);
        $this->assertSame(TransactionStatus::Pending, $entry->fresh()->status);
        app(DecideOnEntry::class)->approve($entry, $other->user);
        $this->assertSame(TransactionStatus::Confirmed, $entry->fresh()->status);

        // Someone who left no longer sees the book; their history stays.
        app(RemoveLedgerParty::class)->execute($otherParty, $other->user);
        $this->assertFalse(Gate::forUser($other->user)->allows('view', $book->fresh()));
        $this->assertSame(TransactionStatus::Confirmed, $entry->fresh()->status);
    }

    public function test_payments_can_be_split_across_debts_and_overpayment_is_refused_per_debt(): void
    {
        [$book, $owner, $worker, $ownerParty, $workerParty] = $this->book();
        $calc = app(BalanceCalculator::class);

        $a = $this->confirmed($book, $owner, $worker, TransactionType::Obligation, $workerParty, $ownerParty, 1_000_000, 'Week 1');
        $b = $this->confirmed($book, $owner, $worker, TransactionType::Obligation, $workerParty, $ownerParty, 2_000_000, 'Week 2');

        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute(
            $book, $worker->user, TransactionType::Payment, $workerParty, $ownerParty, 1_500_000, '2026-10-02',
            allocations: [$a->uuid => 1_200_000],
        ), ValidationException::class);

        $pay = app(RecordSharedEntry::class)->execute(
            $book, $worker->user, TransactionType::Payment, $workerParty, $ownerParty, 1_500_000, '2026-10-02',
            allocations: [$a->uuid => 500_000, $b->uuid => '1,000,000'],
        );
        app(DecideOnEntry::class)->approve($pay, $owner->user);

        $this->assertSame(500_000, $calc->obligation($a->fresh())['outstanding']);
        $this->assertSame(1_000_000, $calc->obligation($b->fresh())['outstanding']);
        $this->assertSame(1_500_000, $calc->forParty($book, $ownerParty)['owed_to_me']);
    }

    public function test_personal_book_wallets_categories_transfers_and_undo(): void
    {
        $me = Actor::factory()->create();
        $book = app(EnsurePersonalLedger::class)->execute($me->user);
        $this->assertSame($book->id, app(EnsurePersonalLedger::class)->execute($me->user)->id);
        $this->assertFalse(Gate::forUser(Actor::factory()->create()->user)->allows('view', $book));

        $cash = $book->wallets()->sole();
        $card = app(ManageWallets::class)->addWallet($book, $me->user, 'Melli card', WalletKind::Card, '5,000,000', '6037 9911 2233 4455');
        $this->assertSame('•••• 4455', $card->masked_identifier);
        $food = $book->categories()->where('system_key', 'food')->sole();
        $salary = $book->categories()->where('system_key', 'salary')->sole();
        $record = app(RecordPersonalEntry::class);

        $record->execute($book, $me->user, TransactionType::Income, '20,000,000', '2026-10-01', $card, $salary, counterpartyName: 'Employer');
        $lunch = $record->execute($book, $me->user, TransactionType::Expense, 350_000, '2026-10-02', $cash, $food, counterpartyName: 'Bakery');
        $record->execute($book, $me->user, TransactionType::Transfer, 2_000_000, '2026-10-02', $card, toWallet: $cash);

        $balances = app(BalanceCalculator::class)->wallets($book)->mapWithKeys(fn (array $row): array => [$row['wallet']->id => $row['balance']]);
        $this->assertSame(1_650_000, $balances[$cash->id]);
        $this->assertSame(23_000_000, $balances[$card->id]);

        $period = app(BalanceCalculator::class)->period($book, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertSame(20_000_000, $period['income']);
        $this->assertSame(350_000, $period['expense']);

        $record->undo($lunch, $me->user);
        $this->assertThrows(fn () => $record->undo($lunch->fresh(), $me->user));
        $balances = app(BalanceCalculator::class)->wallets($book)->mapWithKeys(fn (array $row): array => [$row['wallet']->id => $row['balance']]);
        $this->assertSame(2_000_000, $balances[$cash->id]);

        // Paying in a shared book from a personal wallet moves that wallet.
        [$shared, $owner, $worker, $ownerParty, $workerParty] = $this->book();
        $workerBook = app(EnsurePersonalLedger::class)->execute($worker->user);
        $workerCash = $workerBook->wallets()->sole();
        $payment = app(RecordSharedEntry::class)->execute($shared, $worker->user, TransactionType::Payment, $workerParty, $ownerParty, 300_000, '2026-10-03', wallet: $workerCash);
        $this->assertSame(0, app(BalanceCalculator::class)->wallets($workerBook)->first()['balance']);
        app(DecideOnEntry::class)->approve($payment, $owner->user);
        $this->assertSame(-300_000, app(BalanceCalculator::class)->wallets($workerBook)->first()['balance']);

        // Someone else's wallet cannot be used.
        $this->assertThrows(fn () => app(RecordSharedEntry::class)->execute($shared, $owner->user, TransactionType::Payment, $ownerParty, $workerParty, 1, '2026-10-03', wallet: $workerCash));
    }

    /** @return array{Ledger, Actor, Actor, LedgerParty, LedgerParty} */
    private function book(LedgerKind $kind = LedgerKind::Shared): array
    {
        $owner = Actor::factory()->create();
        $worker = Actor::factory()->create();
        $book = app(CreateLedger::class)->execute($owner->user, $kind, 'Site work');
        $workerParty = app(AddLedgerParty::class)->execute($book, $owner->user, $worker->user->username, null, PartyRole::Member);

        return [$book, $owner, $worker, $book->partyFor($owner), $workerParty];
    }

    private function confirmed(Ledger $book, Actor $recorder, Actor $confirmer, TransactionType $type, LedgerParty $from, LedgerParty $to, int $amount, ?string $description = null): Transaction
    {
        $entry = app(RecordSharedEntry::class)->execute($book, $recorder->user, $type, $from, $to, $amount, '2026-10-01', $description);
        app(DecideOnEntry::class)->approve($entry, $confirmer->user);

        return $entry->fresh();
    }

    private function expectDenied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected the action to be denied.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
