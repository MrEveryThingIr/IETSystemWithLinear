<?php

namespace App\Actions\Bookkeeping;

use App\Enums\Bookkeeping\CategoryDirection;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Bookkeeping\Category;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Bookkeeping\Wallet;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingRecorder;
use App\Support\Bookkeeping\EntryDate;
use App\Support\Bookkeeping\Toman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Personal book: money in, money out, or moved between one's own wallets.
 * Only the owner sees it, so it counts immediately. Mistakes are fixed by
 * recording the opposite entry (or "undo", which records it for you).
 */
class RecordPersonalEntry
{
    public function __construct(private readonly BookkeepingRecorder $recorder) {}

    public function execute(
        Ledger $ledger,
        User $user,
        TransactionType $type,
        int|string $amount,
        string $occurredOn,
        Wallet $wallet,
        ?Category $category = null,
        ?Wallet $toWallet = null,
        ?string $counterpartyName = null,
        ?string $description = null,
        ?string $idempotencyKey = null,
        ?Transaction $reverses = null,
    ): Transaction {
        abort_unless($ledger->kind === LedgerKind::Personal && $type->isPersonal(), 422);
        Gate::forUser($user)->authorize('record', $ledger);

        $amount = Toman::parse($amount);
        $occurred = EntryDate::parse($occurredOn, 'occurredOn');
        $actor = $this->recorder->actor($user);

        if ($idempotencyKey !== null) {
            $existing = Transaction::query()->where('recorded_by_actor_id', $actor->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof Transaction) {
                return $existing;
            }
        }

        $this->assertWallet($ledger, $wallet, 'walletUuid');
        if ($type === TransactionType::Transfer) {
            if (! $toWallet instanceof Wallet) {
                throw ValidationException::withMessages(['toWalletUuid' => __('bookkeeping.validation.to_wallet_required')]);
            }
            $this->assertWallet($ledger, $toWallet, 'toWalletUuid');
            if ((int) $toWallet->id === (int) $wallet->id) {
                throw ValidationException::withMessages(['toWalletUuid' => __('bookkeeping.validation.same_wallet')]);
            }
            $category = null;
        }
        if ($category instanceof Category) {
            $expected = $type === TransactionType::Income ? CategoryDirection::Income : CategoryDirection::Expense;
            abort_unless((int) $category->ledger_id === (int) $ledger->id && $category->direction === $expected, 422, 'Choose a category of this book.');
        }

        $counterpartyName = $counterpartyName !== null ? Str::limit(Str::squish($counterpartyName), 120, '') : null;
        $description = $description !== null ? Str::limit(Str::squish($description), 500, '') : null;

        return DB::transaction(function () use ($ledger, $actor, $type, $amount, $occurred, $wallet, $toWallet, $category, $counterpartyName, $description, $idempotencyKey, $reverses): Transaction {
            $entry = Transaction::query()->create([
                'ledger_id' => $ledger->id,
                'type' => $type,
                'status' => TransactionStatus::Confirmed,
                'amount' => $amount,
                'occurred_on' => $occurred->toDateString(),
                'description' => $description !== '' ? $description : null,
                'wallet_id' => $wallet->id,
                'counter_wallet_id' => $type === TransactionType::Transfer ? $toWallet->id : null,
                'category_id' => $category?->id,
                'counterparty_name' => $counterpartyName !== '' ? $counterpartyName : null,
                'related_transaction_id' => $reverses?->id,
                'recorded_by_actor_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'confirmed_at' => now(),
                'meta' => ['source' => $reverses !== null ? 'undo' : 'manual'],
            ]);

            $this->recorder->audit($ledger, $actor, $reverses !== null ? 'entry.undone' : 'entry.recorded', $entry, after: BookkeepingRecorder::snapshot($entry));

            return $entry;
        }, 3);
    }

    /** Undo a personal entry by recording its opposite; both stay visible. */
    public function undo(Transaction $entry, User $user): Transaction
    {
        $entry->loadMissing('ledger', 'wallet', 'counterWallet', 'category');
        abort_unless($entry->type->isPersonal() && $entry->status === TransactionStatus::Confirmed, 422);
        abort_if(Transaction::query()->where('related_transaction_id', $entry->id)->exists() || $entry->related_transaction_id !== null, 422, 'This entry was already undone.');
        abort_unless($entry->wallet instanceof Wallet, 422);

        [$type, $from, $to] = match ($entry->type) {
            TransactionType::Income => [TransactionType::Expense, $entry->wallet, null],
            TransactionType::Expense => [TransactionType::Income, $entry->wallet, null],
            default => [TransactionType::Transfer, $entry->counterWallet ?? $entry->wallet, $entry->wallet],
        };

        $category = null;
        if ($entry->type !== TransactionType::Transfer) {
            $direction = $type === TransactionType::Income ? CategoryDirection::Income : CategoryDirection::Expense;
            $category = Category::query()->firstOrCreate(
                ['ledger_id' => $entry->ledger_id, 'direction' => $direction->value, 'name' => 'correction'],
                ['system_key' => 'correction'],
            );
        }

        return $this->execute(
            $entry->ledger,
            $user,
            $type,
            (int) $entry->amount,
            $entry->occurred_on->toDateString(),
            $from,
            $category,
            $to,
            $entry->counterparty_name,
            (string) __('bookkeeping.personal.undo_description'),
            'undo:'.$entry->uuid,
            $entry,
        );
    }

    private function assertWallet(Ledger $ledger, Wallet $wallet, string $field): void
    {
        if ((int) $wallet->ledger_id !== (int) $ledger->id || $wallet->archived_at !== null) {
            throw ValidationException::withMessages([$field => __('bookkeeping.validation.wallet')]);
        }
    }
}
