<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyStatus;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Actor;
use App\Models\Bookkeeping\Allocation;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\LedgerParty;
use App\Models\Bookkeeping\Transaction;
use App\Models\Bookkeeping\Wallet;
use App\Models\User;
use App\Support\Bookkeeping\BalanceCalculator;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\Bookkeeping\ConfirmationRules;
use App\Support\Bookkeeping\EntryDate;
use App\Support\Bookkeeping\Toman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records a debt, payment, refund, write-off or settlement in kind between
 * two people in a shared or project book.
 *
 * - Two-sided: the other person (with an account) must confirm before it
 *   counts; until then it shows as "waiting".
 * - Payments can be split across several debts (allocations), oldest first
 *   when the person does not choose. Any remainder is kept as an advance.
 * - Idempotent: the same idempotency key from the same person returns the
 *   first entry instead of recording it twice.
 */
class RecordSharedEntry
{
    public function __construct(
        private readonly BookkeepingRecorder $recorder,
        private readonly BalanceCalculator $balances,
        private readonly ConfirmationRules $rules,
    ) {}

    /**
     * @param  array<string, int|string>|null  $allocations  obligation uuid => amount; null = automatic
     */
    public function execute(
        Ledger $ledger,
        User $user,
        TransactionType $type,
        LedgerParty $from,
        LedgerParty $to,
        int|string $amount,
        string $occurredOn,
        ?string $description = null,
        ?string $dueOn = null,
        ?array $allocations = null,
        ?Wallet $wallet = null,
        ?string $idempotencyKey = null,
    ): Transaction {
        abort_unless(in_array($type, TransactionType::shared(), true) && $type !== TransactionType::Adjustment, 422, 'Use the adjustment action to correct a debt.');
        abort_if($ledger->kind === LedgerKind::Personal, 422, 'Personal books record income and expenses.');
        Gate::forUser($user)->authorize('record', $ledger);

        $amount = Toman::parse($amount);
        $occurred = EntryDate::parse($occurredOn, 'occurredOn');
        $due = $dueOn !== null && $dueOn !== '' ? EntryDate::parse($dueOn, 'dueOn') : null;
        $description = $description !== null ? Str::limit(Str::squish($description), 500, '') : null;
        $actor = $this->recorder->actor($user);

        if ($idempotencyKey !== null && ($existing = $this->existing($actor, $idempotencyKey, $ledger)) instanceof Transaction) {
            return $existing;
        }

        $this->assertParties($ledger, $from, $to, $user, $actor);
        if ($wallet instanceof Wallet) {
            $this->assertOwnWallet($wallet, $actor);
        }

        return DB::transaction(function () use ($ledger, $actor, $type, $from, $to, $amount, $occurred, $due, $description, $allocations, $wallet, $idempotencyKey): Transaction {
            $locked = Ledger::query()->lockForUpdate()->findOrFail($ledger->id);
            abort_unless($locked->status === LedgerStatus::Active, 422, 'This book is archived.');

            $transaction = Transaction::query()->create([
                'ledger_id' => $locked->id,
                'type' => $type,
                'status' => TransactionStatus::Pending,
                'amount' => $amount,
                'occurred_on' => $occurred->toDateString(),
                'due_on' => $type === TransactionType::Obligation ? $due?->toDateString() : null,
                'description' => $description !== '' ? $description : null,
                'from_party_id' => $from->id,
                'to_party_id' => $to->id,
                'wallet_id' => $wallet?->id,
                'recorded_by_actor_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'meta' => ['source' => 'manual'],
            ]);

            if ($type->settlesObligations()) {
                $this->allocate($locked, $transaction, $from, $to, $amount, $allocations);
            }

            $this->recorder->audit($locked, $actor, 'entry.recorded', $transaction, after: BookkeepingRecorder::snapshot($transaction));

            if ($this->rules->required($transaction)->isEmpty()) {
                $transaction->transitionTo(TransactionStatus::Confirmed, ['confirmed_at' => now()]);
                $this->recorder->audit($locked, $actor, 'entry.confirmed', $transaction, after: ['reason' => 'nobody else can confirm']);
            } else {
                $this->recorder->notify(
                    $this->rules->required($transaction),
                    $transaction,
                    'confirm_request',
                    ['who' => $actor->user->username ?? '', 'type' => (string) __('bookkeeping.types.'.$type->value), 'amount' => Toman::format($amount), 'book' => $locked->name],
                    $actor,
                );
            }

            return $transaction->refresh();
        }, 3);
    }

