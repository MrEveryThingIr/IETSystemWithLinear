<?php

namespace Tests\Feature\Bookkeeping;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\AttachEvidence;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Actor;
use App\Models\Bookkeeping\AuditLog;
use App\Models\Bookkeeping\Transaction;
use App\Models\NotificationOutbox;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\BookkeepingOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The reference story: a father works for Majid on the Golestan project.
 * 40,000,000 toman is owed, 10,000,000 was paid. Last week Majid paid another
 * 10,000,000 and sent an SMS, but the father forgot. Every balance is checked
 * at every step.
 */
class ReferenceScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_golestan_debt_partial_payments_and_late_confirmation(): void
    {
        Storage::fake('local');
        $father = Actor::factory()->create();
        $majid = Actor::factory()->create();
        $calculator = app(BalanceCalculator::class);

        // The father opens a shared book and adds Majid.
        $book = app(CreateLedger::class)->execute($father->user, LedgerKind::Shared, 'Golestan project');
        $fatherParty = $book->partyFor($father);
        $majidParty = app(AddLedgerParty::class)->execute($book, $father->user, $majid->user->username, null, PartyRole::Member);

        // 1. The father records the 40M debt; Majid confirms it.
        $debt = app(RecordSharedEntry::class)->execute(
            $book, $father->user, TransactionType::Obligation, $majidParty, $fatherParty,
            '40,000,000', '2026-09-01', 'Golestan project work', '2026-10-30',
        );
        $this->assertSame(TransactionStatus::Pending, $debt->status);
        $this->assertSame(0, $calculator->forParty($book, $fatherParty)['owed_to_me']);
        $this->assertSame(40_000_000, $calculator->forParty($book, $fatherParty)['pending_change']);

        app(DecideOnEntry::class)->approve($debt, $majid->user);
        $this->assertSame(40_000_000, $calculator->forParty($book, $fatherParty)['owed_to_me']);
        $this->assertSame(40_000_000, $calculator->forParty($book, $majidParty)['i_owe']);

        // 2. Majid records the first 10M payment; the father confirms it.
        $first = app(RecordSharedEntry::class)->execute(
            $book, $majid->user, TransactionType::Payment, $majidParty, $fatherParty,
            '۱۰٬۰۰۰٬۰۰۰', '2026-09-15', 'First payment', idempotencyKey: 'pay-1',
        );
        $this->assertSame(10_000_000, (int) $first->allocations()->sole()->amount);
        app(DecideOnEntry::class)->approve($first, $father->user);
        $this->assertSame(30_000_000, $calculator->forParty($book, $fatherParty)['owed_to_me']);
        $this->assertSame('partially_paid', $calculator->obligation($debt->fresh())['state']);

        // 3. Last week Majid paid another 10M and attached the SMS screenshot.
        $second = app(RecordSharedEntry::class)->execute(
            $book, $majid->user, TransactionType::Payment, $majidParty, $fatherParty,
            10_000_000, '2026-10-10', 'Second payment (SMS sent)', idempotencyKey: 'pay-2',
        );
        app(AttachEvidence::class)->execute($second, $majid->user, UploadedFile::fake()->image('sms.png', 40, 80));
        $this->assertSame(1, $second->assets()->count());

        // Submitting the same payment again does not record it twice.
        $again = app(RecordSharedEntry::class)->execute(
            $book, $majid->user, TransactionType::Payment, $majidParty, $fatherParty,
            10_000_000, '2026-10-10', 'Second payment (SMS sent)', idempotencyKey: 'pay-2',
        );
        $this->assertSame($second->id, $again->id);
        $this->assertSame(2, Transaction::query()->where('type', TransactionType::Payment->value)->count());

        // The father sees it waiting for him and a notification about it.
        $overview = app(BookkeepingOverview::class)->forUser($father->user);
        $this->assertTrue($overview['waiting_for_me']->contains('id', $second->id));
        $this->assertSame(30_000_000, $overview['owed_to_me']);
        $this->assertTrue(NotificationOutbox::query()
            ->where('recipient_user_id', $father->user->id)
            ->where('subject_uuid', $second->uuid)
            ->exists());
        $fatherView = $calculator->forParty($book, $fatherParty);
        $this->assertSame(30_000_000, $fatherView['owed_to_me']);
        $this->assertSame(-10_000_000, $fatherView['pending_change']);

        $progress = $calculator->obligation($debt->fresh());
        $this->assertSame(['amount' => 40_000_000, 'paid' => 10_000_000, 'pending' => 10_000_000, 'disputed' => 0, 'outstanding' => 30_000_000, 'state' => 'partially_paid'], $progress);

        // 4. The father confirms: 20M left.
        app(DecideOnEntry::class)->approve($second, $father->user);
        $this->assertSame(20_000_000, $calculator->forParty($book, $fatherParty)['owed_to_me']);
        $this->assertSame(20_000_000, $calculator->forParty($book, $majidParty)['i_owe']);
        $this->assertSame(20_000_000, $calculator->obligation($debt->fresh())['outstanding']);
        $pairs = $calculator->pairs($book);
        $this->assertCount(1, $pairs);
        $this->assertSame($majidParty->id, $pairs[0]['debtor']->id);
        $this->assertSame(20_000_000, $pairs[0]['confirmed']);

        // The activity trail tells the story in order.
        $actions = AuditLog::query()->where('ledger_id', $book->id)->orderBy('id')->where('action', '!=', 'credit.projected')->pluck('action')->all();
        $this->assertSame([
            'ledger.created', 'party.added',
            'entry.recorded', 'entry.confirmed',
            'entry.recorded', 'entry.confirmed',
            'entry.recorded', 'evidence.added', 'entry.confirmed',
        ], $actions);
    }
}
