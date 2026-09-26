<?php

namespace App\Livewire\Accounting;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\CreatePersonalLedger;
use App\Actions\Accounting\RecordExpense;
use App\Actions\Accounting\RecordIncome;
use App\Actions\Accounting\RecordOpeningBalance;
use App\Actions\Accounting\RecordTransfer;
use App\Actions\Accounting\ReverseJournalEntry;
use App\Actions\Contexts\EnsurePersonalContext;
use App\JournalEntryKind;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Ledger;
use App\Models\User;
use App\Support\AccountingSummary;
use App\Support\MonetaryUnitCatalog;
use App\Support\MoneyAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Accounting')]
class Index extends Component
{
    #[Url(as: 'ledger')]
    public string $ledgerUuid = '';

    public string $unitCode = 'EUR';

    public string $ledgerName = '';

    public string $action = 'expense';

    public string $amount = '';

    public string $date = '';

    public string $description = '';

    public string $category = '';

    public string $accountUuid = '';

    public string $toAccountUuid = '';

    public string $newAccountName = '';

    public string $periodMode = 'month';

    public string $periodDate = '';

    public function mount(EnsurePersonalContext $contexts): void
    {
        $user = $this->user();
        $context = $contexts->execute($user);
        $timezone = TemporalPreferences::timezoneFor($user);
        $today = CarbonImmutable::now($timezone)->toDateString();

        $this->date = $today;
        $this->periodDate = $today;
        $this->unitCode = strtoupper((string) $user->default_monetary_unit_code);

        $ledgers = $context->ledgers()->with('monetaryUnit')->orderBy('id')->get();

        if ($this->ledgerUuid !== '') {
            abort_unless($ledgers->contains('uuid', $this->ledgerUuid), 404);
        } elseif ($ledgers->isNotEmpty()) {
            $this->ledgerUuid = (string) $ledgers->first()->uuid;
        }
    }

    public function selectLedger(string $uuid): void
    {
        $context = $this->personalContext();
        abort_unless($context->ledgers()->where('uuid', $uuid)->exists(), 404);

        $this->ledgerUuid = $uuid;
        $this->resetEntryForm();
    }

    public function createLedger(CreatePersonalLedger $create): void
    {
        $this->validate([
            'unitCode' => ['required', 'string', 'max:12'],
            'ledgerName' => ['nullable', 'string', 'max:180'],
        ]);

        $ledger = $create->execute(
            $this->user(),
            $this->unitCode,
            $this->ledgerName !== '' ? $this->ledgerName : null,
        );

        $this->ledgerUuid = $ledger->uuid;
        $this->ledgerName = '';
        $this->accountUuid = (string) $ledger->accounts()->where('system_key', 'cash')->value('uuid');

        session()->flash('status', __('accounting.messages.ledger_ready'));
    }

    public function addAccount(CreateLedgerAccount $create): void
    {
        $this->validate([
            'newAccountName' => ['required', 'string', 'max:180'],
        ]);

        $account = $create->execute(
            $this->ledger(),
            $this->user(),
            $this->newAccountName,
            AccountType::Asset,
        );

        $this->newAccountName = '';
        $this->accountUuid = $account->uuid;

        session()->flash('status', __('accounting.messages.account_ready'));
    }

    public function record(
        RecordOpeningBalance $opening,
        RecordExpense $expense,
        RecordIncome $income,
        RecordTransfer $transfer,
    ): void {
        $this->validate([
            'action' => ['required', 'in:opening_balance,expense,income,transfer'],
            'amount' => ['required', 'string', 'max:40'],
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:180'],
            'accountUuid' => ['required', 'uuid'],
            'toAccountUuid' => ['nullable', 'uuid'],
        ]);

        $ledger = $this->ledger();
        $amountMinor = $this->minorAmount($ledger);
        if ($amountMinor === null) {
            return;
        }

        $account = $this->assetAccount($ledger, $this->accountUuid);
        $description = $this->description !== '' ? $this->description : null;

        match ($this->action) {
            'opening_balance' => $opening->execute(
                $ledger,
                $this->user(),
                $account,
                $amountMinor,
                $this->date,
                $description,
            ),
            'expense' => $expense->execute(
                $ledger,
                $this->user(),
                $account,
                $amountMinor,
                $this->date,
                $this->category !== '' ? $this->category : 'General expense',
                $description,
            ),
            'income' => $income->execute(
                $ledger,
                $this->user(),
                $account,
                $amountMinor,
                $this->date,
                $this->category !== '' ? $this->category : 'General income',
                $description,
            ),
            'transfer' => $transfer->execute(
                $ledger,
                $this->user(),
                $account,
                $this->assetAccount($ledger, $this->toAccountUuid),
                $amountMinor,
                $this->date,
                $description,
            ),
            default => abort(422),
        };

        $this->amount = '';
        $this->description = '';
        $this->category = '';
        $this->toAccountUuid = '';

        session()->flash('status', __('accounting.messages.recorded'));
    }