    /** @param  array<string, int|string>|null  $requested */
    private function allocate(Ledger $ledger, Transaction $transaction, LedgerParty $from, LedgerParty $to, int $amount, ?array $requested): void
    {
        // A payment from the debtor settles debts "from → to"; a refund flows
        // back, so it reduces what was paid on debts "to → from".
        [$debtor, $creditor] = $transaction->type === TransactionType::Refund ? [$to, $from] : [$from, $to];
        $open = $this->balances->openObligations($ledger, $debtor, $creditor)
            ->keyBy(fn (array $row): string => $row['obligation']->uuid);

        if ($transaction->type === TransactionType::Refund) {
            // Refunds apply only where something was paid.
            $open = Transaction::query()
                ->where('ledger_id', $ledger->id)
                ->where('type', TransactionType::Obligation->value)
                ->where('from_party_id', $debtor->id)
                ->where('to_party_id', $creditor->id)
                ->where('status', TransactionStatus::Confirmed->value)
                ->get()
                ->map(fn (Transaction $obligation): array => ['obligation' => $obligation, 'open' => $this->balances->obligation($obligation)['paid']])
                ->filter(fn (array $row): bool => $row['open'] > 0)
                ->keyBy(fn (array $row): string => $row['obligation']->uuid);
        }

        $plan = [];
        if ($requested === null) {
            $left = $amount;
            foreach ($open as $uuid => $row) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, $row['open']);
                $plan[$uuid] = $take;
                $left -= $take;
            }
        } else {
            $total = 0;
            foreach ($requested as $uuid => $value) {
                if ($value === '' || (string) $value === '0') {
                    continue;
                }
                $value = Toman::parse($value, 'allocations.'.$uuid);
                $row = $open->get((string) $uuid);
                if ($row === null) {
                    throw ValidationException::withMessages(['allocations.'.$uuid => __('bookkeeping.validation.allocation_not_open')]);
                }
                if ($value > $row['open']) {
                    throw ValidationException::withMessages(['allocations.'.$uuid => __('bookkeeping.validation.allocation_too_large', ['max' => Toman::format($row['open'])])]);
                }
                $plan[(string) $uuid] = $value;
                $total += $value;
            }
            if ($total > $amount) {
                throw ValidationException::withMessages(['allocations' => __('bookkeeping.validation.allocations_exceed')]);
            }
        }

        foreach ($plan as $uuid => $value) {
            if ($value <= 0) {
                continue;
            }
            Allocation::query()->create([
                'transaction_id' => $transaction->id,
                'obligation_id' => $open->get($uuid)['obligation']->id,
                'amount' => $value,
            ]);
        }
    }

    private function assertParties(Ledger $ledger, LedgerParty $from, LedgerParty $to, User $user, Actor $actor): void
    {
        foreach (['fromParty' => $from, 'toParty' => $to] as $field => $party) {
            if ((int) $party->ledger_id !== (int) $ledger->id || $party->status !== PartyStatus::Active) {
                throw ValidationException::withMessages([$field => __('bookkeeping.validation.party_not_in_book')]);
            }
        }
        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages(['toParty' => __('bookkeeping.validation.same_party')]);
        }

        // Members record only what they take part in; managers can record for anyone.
        $involved = in_array((int) $actor->id, [(int) $from->actor_id, (int) $to->actor_id], true);
        if (! $involved && ! Gate::forUser($user)->allows('manage', $ledger)) {
            throw ValidationException::withMessages(['fromParty' => __('bookkeeping.validation.must_be_involved')]);
        }
    }

    private function assertOwnWallet(Wallet $wallet, Actor $actor): void
    {
        $wallet->loadMissing('ledger');
        abort_unless(
            $wallet->ledger->kind === LedgerKind::Personal && (int) $wallet->ledger->owner_actor_id === (int) $actor->id && $wallet->archived_at === null,
            422,
            'Only your own wallets can be used.',
        );
    }

    private function existing(Actor $actor, string $key, Ledger $ledger): ?Transaction
    {
        $existing = Transaction::query()
            ->where('recorded_by_actor_id', $actor->id)
            ->where('idempotency_key', $key)
            ->first();

        abort_if($existing instanceof Transaction && (int) $existing->ledger_id !== (int) $ledger->id, 409, 'This request key was already used in another book.');

        return $existing;
    }
}
