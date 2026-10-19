<?php

namespace App\Support\Bookkeeping;

use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A person's money at a glance, across all their books: what others owe
 * them, what they owe, what waits for their confirmation, and today's
 * personal income and spending.
 */
final class BookkeepingOverview
{
    public function __construct(
        private readonly BalanceCalculator $balances,
        private readonly ConfirmationRules $rules,
    ) {}

    /**
     * @return array{owed_to_me: int, i_owe: int, waiting_for_me: Collection<int, Transaction>, today_income: int, today_expense: int, has_books: bool}
     */
    public function forUser(User $user, ?string $today = null): array
    {
        $actor = User::query()->with('actor')->find($user->id)?->actor;
        $empty = ['owed_to_me' => 0, 'i_owe' => 0, 'waiting_for_me' => collect(), 'today_income' => 0, 'today_expense' => 0, 'has_books' => false];

        if (! $actor instanceof Actor) {
            return $empty;
        }

        $parties = LedgerParty::query()
            ->where('actor_id', $actor->id)
            ->where('status', PartyStatus::Active->value)
            ->whereHas('ledger', fn ($query) => $query->where('status', LedgerStatus::Active->value))
            ->with('ledger')
            ->limit(200)
            ->get();

        $owedToMe = 0;
        $iOwe = 0;
        $personal = null;
        foreach ($parties as $party) {
            if ($party->ledger->kind === LedgerKind::Personal) {
                $personal = $party->ledger;

                continue;
            }
            $summary = $this->balances->forParty($party->ledger, $party);
            $owedToMe += $summary['owed_to_me'];
            $iOwe += $summary['i_owe'];
        }

        $sharedIds = $parties->reject(fn (LedgerParty $party): bool => $party->ledger->kind === LedgerKind::Personal)->pluck('ledger_id');
        $waiting = $sharedIds->isEmpty() ? collect() : Transaction::query()
            ->whereIn('ledger_id', $sharedIds)
            ->where('status', TransactionStatus::Pending->value)
            ->where('recorded_by_actor_id', '!=', $actor->id)
            ->with(['ledger', 'fromParty.actor.user', 'toParty.actor.user', 'recorder.user'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->rules->awaiting($transaction)
                ->contains(fn (LedgerParty $party): bool => (int) $party->actor_id === (int) $actor->id))
            ->values();

        $todayIncome = 0;
        $todayExpense = 0;
        if ($personal instanceof Ledger && $today !== null) {
            $day = Carbon::parse($today);
            $period = $this->balances->period($personal, $day, $day);
            $todayIncome = $period['income'];
            $todayExpense = $period['expense'];
        }

        return [
            'owed_to_me' => $owedToMe,
            'i_owe' => $iOwe,
            'waiting_for_me' => $waiting,
            'today_income' => $todayIncome,
            'today_expense' => $todayExpense,
            'has_books' => $parties->isNotEmpty(),
        ];
    }
}
