<?php

namespace App\Livewire\Accounting;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\CreatePersonalLedger;
use App\Actions\Accounting\RecordExpense;
use App\Actions\Accounting\RecordIncome;
use App\Actions\Accounting\RecordTransfer;
use App\Actions\Accounting\ReverseJournalEntry;
use App\Actions\Contexts\EnsurePersonalContext;
use App\JournalEntryKind;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Context;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Ledger;
use App\Models\MoneyIntention;
use App\Models\User;
use App\Support\MonetaryUnitCatalog;
use App\Support\MoneyAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Money')]
class BasicIndex extends Component
{
    #[Url(as: 'ledger')]
    public string $ledgerUuid = '';

    public string $unitCode = 'USD';

    public string $ledgerName = '';

    public string $action = 'expense';

    public string $amount = '';

    public string $date = '';

    public string $description = '';

    public string $category = '';

    public string $accountUuid = '';

    public string $toAccountUuid = '';

    public string $newAccountName = '';

    public string $intentionKind = 'purchase';

    public string $intentionTitle = '';

    public string $intentionAmount = '';

    public string $intentionDate = '';

    public string $intentionNotes = '';

    public function mount(EnsurePersonalContext $contexts): void
    {
        $user = $this->user();
        $context = $contexts->execute($user);
        $today = CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->toDateString();

        $this->date = $today;
        $this->intentionDate = $today;

        $ledgers = $context->ledgers()
            ->with('monetaryUnit')
            ->whereHas('monetaryUnit', fn ($query) => $query->where('code', '!=', 'IET'))
            ->orderBy('id')
            ->get();

        if ($this->ledgerUuid !== '') {
            abort_unless($ledgers->contains('uuid', $this->ledgerUuid), 404);
        } elseif ($ledgers->isNotEmpty()) {
            $this->ledgerUuid = (string) $ledgers->first()->uuid;
        }
    }

    public function selectLedger(string $uuid): void
    {
        $context = $this->personalContext();
        abort_unless(
            $context->ledgers()
                ->where('uuid', $uuid)
                ->whereHas('monetaryUnit', fn ($query) => $query->where('code', '!=', 'IET'))
                ->exists(),
            404,
        );

        $this->ledgerUuid = $uuid;
        $this->accountUuid = '';
        $this->toAccountUuid = '';
        $this->resetValidation();
    }

    public function createLedger(CreatePersonalLedger $create): void
    {
        $data = $this->validate([
            'unitCode' => ['required', 'string', 'max:12'],
            'ledgerName' => ['nullable', 'string', 'max:180'],
        ]);

        abort_if(strtoupper($data['unitCode']) === 'IET', 422, 'IET is managed through the internal Finance rail.');

        $ledger = $create->execute(
            $this->user(),
            $data['unitCode'],
            trim($data['ledgerName']) !== '' ? trim($data['ledgerName']) : null,
        );

        $this->ledgerUuid = $ledger->uuid;
        $this->ledgerName = '';
        $this->accountUuid = (string) $ledger->accounts()->where('system_key', 'cash')->value('uuid');

        session()->flash('status', __('accounting.messages.ledger_ready'));
    }

    public function addAccount(CreateLedgerAccount $create): void
    {
        $data = $this->validate([
            'newAccountName' => ['required', 'string', 'max:180'],
        ]);

        $account = $create->execute(
            $this->ledger(),
            $this->user(),
            trim($data['newAccountName']),
            AccountType::Asset,
        );

        $this->newAccountName = '';
        $this->accountUuid = $account->uuid;

        session()->flash('status', __('accounting.messages.account_ready'));
    }

