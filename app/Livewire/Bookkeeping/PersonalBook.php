<?php

namespace App\Livewire\Bookkeeping;

use App\Actions\Bookkeeping\AttachEvidence;
use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Actions\Bookkeeping\ManageWallets;
use App\Actions\Bookkeeping\RecordPersonalEntry;
use App\Enums\Bookkeeping\CategoryDirection;
use App\Enums\Bookkeeping\TransactionType;
use App\Enums\Bookkeeping\WalletKind;
use App\Models\Bookkeeping\Category;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Bookkeeping\Wallet;
use App\Models\User;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\Toman;
use App\Support\MediaLimits;
use App\Support\TemporalCalendar;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * A person's own money: quick "spent / received / moved" entries, wallets
 * with live balances, and month totals by category.
 */
#[Layout('layouts.app')]
#[Title('Personal book')]
class PersonalBook extends Component
{
    use WithFileUploads;

    public string $type = 'expense';

    public string $amount = '';

    public string $occurredOn = '';

    public string $walletUuid = '';

    public string $toWalletUuid = '';

    public string $categoryUuid = '';

    public string $counterpartyName = '';

    public string $description = '';

    /** @var TemporaryUploadedFile|null */
    public $receipt = null;

    /** Guards against double submission of the same form. */
    public string $formKey = '';

    #[Url(as: 'month')]
    public string $month = '';

    public bool $walletOpen = false;

    public string $walletName = '';

    public string $walletKind = 'card';

    public string $walletOpening = '';

    public string $walletDigits = '';

    public bool $categoryOpen = false;

    public string $categoryName = '';

    public function mount(): void
    {
        $now = CarbonImmutable::now(TemporalPreferences::timezoneFor($this->user()));
        $this->occurredOn = $now->toDateString();
        $this->month = $this->monthStart()->toDateString();
        $this->formKey = (string) Str::uuid();
    }

    public function setType(string $type): void
    {
        if (in_array($type, ['expense', 'income', 'transfer'], true)) {
            $this->type = $type;
            $this->categoryUuid = '';
            $this->resetErrorBag();
        }
    }

    public function save(RecordPersonalEntry $record, AttachEvidence $evidence): void
    {
        $ledger = $this->ledger();
        $type = TransactionType::from($this->type);
        $wallet = $this->wallet($ledger, $this->walletUuid, 'walletUuid');
        $toWallet = $type === TransactionType::Transfer ? $this->wallet($ledger, $this->toWalletUuid, 'toWalletUuid') : null;
        $category = $this->categoryUuid !== ''
            ? Category::query()->where('ledger_id', $ledger->id)->where('uuid', $this->categoryUuid)->first()
            : null;

        if ($this->receipt !== null) {
            $this->validate(['receipt' => ['file', 'max:'.MediaLimits::kilobytes('asset'), 'mimes:jpg,jpeg,png,webp,gif,pdf']]);
        }

        $entry = $record->execute(
            $ledger,
            $this->user(),
            $type,
            $this->amount,
            $this->occurredOn,
            $wallet,
            $category,
            $toWallet,
            $this->counterpartyName,
            $this->description,
            'personal:'.$this->formKey,
        );

        if ($this->receipt !== null) {
            $evidence->execute($entry, $this->user(), $this->receipt);
        }

        $this->reset('amount', 'counterpartyName', 'description', 'receipt');
        $this->formKey = (string) Str::uuid();
        session()->flash('status', __('bookkeeping.messages.saved'));
    }

    public function undo(string $uuid, RecordPersonalEntry $record): void
    {
        $entry = Transaction::query()->where('ledger_id', $this->ledger()->id)->where('uuid', $uuid)->firstOrFail();
        $record->undo($entry, $this->user());
        session()->flash('status', __('bookkeeping.messages.undone'));
    }

