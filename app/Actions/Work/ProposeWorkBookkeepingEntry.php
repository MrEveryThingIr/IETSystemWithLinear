<?php

namespace App\Actions\Work;

use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Enums\Bookkeeping\EntryOrigin;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Fulfillment;
use App\Models\User;
use App\Support\Bookkeeping\EntrySource;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Turns accepted work into a *proposed* debt in a book both sides share:
 * "the employer owes the worker for this accepted work". It counts only when
 * the other side confirms it there, exactly like any shared entry. IET never
 * writes into anyone's personal book.
 *
 * One accepted fulfillment yields at most one live entry across all books;
 * asking again returns the existing one.
 */
class ProposeWorkBookkeepingEntry
{
    public const SOURCE_TYPE = 'fulfillment';

    /** Currencies the books can hold (whole toman), with how many minor units make one toman. */
    private const TOMAN_DIVISORS = ['IRT' => 1, 'IRR' => 10];

    public function __construct(
        private readonly RecordSharedEntry $record,
        private readonly CreateLedger $createLedger,
        private readonly AddLedgerParty $addParty,
    ) {}

    /** Whole toman owed for the accepted quantity, or null when the agreed price cannot be expressed in toman. */
    public function amountFor(Fulfillment $fulfillment): ?int
    {
        $fulfillment->loadMissing('commitment.serviceTerm.monetaryUnit');
        $terms = $fulfillment->commitment?->serviceTerm;
        $code = strtoupper((string) $terms?->monetaryUnit?->code);
        if ($terms === null || ! isset(self::TOMAN_DIVISORS[$code])) {
            return null;
        }
        if ((int) $terms->monetaryUnit->exponent !== 0) {
            return null;
        }

        // quantity has up to 4 decimals; keep integer math.
        [$whole, $fraction] = array_pad(explode('.', (string) $fulfillment->quantity, 2), 2, '');
        $scaled = ((int) $whole * 10000) + (int) substr(str_pad($fraction, 4, '0'), 0, 4);
        $minor = intdiv($scaled * (int) $terms->unit_rate_minor + 5000, 10000);
        $toman = intdiv($minor + intdiv(self::TOMAN_DIVISORS[$code], 2), self::TOMAN_DIVISORS[$code]);

        return $toman > 0 ? $toman : null;
    }

    /** The live entry already proposed for this work, in any book. */
    public function existing(Fulfillment $fulfillment): ?Transaction
    {
        return Transaction::query()
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_uuid', $fulfillment->uuid)
            ->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Confirmed->value, TransactionStatus::Disputed->value])
            ->latest('id')
            ->first();
    }

    /**
     * Shared or project books where both people of the work are active and the user may record.
     *
     * @return Collection<int, Ledger>
     */
    public function booksFor(User $user, Commitment $commitment): Collection
    {
        $actors = [(int) $commitment->obligor_actor_id, (int) $commitment->beneficiary_actor_id];

        return Ledger::query()
            ->whereIn('kind', [LedgerKind::Shared->value, LedgerKind::Project->value])
            ->where('status', LedgerStatus::Active->value)
            ->whereHas('parties', fn ($query) => $query->where('actor_id', $actors[0])->where('status', PartyStatus::Active->value))
            ->whereHas('parties', fn ($query) => $query->where('actor_id', $actors[1])->where('status', PartyStatus::Active->value))
            ->orderBy('name')
            ->get()
            ->filter(fn (Ledger $ledger): bool => Gate::forUser($user)->allows('record', $ledger))
            ->values();
    }

    /** A new shared book with the other person of this work (they are added and notified as usual). */
    public function createSharedBook(User $user, Commitment $commitment): Ledger
    {
        $actor = $this->participant($user, $commitment);
        $otherId = (int) $actor->id === (int) $commitment->obligor_actor_id ? $commitment->beneficiary_actor_id : $commitment->obligor_actor_id;
        $other = Actor::query()->with('user')->findOrFail($otherId);
        abort_unless($other->user instanceof User && filled($other->user->username), 422);

        return DB::transaction(function () use ($user, $commitment, $other): Ledger {
            $ledger = $this->createLedger->execute($user, LedgerKind::Shared, (string) __('work.books.default_name', ['title' => $commitment->title]));
            $this->addParty->execute($ledger, $user, (string) $other->user->username, null);

            return $ledger->refresh();
        }, 3);
    }

    public function execute(User $user, Fulfillment $fulfillment, Ledger $ledger): Transaction
    {
        $fulfillment->loadMissing('commitment.serviceTerm.monetaryUnit', 'commitment.contractVersion');
        $commitment = $fulfillment->commitment;
        abort_unless($commitment instanceof Commitment, 404);
        $this->participant($user, $commitment);
        abort_unless($fulfillment->status === FulfillmentStatus::Accepted, 422, 'Only accepted work can be proposed for the books.');

        $amount = $this->amountFor($fulfillment);
        if ($amount === null) {
            throw ValidationException::withMessages(['ledger' => __('work.books.no_amount')]);
        }
        if (! $this->booksFor($user, $commitment)->contains(fn (Ledger $book): bool => (int) $book->id === (int) $ledger->id)) {
            throw ValidationException::withMessages(['ledger' => __('work.books.not_shared')]);
        }

        return DB::transaction(function () use ($user, $fulfillment, $commitment, $ledger, $amount): Transaction {
            // Serialise proposals for the same work, from either side.
            Fulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);
            $existing = $this->existing($fulfillment);
            if ($existing instanceof Transaction) {
                return $existing;
            }

            $parties = LedgerParty::query()->where('ledger_id', $ledger->id)->where('status', PartyStatus::Active->value)->get()->keyBy('actor_id');
            $debtor = $parties->get((int) $commitment->beneficiary_actor_id);
            $creditor = $parties->get((int) $commitment->obligor_actor_id);
            abort_unless($debtor instanceof LedgerParty && $creditor instanceof LedgerParty, 422);

            $terms = $commitment->serviceTerm;
            $accepted = ($fulfillment->reviewed_at ?? now())->copy();
            $source = new EntrySource(EntryOrigin::IetWorkflow, self::SOURCE_TYPE, (string) $fulfillment->uuid, (int) $commitment->contractVersion->version);

            return $this->record->execute(
                $ledger,
                $user,
                TransactionType::Obligation,
                $debtor,
                $creditor,
                $amount,
                $accepted->toDateString(),
                (string) __('work.books.description', [
                    'title' => $commitment->title,
                    'quantity' => rtrim(rtrim((string) $fulfillment->quantity, '0'), '.'),
                    'unit' => (string) $fulfillment->unit,
                ]),
                $terms !== null && $terms->payment_due_days > 0 ? $accepted->copy()->addDays((int) $terms->payment_due_days)->toDateString() : null,
                // A rejected or withdrawn proposal may be proposed again: one key per attempt.
                idempotencyKey: $source->idempotencyKey().':a'.(Transaction::query()->where('source_type', self::SOURCE_TYPE)->where('source_uuid', $fulfillment->uuid)->count() + 1),
                source: $source,
            );
        }, 3);
    }

    private function participant(User $user, Commitment $commitment): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless(
            $actor instanceof Actor
            && Gate::forUser($user)->allows('view', $commitment)
            && in_array((int) $actor->id, [(int) $commitment->obligor_actor_id, (int) $commitment->beneficiary_actor_id], true),
            403,
        );

        return $actor;
    }
}