    public function reverseEntry(int $entryId, ReverseJournalEntry $reverse): void
    {
        $ledger = $this->ledger();
        $entry = $ledger->journalEntries()
            ->with('reversals')
            ->findOrFail($entryId);

        abort_unless($entry->kind !== JournalEntryKind::Reversal, 422);
        abort_if($entry->reversals->isNotEmpty(), 422, 'Entry is already reversed.');

        $reverse->execute(
            $entry,
            $this->user(),
            CarbonImmutable::now(TemporalPreferences::timezoneFor($this->user()))->toDateString(),
        );

        session()->flash('status', __('accounting.messages.reversed'));
    }

    public function render(AccountingSummary $accounting): View
    {
        $context = $this->personalContext();
        $ledgers = $context->ledgers()
            ->with('monetaryUnit')
            ->orderBy('id')
            ->get();

        $ledger = $this->ledgerOrNull();

        $period = ['income_minor' => 0, 'expense_minor' => 0, 'net_minor' => 0];
        $from = $this->periodDate;
        $through = $this->periodDate;
        $balances = [];
        $assetAccounts = collect();
        $entries = collect();

        if ($ledger instanceof Ledger) {
            $ledger->loadMissing(['monetaryUnit', 'accounts']);
            [$from, $through] = $this->periodRange();
            $period = $accounting->period($ledger, $from, $through);
            $balances = $accounting->balances($ledger);

            $assetAccounts = $ledger->accounts
                ->filter(fn (Account $account): bool => $account->type === AccountType::Asset && $account->status === 'active')
                ->values();

            $entries = $ledger->journalEntries()
                ->with(['lines.account', 'creator.user', 'reversals'])
                ->latest('occurred_on')
                ->latest('id')
                ->limit(100)
                ->get();

            if ($this->accountUuid === '' && $assetAccounts->isNotEmpty()) {
                $this->accountUuid = (string) $assetAccounts->first()->uuid;
            }
        }

        return view('livewire.accounting.index', [
            'context' => $context,
            'ledgers' => $ledgers,
            'ledger' => $ledger,
            'period' => $period,
            'periodFrom' => $from,
            'periodThrough' => $through,
            'balances' => $balances,
            'assetAccounts' => $assetAccounts,
            'entries' => $entries,
            'unitCatalog' => MonetaryUnitCatalog::all(),
        ]);
    }

    private function ledger(): Ledger
    {
        $ledger = $this->ledgerOrNull();
        abort_unless($ledger instanceof Ledger, 404);
        Gate::forUser($this->user())->authorize('manage', $ledger);

        return $ledger;
    }

    private function ledgerOrNull(): ?Ledger
    {
        if ($this->ledgerUuid === '') {
            return null;
        }

        $context = $this->personalContext();

        $ledger = $context->ledgers()
            ->with('monetaryUnit')
            ->where('uuid', $this->ledgerUuid)
            ->first();

        abort_unless($ledger instanceof Ledger, 404);
        Gate::forUser($this->user())->authorize('view', $ledger);

        return $ledger;
    }

    private function personalContext(): Context
    {
        $actor = $this->user()->actor;
        abort_unless($actor instanceof Actor, 403);

        $context = Context::query()
            ->whereHas('personalBinding', fn ($query) => $query->where('actor_id', $actor->id))
            ->firstOrFail();

        Gate::forUser($this->user())->authorize('view', $context);

        return $context;
    }

    private function assetAccount(Ledger $ledger, string $uuid): Account
    {
        return $ledger->accounts()
            ->where('uuid', $uuid)
            ->where('type', AccountType::Asset->value)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function minorAmount(Ledger $ledger): ?int
    {
        try {
            $minor = MoneyAmount::parse($this->amount, $ledger->monetaryUnit->exponent);
        } catch (InvalidArgumentException) {
            $this->addError('amount', __('accounting.validation.amount'));

            return null;
        }

        if ($minor <= 0) {
            $this->addError('amount', __('accounting.validation.amount'));

            return null;
        }

        return $minor;
    }

    /** @return array{string, string} */
    private function periodRange(): array
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = CarbonImmutable::parse($this->periodDate, $timezone);

        return match ($this->periodMode) {
            'day' => [$date->toDateString(), $date->toDateString()],
            'week' => $this->weekRange($date, $user),
            'year' => [$date->startOfYear()->toDateString(), $date->endOfYear()->toDateString()],
            default => [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()],
        };
    }

    /** @return array{string, string} */
    private function weekRange(CarbonImmutable $date, User $user): array
    {
        $first = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
        $carbonFirst = $first === 7 ? CarbonInterface::SUNDAY : $first;
        $start = $date->startOfWeek($carbonFirst);

        return [$start->toDateString(), $start->addDays(6)->toDateString()];
    }

    private function resetEntryForm(): void
    {
        $this->amount = '';
        $this->description = '';
        $this->category = '';
        $this->accountUuid = '';
        $this->toAccountUuid = '';
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
