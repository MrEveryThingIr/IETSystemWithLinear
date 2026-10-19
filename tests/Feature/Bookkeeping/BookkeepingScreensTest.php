<?php

namespace Tests\Feature\Bookkeeping;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Livewire\Bookkeeping\BookShow;
use App\Livewire\Bookkeeping\Index;
use App\Livewire\Bookkeeping\PersonalBook;
use App\Livewire\Home\Today;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BookkeepingScreensTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_bookkeeping_home_opens_a_book_and_needs_the_published_facility(): void
    {
        $me = Actor::factory()->create();

        $this->actingAs($me->user)->get(route('bookkeeping.index'))->assertForbidden();
        $this->publishSurfaces($me->user, ['money']);
        $this->actingAs($me->user)->get(route('bookkeeping.index'))
            ->assertOk()
            ->assertSee('data-bookkeeping-index', false)
            ->assertSee('data-personal-book-card', false);

        Livewire::actingAs($me->user)
            ->test(Index::class)
            ->call('openCreate', 'project')
            ->set('newName', 'Golestan project')
            ->call('createBook')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookkeeping.books.show', Ledger::query()->where('name', 'Golestan project')->sole()));
    }

    public function test_personal_book_records_spending_with_a_receipt_and_undoes_it(): void
    {
        Storage::fake('local');
        $me = Actor::factory()->create();
        $this->publishSurfaces($me->user, ['money']);

        $page = Livewire::actingAs($me->user)
            ->test(PersonalBook::class)
            ->assertSeeHtml('data-personal-form')
            ->set('amount', '۳۵۰٬۰۰۰')
            ->assertSee('350,000')
            ->set('counterpartyName', 'Bakery')
            ->set('receipt', UploadedFile::fake()->image('receipt.jpg', 30, 30))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Bakery');

        $entry = Transaction::query()->where('type', TransactionType::Expense->value)->sole();
        $this->assertSame(350_000, (int) $entry->amount);
        $this->assertSame(1, $entry->assets()->count());
        $page->assertSee('-350,000');

        // A double click with the same form does not record twice.
        $page->set('amount', '1000')->call('save')->assertHasNoErrors();
        $this->assertSame(2, Transaction::query()->count());

        $page->call('undo', $entry->uuid)->assertSeeHtml('data-undo');
        $this->assertSame(1, Transaction::query()->where('related_transaction_id', $entry->id)->count());

        $page->set('amount', 'abc')->call('save')->assertHasErrors('amount');
    }

    public function test_shared_book_screen_records_a_payment_that_the_other_side_confirms(): void
    {
        Storage::fake('local');
        $father = Actor::factory()->create();
        $majid = Actor::factory()->create();
        $book = app(CreateLedger::class)->execute($father->user, LedgerKind::Shared, 'Golestan');
        $majidParty = app(AddLedgerParty::class)->execute($book, $father->user, $majid->user->username, null, PartyRole::Member);
        $debt = app(RecordSharedEntry::class)->execute($book, $father->user, TransactionType::Obligation, $majidParty, $book->partyFor($father), 40_000_000, '2026-09-01', 'Golestan work');

        // Majid has not been given the Bookkeeping facility, but can open a book he is in.
        $this->actingAs($majid->user)->get(route('bookkeeping.books.show', $book))->assertOk()->assertSee('data-book-waiting', false);

        Livewire::actingAs($majid->user)
            ->test(BookShow::class, ['ledger' => $book])
            ->call('approve', $debt->uuid)
            ->call('openRecord', 'payment')
            ->assertSet('fromParty', $majidParty->uuid)
            ->assertSet('toParty', $book->partyFor($father)->uuid)
            ->set('amount', '10,000,000')
            ->set('description', 'SMS sent')
            ->set('evidence', UploadedFile::fake()->image('sms.png', 30, 60))
            ->call('record')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-entry="pending"');

        $payment = Transaction::query()->where('type', TransactionType::Payment->value)->sole();
        $asset = $payment->assets()->sole();

        Livewire::actingAs($father->user)
            ->test(BookShow::class, ['ledger' => $book])
            ->assertSeeHtml('data-book-waiting')
            ->assertSee('10,000,000')
            ->call('approve', $payment->uuid)
            ->assertSeeHtml('data-debt="partially_paid"')
            ->assertSee('30,000,000');
        $this->assertSame(TransactionStatus::Confirmed, $payment->fresh()->status);

        // Evidence is served to the book's people only.
        $this->actingAs($father->user)->get(route('bookkeeping.books.evidence', [$book, $payment, $asset]))->assertOk();
        $stranger = Actor::factory()->create();
        $this->actingAs($stranger->user)->get(route('bookkeeping.books.evidence', [$book, $payment, $asset]))->assertForbidden();
        $this->actingAs($stranger->user)->get(route('bookkeeping.books.show', $book))->assertForbidden();

        // People drawer: add a worker without an account.
        Livewire::actingAs($father->user)
            ->test(BookShow::class, ['ledger' => $book])
            ->set('partyName', 'Ali')
            ->call('addParty')
            ->assertHasNoErrors()
            ->assertSee('Ali');
    }

    public function test_today_shows_money_and_entries_waiting_for_confirmation(): void
    {
        $father = Actor::factory()->create();
        $majid = Actor::factory()->create();
        $this->publishSurfaces($father->user, ['money']);
        $book = app(CreateLedger::class)->execute($majid->user, LedgerKind::Shared, 'Golestan');
        $fatherParty = app(AddLedgerParty::class)->execute($book, $majid->user, $father->user->username, null, PartyRole::Member);
        app(RecordSharedEntry::class)->execute($book, $majid->user, TransactionType::Payment, $book->partyFor($majid), $fatherParty, 10_000_000, '2026-10-10');

        Livewire::actingAs($father->user)
            ->test(Today::class)
            ->assertSeeHtml('data-today-money')
            ->assertSee(__('bookkeeping.today.waiting_title', ['who' => $majid->user->username, 'type' => __('bookkeeping.types.payment'), 'amount' => '10,000,000']));
    }
}
