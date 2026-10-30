<?php

namespace App\Livewire\Bookkeeping;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\AttachEvidence;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Actions\Bookkeeping\RaiseDispute;
use App\Actions\Bookkeeping\RecordAdjustment;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Actions\Bookkeeping\RemoveLedgerParty;
use App\Actions\Bookkeeping\ResolveDispute;
use App\Actions\Bookkeeping\WithdrawEntry;
use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\DisputeOutcome;
use App\Enums\Bookkeeping\DisputeStatus;
use App\Enums\Bookkeeping\EntryOrigin;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\PartyStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Dispute;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\Bookkeeping\Wallet;
use App\Models\Fulfillment;
use App\Models\User;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\Bookkeeping\ConfirmationRules;
use App\Support\Bookkeeping\Toman;
use App\Support\MediaLimits;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * A shared or project book: who owes whom (confirmed / waiting / disputed),
 * each debt with what was paid and what is left, entries waiting for my
 * confirmation, recording debts and payments, people, and the full history.
 */
#[Layout('layouts.app')]
#[Title('Book')]
class BookShow extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $ledgerUuid = '';

    // Record dialog
    public bool $recordOpen = false;

    public string $entryType = 'obligation';

    public string $fromParty = '';

    public string $toParty = '';

    public string $amount = '';

    public string $occurredOn = '';

    public string $dueOn = '';

    public string $description = '';

    public bool $chooseAllocations = false;

    /** @var array<string, string> */
    public array $allocations = [];

    public string $refundPayment = '';

    public string $returnMode = 'unused';

    public string $refundObligation = '';

    public string $walletUuid = '';

    /** @var TemporaryUploadedFile|null */
    public $evidence = null;

    public string $formKey = '';

    // People drawer
    public string $partyUsername = '';

    public string $partyName = '';

    public string $partyContact = '';

    public string $partyRole = 'member';

    // Decisions
    /** @var array<string, string> */
    public array $notes = [];

    public ?string $disputeFor = null;

    public string $disputeReason = '';

    public ?string $adjustFor = null;

    public string $adjustDirection = 'increase';

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public ?string $evidenceFor = null;

    /** @var TemporaryUploadedFile|null */
    public $extraEvidence = null;

    public string $filter = 'all';

    /** all / manual / iet_workflow / import / correction */
    public string $origin = 'all';

    public function mount(Ledger $ledger): void
    {
        abort_if($ledger->kind === LedgerKind::Personal, 404);
        Gate::forUser($this->user())->authorize('view', $ledger);
        $this->ledgerUuid = $ledger->uuid;
        $this->formKey = (string) Str::uuid();
    }

    public function openRecord(string $type = 'obligation'): void
    {
        $ledger = $this->ledger();
        Gate::forUser($this->user())->authorize('record', $ledger);
        $this->resetErrorBag();
        $this->reset('amount', 'dueOn', 'description', 'allocations', 'chooseAllocations', 'walletUuid', 'evidence', 'refundPayment', 'refundObligation', 'returnMode');
        $this->entryType = in_array($type, ['obligation', 'payment', 'refund', 'write_off', 'settlement_in_kind'], true) ? $type : 'obligation';
        $this->occurredOn = CarbonImmutable::now(TemporalPreferences::timezoneFor($this->user()))->toDateString();
        $this->formKey = (string) Str::uuid();

        // Sensible default direction: in a two-person book, "the other person owes me"
        // for a debt and "I pay the other person" for a payment.
        $me = $ledger->partyFor($this->user()->actor);
        $others = $ledger->activeParties()->where('id', '!=', $me?->id)->get();
        $other = $others->count() === 1 ? $others->first() : null;
        [$from, $to] = match ($this->entryType) {
            'obligation', 'write_off' => [$other, $me],
            default => [$me, $other],
        };
        $this->fromParty = (string) ($from->uuid ?? '');
        $this->toParty = (string) ($to->uuid ?? '');
        $this->recordOpen = true;
    }

    public function closeRecord(): void
    {
        $this->recordOpen = false;
    }

    public function swapParties(): void
    {
        [$this->fromParty, $this->toParty] = [$this->toParty, $this->fromParty];
        $this->allocations = [];
    }

    public function record(RecordSharedEntry $record, AttachEvidence $attach): void
    {
        $ledger = $this->ledger();
        $from = $this->party($ledger, $this->fromParty, 'fromParty');
        $to = $this->party($ledger, $this->toParty, 'toParty');
        $wallet = null;
        if ($this->walletUuid !== '') {
            $wallet = Wallet::query()->where('uuid', $this->walletUuid)->first();
        }
        if ($this->evidence !== null) {
            $this->validate(['evidence' => ['file', 'max:'.MediaLimits::kilobytes('asset'), 'mimes:jpg,jpeg,png,webp,gif,pdf']]);
        }

        $type = TransactionType::from($this->entryType);
        $entry = $record->execute(
            $ledger,
            $this->user(),
            $type,
            $from,
            $to,
            $this->amount,
            $this->occurredOn,
            $this->description,
            $this->dueOn !== '' ? $this->dueOn : null,
            $type->settlesObligations() && $this->chooseAllocations ? $this->allocations : null,
            $wallet,
            'book:'.$this->formKey,
            refundPayment: $this->refundPayment !== '' ? Transaction::query()->where('ledger_id', $ledger->id)->where('uuid', $this->refundPayment)->firstOrFail() : null,
            returnMode: $this->returnMode,
            refundObligation: $this->refundObligation !== '' ? Transaction::query()->where('ledger_id', $ledger->id)->where('uuid', $this->refundObligation)->firstOrFail() : null,
        );

        if ($this->evidence !== null) {
            $attach->execute($entry, $this->user(), $this->evidence);
        }

        $this->recordOpen = false;
        $this->formKey = (string) Str::uuid();
        session()->flash('status', $entry->status === TransactionStatus::Pending ? __('bookkeeping.messages.recorded_waiting') : __('bookkeeping.messages.recorded'));
    }

    public function approve(string $uuid, DecideOnEntry $decide): void
    {
        $decide->approve($this->entry($uuid), $this->user(), $this->notes[$uuid] ?? null);
        unset($this->notes[$uuid]);
        session()->flash('status', __('bookkeeping.messages.confirmed'));
    }

    public function reject(string $uuid, DecideOnEntry $decide): void
    {
        $decide->reject($this->entry($uuid), $this->user(), $this->notes[$uuid] ?? '');
        unset($this->notes[$uuid]);
        session()->flash('status', __('bookkeeping.messages.rejected'));
    }

    public function withdraw(string $uuid, WithdrawEntry $withdraw): void
    {
        $withdraw->execute($this->entry($uuid), $this->user());
        session()->flash('status', __('bookkeeping.messages.withdrawn'));
    }

    public function openDispute(string $uuid): void
    {
        $this->resetErrorBag();
        $this->disputeFor = $uuid;
        $this->disputeReason = '';
    }

    public function closeDispute(): void
    {
        $this->disputeFor = null;
    }

    public function raiseDispute(RaiseDispute $raise): void
    {
        abort_if($this->disputeFor === null, 422);
        $raise->execute($this->entry($this->disputeFor), $this->user(), $this->disputeReason);
        $this->disputeFor = null;
        session()->flash('status', __('bookkeeping.messages.disputed'));
    }

    public function resolveDispute(string $disputeUuid, string $outcome, ResolveDispute $resolve): void
    {
        $dispute = Dispute::query()->where('uuid', $disputeUuid)->whereHas('transaction', fn ($query) => $query->where('ledger_id', $this->ledger()->id))->firstOrFail();
        $resolve->execute($dispute, $this->user(), DisputeOutcome::from($outcome), $this->notes[$disputeUuid] ?? null);
        session()->flash('status', __('bookkeeping.messages.dispute_'.$outcome));
    }

    public function openAdjust(string $uuid): void
    {
        $this->resetErrorBag();
        $this->adjustFor = $uuid;
        $this->reset('adjustAmount', 'adjustReason');
        $this->adjustDirection = 'increase';
    }

    public function closeAdjust(): void
    {
        $this->adjustFor = null;
    }

    public function adjust(RecordAdjustment $adjust): void
    {
        abort_if($this->adjustFor === null, 422);
        $adjust->execute($this->entry($this->adjustFor), $this->user(), AdjustmentDirection::from($this->adjustDirection), $this->adjustAmount, $this->adjustReason, 'adjust:'.$this->formKey);
        $this->adjustFor = null;
        $this->formKey = (string) Str::uuid();
        session()->flash('status', __('bookkeeping.messages.recorded_waiting'));
    }

    public function openEvidence(string $uuid): void
    {
        $this->resetErrorBag();
        $this->evidenceFor = $uuid;
        $this->extraEvidence = null;
    }

    public function closeEvidence(): void
    {
        $this->evidenceFor = null;
    }

    public function addEvidence(AttachEvidence $attach): void
    {
        abort_if($this->evidenceFor === null, 422);
        $this->validate(['extraEvidence' => ['required', 'file', 'max:'.MediaLimits::kilobytes('asset'), 'mimes:jpg,jpeg,png,webp,gif,pdf']]);
        $attach->execute($this->entry($this->evidenceFor), $this->user(), $this->extraEvidence);
        $this->evidenceFor = null;
        $this->extraEvidence = null;
        session()->flash('status', __('bookkeeping.messages.evidence_added'));
    }

    public function addParty(AddLedgerParty $add): void
    {
        $role = PartyRole::tryFrom($this->partyRole) ?? PartyRole::Member;
        $add->execute($this->ledger(), $this->user(), $this->partyUsername !== '' ? $this->partyUsername : null, $this->partyName !== '' ? $this->partyName : null, $role === PartyRole::Owner ? PartyRole::Member : $role, $this->partyContact);
        $this->reset('partyUsername', 'partyName', 'partyContact');
        session()->flash('status', __('bookkeeping.messages.person_added'));
    }

    public function removeParty(string $uuid, RemoveLedgerParty $remove): void
    {
        $party = LedgerParty::query()->where('ledger_id', $this->ledger()->id)->where('uuid', $uuid)->firstOrFail();
        $remove->execute($party, $this->user());
        if ((int) $party->actor_id === (int) $this->user()->actor?->id) {
            $this->redirectRoute('bookkeeping.index');
        }
    }

    public function archiveBook(BookkeepingRecorder $recorder): void
    {
        $ledger = $this->ledger();
        Gate::forUser($this->user())->authorize('archive', $ledger);
        $ledger->forceFill(['status' => $ledger->status === LedgerStatus::Active ? LedgerStatus::Archived : LedgerStatus::Active, 'archived_at' => $ledger->status === LedgerStatus::Active ? now() : null])->save();
        $recorder->audit($ledger, $recorder->actor($this->user()), $ledger->status === LedgerStatus::Archived ? 'ledger.archived' : 'ledger.reopened');
    }

    public function render(BalanceCalculator $balances, ConfirmationRules $rules): View
    {
        $user = $this->user();
        $ledger = $this->ledger();
        Gate::forUser($user)->authorize('view', $ledger);
        $me = $ledger->partyFor($user->actor);
        $parties = $ledger->parties()->with('actor.user', 'actor.profile')->orderBy('id')->get();
        $activeParties = $parties->where('status', PartyStatus::Active)->values();

        $entries = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->when($this->filter === 'waiting', fn ($query) => $query->where('status', TransactionStatus::Pending->value))
            ->when($this->filter === 'debts', fn ($query) => $query->where('type', TransactionType::Obligation->value))
            ->when(in_array($this->origin, array_column(EntryOrigin::cases(), 'value'), true), fn ($query) => $query->where('origin', $this->origin))
            ->when($this->filter === 'payments', fn ($query) => $query->whereIn('type', [TransactionType::Payment->value, TransactionType::Refund->value, TransactionType::WriteOff->value, TransactionType::SettlementInKind->value]))
            ->with(['fromParty.actor.user', 'toParty.actor.user', 'recorder.user', 'confirmations.actor.user', 'disputes.raisedBy.user', 'allocations.obligation', 'assets', 'related', 'wallet.ledger'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $debts = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('type', TransactionType::Obligation->value)
            ->whereIn('status', [TransactionStatus::Confirmed->value, TransactionStatus::Pending->value, TransactionStatus::Disputed->value])
            ->with(['fromParty.actor.user', 'toParty.actor.user'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (Transaction $debt): array => ['debt' => $debt, 'progress' => $balances->obligation($debt)]);

        $awaiting = $entries->mapWithKeys(fn (Transaction $entry): array => [$entry->uuid => $rules->awaiting($entry)]);

        $openForAllocation = collect();
        if ($this->recordOpen && in_array($this->entryType, ['payment', 'write_off', 'settlement_in_kind'], true)) {
            $from = $parties->firstWhere('uuid', $this->fromParty);
            $to = $parties->firstWhere('uuid', $this->toParty);
            if ($from instanceof LedgerParty && $to instanceof LedgerParty && $from->id !== $to->id) {
                $openForAllocation = $balances->openObligations($ledger, $from, $to);
            }
        }

        $myWallets = $this->recordOpen && in_array($this->entryType, ['payment', 'refund'], true)
            ? app(EnsurePersonalLedger::class)->execute($user)->wallets()->whereNull('archived_at')->get()
            : collect();

        $workSources = Fulfillment::query()
            ->whereIn('uuid', $entries->where('source_type', 'fulfillment')->pluck('source_uuid')->filter()->unique()->values())
            ->with('commitment:id,uuid,title')
            ->get(['id', 'uuid', 'commitment_id'])
            ->keyBy('uuid');

        return view('livewire.bookkeeping.book-show', [
            'workSources' => $workSources,
            'ledger' => $ledger,
            'me' => $me,
            'parties' => $parties,
            'activeParties' => $activeParties,
            'pairs' => $balances->pairs($ledger),
            'myPosition' => $me instanceof LedgerParty ? $balances->forParty($ledger, $me) : null,
            'creditSnapshot' => $balances->credit($ledger),
            'returnSources' => Transaction::query()->where('ledger_id', $ledger->id)->where('type', 'payment')->where('status', 'confirmed')->with('fromParty', 'toParty')->get(),
            'returnObligations' => Transaction::query()->where('ledger_id', $ledger->id)->where('type', 'obligation')->where('status', 'confirmed')->get(),
            'entries' => $entries,
            'debts' => $debts,
            'awaiting' => $awaiting,
            'rules' => $rules,
            'openForAllocation' => $openForAllocation,
            'myWallets' => $myWallets,
            'amountPreview' => $this->preview($this->amount),
            'canRecord' => Gate::forUser($user)->allows('record', $ledger),
            'canManage' => Gate::forUser($user)->allows('manage', $ledger),
            'canArchive' => Gate::forUser($user)->allows('archive', $ledger),
            'openDisputes' => Dispute::query()->whereIn('transaction_id', $entries->pluck('id'))->where('status', DisputeStatus::Open->value)->get()->keyBy('transaction_id'),
            'roles' => [PartyRole::Partner, PartyRole::Member, PartyRole::Viewer],
        ])->title($ledger->name);
    }

    private function preview(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Toman::format(Toman::parse($value));
        } catch (Throwable) {
            return null;
        }
    }

    private function entry(string $uuid): Transaction
    {
        return Transaction::query()->where('ledger_id', $this->ledger()->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function party(Ledger $ledger, string $uuid, string $field): LedgerParty
    {
        $party = LedgerParty::query()->where('ledger_id', $ledger->id)->where('uuid', $uuid)->first();
        if (! $party instanceof LedgerParty) {
            throw ValidationException::withMessages([$field => __('bookkeeping.validation.party_not_in_book')]);
        }

        return $party;
    }

    private function ledger(): Ledger
    {
        return Ledger::query()->where('uuid', $this->ledgerUuid)->firstOrFail();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        return $user;
    }
}