    public function shiftMonth(int $delta): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $start = $this->monthStart();
        $this->month = ($delta > 0
            ? TemporalCalendar::nextMonthStart($start, $user, $timezone)
            : TemporalCalendar::previousMonthStart($start, $user, $timezone))->toDateString();
    }

    public function openWallet(): void
    {
        $this->reset('walletName', 'walletOpening', 'walletDigits');
        $this->walletKind = 'card';
        $this->resetErrorBag();
        $this->walletOpen = true;
    }

    public function addWallet(ManageWallets $wallets): void
    {
        $wallets->addWallet($this->ledger(), $this->user(), $this->walletName, WalletKind::from($this->walletKind), $this->walletOpening, $this->walletDigits);
        $this->walletOpen = false;
    }

    public function archiveWallet(string $uuid, ManageWallets $wallets): void
    {
        $wallet = Wallet::query()->where('ledger_id', $this->ledger()->id)->where('uuid', $uuid)->firstOrFail();
        $wallets->archiveWallet($wallet, $this->user());
    }

    public function openCategory(): void
    {
        $this->reset('categoryName');
        $this->resetErrorBag();
        $this->categoryOpen = true;
    }

    public function addCategory(ManageWallets $wallets): void
    {
        $direction = $this->type === 'income' ? CategoryDirection::Income : CategoryDirection::Expense;
        $category = $wallets->addCategory($this->ledger(), $this->user(), $direction, $this->categoryName);
        $this->categoryUuid = $category->uuid;
        $this->categoryOpen = false;
    }

    public function render(BalanceCalculator $balances): View
    {
        $ledger = $this->ledger();
        $wallets = $balances->wallets($ledger);
        if ($this->walletUuid === '' || ! $wallets->contains(fn (array $row): bool => $row['wallet']->uuid === $this->walletUuid)) {
            $this->walletUuid = (string) ($wallets->first()['wallet']->uuid ?? '');
        }

        $direction = $this->type === 'income' ? CategoryDirection::Income : CategoryDirection::Expense;
        $categories = $ledger->categories()->whereNull('archived_at')->where('direction', $direction->value)->where(fn ($query) => $query->whereNull('system_key')->orWhere('system_key', '!=', 'correction'))->orderBy('id')->get();
        $allCategories = $ledger->categories()->get()->keyBy('id');

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $start = $this->monthStart();
        $end = TemporalCalendar::nextMonthStart($start, $user, $timezone)->subDay();
        $period = $balances->period($ledger, $start, $end);

        $entries = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->with(['wallet', 'counterWallet', 'category', 'assets'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $undone = Transaction::query()->where('ledger_id', $ledger->id)->whereNotNull('related_transaction_id')->pluck('related_transaction_id')->all();

        // Payments made in shared books from my wallets, so balances explain themselves.
        $sharedMoves = Transaction::query()
            ->whereIn('wallet_id', $wallets->pluck('wallet.id'))
            ->where('ledger_id', '!=', $ledger->id)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->with(['ledger', 'wallet', 'fromParty', 'toParty'])
            ->orderByDesc('occurred_on')
            ->limit(50)
            ->get();

        return view('livewire.bookkeeping.personal-book', [
            'ledger' => $ledger,
            'wallets' => $wallets,
            'categories' => $categories,
            'allCategories' => $allCategories,
            'period' => $period,
            'monthLabel' => TemporalCalendar::monthLabel($start, $user, $timezone).' '.TemporalCalendar::yearLabel($start, $user, $timezone),
            'entries' => $entries,
            'undone' => $undone,
            'sharedMoves' => $sharedMoves,
            'amountPreview' => $this->amountPreview(),
            'walletKinds' => WalletKind::cases(),
        ])->title(__('bookkeeping.personal.title'));
    }

    private function amountPreview(): ?string
    {
        if (trim($this->amount) === '') {
            return null;
        }

        try {
            return Toman::format(Toman::parse($this->amount));
        } catch (Throwable) {
            return null;
        }
    }

    /** First day of the shown month in the person's own calendar (Persian, Gregorian, ...). */
    private function monthStart(): CarbonImmutable
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);

        try {
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->month) === 1
                ? CarbonImmutable::createFromFormat('!Y-m-d', $this->month, $timezone)
                : false;
        } catch (Throwable) {
            $date = false;
        }

        return TemporalCalendar::monthStart($date instanceof CarbonImmutable ? $date : CarbonImmutable::now($timezone), $user, $timezone);
    }

    public function closeWallet(): void
    {
        $this->walletOpen = false;
    }

    public function closeCategory(): void
    {
        $this->categoryOpen = false;
    }

    private function wallet(Ledger $ledger, string $uuid, string $field): Wallet
    {
        $wallet = Wallet::query()->where('ledger_id', $ledger->id)->where('uuid', $uuid)->whereNull('archived_at')->first();
        if (! $wallet instanceof Wallet) {
            throw ValidationException::withMessages([$field => __('bookkeeping.validation.wallet')]);
        }

        return $wallet;
    }

    private function ledger(): Ledger
    {
        return app(EnsurePersonalLedger::class)->execute($this->user());
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        return $user;
    }
}
