<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\JournalEntryKind;
use App\Livewire\Accounting\Index as AccountingIndex;
use App\Models\Actor;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Support\AccountingSummary;
use App\Support\ContextTimeline;
use App\Support\MoneyAmount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bob_tracks_everyday_money_without_using_debit_credit_terminology(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 10:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $bob->user->forceFill([
                'timezone' => 'Europe/Berlin',
                'locale' => 'en',
            ])->save();

            $component = Livewire::actingAs($bob->user)
                ->test(AccountingIndex::class)
                ->assertSee(__('accounting.create_ledger'))
                ->set('unitCode', 'EUR')
                ->set('ledgerName', 'Bob personal EUR')
                ->call('createLedger')
                ->assertHasNoErrors()
                ->assertSee('Bob personal EUR')
                ->assertDontSee('Debit')
                ->assertDontSee('Credit');

            $ledger = Ledger::query()->with(['accounts', 'monetaryUnit'])->sole();
            $cash = $ledger->accounts->firstWhere('system_key', 'cash');
            $this->assertNotNull($cash);

            $component
                ->set('action', 'opening_balance')
                ->set('accountUuid', $cash->uuid)
                ->set('amount', '1000.00')
                ->set('date', '2026-09-01')
                ->set('description', 'Starting cash')
                ->call('record')
                ->assertHasNoErrors();

            $component
                ->set('action', 'expense')
                ->set('accountUuid', $cash->uuid)
                ->set('amount', '25.00')
                ->set('date', '2026-09-02')
                ->set('category', 'Work gloves')
                ->set('description', 'Gloves for Riverside work')
                ->call('record')
                ->assertHasNoErrors();

            $component
                ->set('action', 'income')
                ->set('accountUuid', $cash->uuid)
                ->set('amount', '100.00')
                ->set('date', '2026-09-03')
                ->set('category', 'Service income')
                ->set('description', 'Small service job')
                ->call('record')
                ->assertHasNoErrors();

            $component
                ->set('periodMode', 'month')
                ->set('periodDate', '2026-09-15')
                ->assertSee('€1075.00')
                ->assertSee('€100.00')
                ->assertSee('€25.00')
                ->assertSee('€75.00')
                ->assertDontSee('Debit')
                ->assertDontSee('Credit');

            $component
                ->set('newAccountName', 'Bank')
                ->call('addAccount')
                ->assertHasNoErrors()
                ->assertSee('Bank');

            $ledger->refresh()->load('accounts');
            $bank = $ledger->accounts->firstWhere('name', 'Bank');
            $this->assertNotNull($bank);

            $component
                ->set('action', 'transfer')
                ->set('accountUuid', $cash->uuid)
                ->set('toAccountUuid', $bank->uuid)
                ->set('amount', '200.00')
                ->set('date', '2026-09-04')
                ->set('description', 'Move cash to bank')
                ->call('record')
                ->assertHasNoErrors()
                ->assertSee('€875.00')
                ->assertSee('€200.00')
                ->assertSee('€1075.00');

            $summary = app(AccountingSummary::class);
            $this->assertSame(87500, $summary->accountBalanceMinor($cash->fresh()));
            $this->assertSame(20000, $summary->accountBalanceMinor($bank->fresh()));

            $component
                ->set('periodMode', 'day')
                ->set('periodDate', '2026-09-02')
                ->assertSee('€0.00')
                ->assertSee('€25.00')
                ->assertSee('€-25.00');

            $component
                ->set('periodMode', 'year')
                ->set('periodDate', '2026-09-24')
                ->assertSee('€100.00')
                ->assertSee('€25.00')
                ->assertSee('€75.00');

            $expense = JournalEntry::query()
                ->where('kind', JournalEntryKind::Expense->value)
                ->sole();

            $component
                ->call('reverseEntry', $expense->id)
                ->assertHasNoErrors()
                ->assertSee(__('accounting.reversed'))
                ->assertSee('€1100.00');

            $this->assertDatabaseHas('journal_entries', [
                'reverses_entry_id' => $expense->id,
                'kind' => JournalEntryKind::Reversal->value,
            ]);
            $this->assertSame('Gloves for Riverside work', $expense->fresh()->description);

            $month = $summary->period($ledger, '2026-09-01', '2026-09-30');
            $this->assertSame(10000, $month['income_minor']);
            $this->assertSame(0, $month['expense_minor']);
            $this->assertSame(10000, $month['net_minor']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_accounting_activity_projects_into_personal_context_timeline(): void
    {
        $bob = Actor::factory()->create();

        Livewire::actingAs($bob->user)
            ->test(AccountingIndex::class)
            ->set('unitCode', 'EUR')
            ->call('createLedger')
            ->set('action', 'opening_balance')
            ->set('amount', '500.00')
            ->set('date', '2026-09-24')
            ->call('record')
            ->assertHasNoErrors();

        $ledger = Ledger::query()->sole();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);

        $entries = app(ContextTimeline::class)->entries($context, $bob->user);

        $this->assertTrue($entries->contains(fn ($entry): bool => $entry->kind === 'accounting'));
        $this->assertTrue($entries->contains(
            fn ($entry): bool => str_contains(
                $entry->url,
                route('accounting.index', ['ledger' => $ledger->uuid]),
            ),
        ));
    }

    public function test_another_actor_cannot_select_bobs_personal_ledger(): void
    {
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        Livewire::actingAs($bob->user)
            ->test(AccountingIndex::class)
            ->set('unitCode', 'EUR')
            ->call('createLedger');

        $ledger = Ledger::query()->sole();

        $this->actingAs($carol->user)
            ->get(route('accounting.index', ['ledger' => $ledger->uuid]))
            ->assertNotFound();
    }

    public function test_money_display_round_trips_exact_minor_units(): void
    {
        $this->assertSame(107500, MoneyAmount::parse('1075.00', 2));
        $this->assertSame('1075.00', MoneyAmount::format(107500, 2));
        $this->assertSame('-25.00', MoneyAmount::format(-2500, 2));
    }
}
