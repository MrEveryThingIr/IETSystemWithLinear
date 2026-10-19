<?php

namespace App\Livewire\Bookkeeping;

use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\BookkeepingOverview;
use App\Support\TemporalCalendar;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Bookkeeping home: what others owe me and what I owe across my books,
 * entries waiting for my confirmation, my personal book, and my shared and
 * project books.
 */
#[Layout('layouts.app')]
#[Title('Bookkeeping')]
class Index extends Component
{
    public bool $createOpen = false;

    public string $newKind = 'shared';

    public string $newName = '';

    public string $newDescription = '';

    public function openCreate(string $kind = 'shared'): void
    {
        $this->resetErrorBag();
        $this->newKind = in_array($kind, ['shared', 'project'], true) ? $kind : 'shared';
        $this->reset('newName', 'newDescription');
        $this->createOpen = true;
    }

    public function closeCreate(): void
    {
        $this->createOpen = false;
    }

    public function createBook(CreateLedger $create): void
    {
        $ledger = $create->execute($this->user(), LedgerKind::from($this->newKind === 'project' ? 'project' : 'shared'), $this->newName, $this->newDescription);
        $this->redirectRoute('bookkeeping.books.show', $ledger, navigate: false);
    }

    public function approve(string $uuid, DecideOnEntry $decide): void
    {
        $entry = Transaction::query()->where('uuid', $uuid)->firstOrFail();
        $decide->approve($entry, $this->user());
        session()->flash('status', __('bookkeeping.messages.confirmed'));
    }

    public function render(EnsurePersonalLedger $personal, BalanceCalculator $balances, BookkeepingOverview $overview): View
    {
        $user = $this->user();
        $personalBook = $personal->execute($user);
        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($timezone);
        $summary = $overview->forUser($user, $now->toDateString());

        $monthStart = TemporalCalendar::monthStart($now, $user, $timezone);
        $month = $balances->period($personalBook, $monthStart, TemporalCalendar::nextMonthStart($monthStart, $user, $timezone)->subDay());
        $walletTotal = $balances->wallets($personalBook)->sum('balance');

        $books = LedgerParty::query()
            ->where('actor_id', $user->actor?->id)
            ->where('status', PartyStatus::Active->value)
            ->whereHas('ledger', fn ($query) => $query->where('kind', '!=', LedgerKind::Personal->value))
            ->with(['ledger.activeParties'])
            ->get()
            ->map(function (LedgerParty $party) use ($balances): array {
                $ledger = $party->ledger;

                return [
                    'ledger' => $ledger,
                    'party' => $party,
                    'position' => $balances->forParty($ledger, $party),
                    'people' => $ledger->activeParties->count(),
                    'pending' => Transaction::query()->where('ledger_id', $ledger->id)->where('status', TransactionStatus::Pending->value)->count(),
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => ($a['ledger']->status === LedgerStatus::Archived) <=> ($b['ledger']->status === LedgerStatus::Archived),
                fn (array $a, array $b): int => $b['ledger']->updated_at <=> $a['ledger']->updated_at,
            ])
            ->values();

        return view('livewire.bookkeeping.index', [
            'summary' => $summary,
            'personalBook' => $personalBook,
            'month' => $month,
            'walletTotal' => $walletTotal,
            'books' => $books,
        ])->title(__('bookkeeping.title'));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        return $user;
    }
}