    public function record(
        RecordExpense $expense,
        RecordIncome $income,
        RecordTransfer $transfer,
    ): void {
        $data = $this->validate([
            'action' => ['required', 'in:expense,income,transfer'],
            'amount' => ['required', 'string', 'max:40'],
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:180'],
            'accountUuid' => ['required', 'uuid'],
            'toAccountUuid' => ['required_if:action,transfer', 'nullable', 'uuid'],
        ]);

        $ledger = $this->ledger();
        $amountMinor = $this->minorAmount($ledger, $data['amount']);
        $account = $this->assetAccount($ledger, $data['accountUuid']);
        $description = trim($data['description']) !== '' ? trim($data['description']) : null;
        $category = trim($data['category']);

        match ($data['action']) {
            'expense' => $expense->execute(
                $ledger,
                $this->user(),
                $account,
                $amountMinor,
                $data['date'],
                $category !== '' ? $category : 'General expense',
                $description,
            ),
            'income' => $income->execute(
                $ledger,
                $this->user(),
                $account,
                $amountMinor,
                $data['date'],
                $category !== '' ? $category : 'General income',
                $description,
            ),
            'transfer' => $transfer->execute(
                $ledger,
                $this->user(),
                $account,
                $this->assetAccount($ledger, $data['toAccountUuid'] ?? ''),
                $amountMinor,
                $data['date'],
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

    public function addIntention(): void
    {
        $data = $this->validate([
            'intentionKind' => ['required', 'in:purchase,earn'],
            'intentionTitle' => ['required', 'string', 'max:180'],
            'intentionAmount' => ['required', 'string', 'max:40'],
            'intentionDate' => ['nullable', 'date_format:Y-m-d'],
            'intentionNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ledger = $this->ledger();

        MoneyIntention::query()->create([
            'user_id' => $this->user()->id,
            'ledger_id' => $ledger->id,
            'kind' => $data['intentionKind'],
            'title' => trim($data['intentionTitle']),
            'amount_minor' => $this->minorAmount($ledger, $data['intentionAmount'], 'intentionAmount'),
            'target_on' => $data['intentionDate'] !== '' ? $data['intentionDate'] : null,
            'notes' => trim($data['intentionNotes']) !== '' ? trim($data['intentionNotes']) : null,
        ]);

        $this->intentionTitle = '';
        $this->intentionAmount = '';
        $this->intentionNotes = '';

        session()->flash('status', __('accounting.baseline.intention_saved'));
    }

    public function archiveIntention(int $id): void
    {
        $intention = MoneyIntention::query()
            ->where('user_id', $this->user()->id)
            ->findOrFail($id);

        $intention->status = 'archived';
        $intention->save();
    }

    public function reverseEntry(int $entryId, ReverseJournalEntry $reverse): void
    {
        $ledger = $this->ledger();
        $entry = $ledger->journalEntries()->with('reversals')->findOrFail($entryId);

        abort_unless($entry->kind !== JournalEntryKind::Reversal, 422);
        abort_if($entry->reversals->isNotEmpty(), 422);

        $reverse->execute(
            $entry,
            $this->user(),
            CarbonImmutable::now(TemporalPreferences::timezoneFor($this->user()))->toDateString(),
        );

        session()->flash('status', __('accounting.messages.reversed'));
    }

    public function render(): View
    {
        $context = $this->personalContext();
        $ledgers = $context->ledgers()->with('monetaryUnit')->orderBy('id')->get();
        $ledger = $this->ledgerOrNull();

        $assetAccounts = collect();
        $entries = collect();
        $rows = collect();
        $intentions = collect();

        if ($ledger instanceof Ledger) {
            $ledger->loadMissing(['monetaryUnit', 'accounts']);

            $assetAccounts = $ledger->accounts
                ->filter(fn (Account $account): bool => $account->type === AccountType::Asset && $account->status === 'active')
                ->values();

            if ($this->accountUuid === '' && $assetAccounts->isNotEmpty()) {
                $this->accountUuid = (string) $assetAccounts->first()->uuid;
            }

            $entries = $ledger->journalEntries()
                ->with(['lines.account', 'reversals'])
                ->latest('occurred_on')
                ->latest('id')
                ->limit(150)
                ->get();

            $rows = $entries->map(fn (JournalEntry $entry): array => $this->entryRow($entry));

            $intentions = MoneyIntention::query()
                ->where('user_id', $this->user()->id)
                ->where('ledger_id', $ledger->id)
                ->where('status', 'open')
                ->orderByRaw('target_on is null')
                ->orderBy('target_on')
                ->latest('id')
                ->get();
        }

        return view('livewire.accounting.basic-index', [
            'ledgers' => $ledgers,
            'ledger' => $ledger,
            'assetAccounts' => $assetAccounts,
            'rows' => $rows,
            'intentions' => $intentions,
            'unitCatalog' => array_filter(
                MonetaryUnitCatalog::all(),
                fn (array $meta, string $code): bool => $code !== 'IET',
                ARRAY_FILTER_USE_BOTH,
            ),
        ]);
    }

    /**
     * @return array{
     *   id:int, uuid:string, kind:string, date:mixed, description:string,
     *   amount_minor:int, from:?string, to:?string, reversed:bool
     * }
     */
    private function entryRow(JournalEntry $entry): array
    {
        $lines = $entry->lines;
        $assetDebit = $lines->first(fn (JournalLine $line): bool => $line->account?->type === AccountType::Asset && (int) $line->debit_minor > 0);
        $assetCredit = $lines->first(fn (JournalLine $line): bool => $line->account?->type === AccountType::Asset && (int) $line->credit_minor > 0);
        $other = $lines->first(fn (JournalLine $line): bool => $line->account?->type !== AccountType::Asset);
        $amount = (int) $lines->sum('debit_minor');

        return [
            'id' => (int) $entry->id,
            'uuid' => (string) $entry->uuid,
            'kind' => $entry->kind->value,
            'date' => $entry->occurred_on,
            'description' => (string) ($entry->description ?? ''),
            'amount_minor' => $amount,
            'from' => match ($entry->kind) {
                JournalEntryKind::Expense, JournalEntryKind::Transfer => $assetCredit?->account?->name,
                JournalEntryKind::Income => $other?->account?->name,
                default => $other?->account?->name,
            },
            'to' => match ($entry->kind) {
                JournalEntryKind::Expense => $other?->account?->name,
                JournalEntryKind::Transfer, JournalEntryKind::Income => $assetDebit?->account?->name,
                default => $assetDebit?->account?->name,
            },
            'reversed' => $entry->reversals->isNotEmpty(),
        ];
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

        $ledger = $this->personalContext()
            ->ledgers()
            ->with('monetaryUnit')
            ->where('uuid', $this->ledgerUuid)
            ->whereHas('monetaryUnit', fn ($query) => $query->where('code', '!=', 'IET'))
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

    private function minorAmount(Ledger $ledger, string $amount, string $field = 'amount'): int
    {
        try {
            $minor = MoneyAmount::parse($amount, $ledger->monetaryUnit->exponent);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                $field => __('accounting.validation.amount'),
            ]);
        }

        if ($minor <= 0) {
            throw ValidationException::withMessages([
                $field => __('accounting.validation.amount'),
            ]);
        }

        return $minor;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
