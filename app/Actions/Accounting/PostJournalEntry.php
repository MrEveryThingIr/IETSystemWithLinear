<?php

namespace App\Actions\Accounting;

use App\JournalEntryKind;
use App\Models\Account;
use App\Models\Actor;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PostJournalEntry
{
    /**
     * @param list<array{account: Account, debit_minor?: int, credit_minor?: int, memo?: ?string}> $lines
     */
    public function execute(
        Ledger $ledger,
        User $user,
        JournalEntryKind $kind,
        string $occurredOn,
        ?string $description,
        array $lines,
        ?JournalEntry $reverses = null,
        ?JournalEntry $correctionOf = null,
        ?string $sourceType = null,
        ?string $sourceUuid = null,
        ?string $idempotencyKey = null,
    ): JournalEntry {
        Gate::forUser($user)->authorize('manage', $ledger);

        $actor = $this->actor($user);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $occurredOn);
        abort_unless($date !== null && $date->format('Y-m-d') === $occurredOn, 422, 'Invalid accounting date.');
        abort_if(count($lines) < 2, 422, 'A Journal Entry requires at least two lines.');

        $description = trim((string) $description);
        abort_if(mb_strlen($description) > 500, 422, 'Journal Entry description is too long.');

        $sourceType = $sourceType !== null ? Str::squish($sourceType) : null;
        $sourceUuid = $sourceUuid !== null ? trim($sourceUuid) : null;
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;

        abort_if($sourceType !== null && ($sourceType === '' || mb_strlen($sourceType) > 80), 422, 'Invalid Journal Entry source type.');
        abort_if($sourceUuid !== null && ! Str::isUuid($sourceUuid), 422, 'Invalid Journal Entry source UUID.');
        abort_if(
            ($sourceType === null) !== ($sourceUuid === null),
            422,
            'Journal Entry source type and UUID must be provided together.',
        );
        abort_if($idempotencyKey !== null && ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 190), 422, 'Invalid Journal Entry idempotency key.');

        $debits = 0;
        $credits = 0;
        $normalized = [];

        foreach ($lines as $line) {
            $account = $line['account'];
            abort_unless((int) $account->ledger_id === (int) $ledger->id, 422, 'All Accounts must belong to this Ledger.');
            abort_unless($account->status === 'active', 422, 'Archived Accounts cannot receive new Journal Lines.');

            $debit = (int) ($line['debit_minor'] ?? 0);
            $credit = (int) ($line['credit_minor'] ?? 0);
            abort_unless(($debit > 0) xor ($credit > 0), 422, 'Each line needs exactly one positive debit or credit.');

            $debits += $debit;
            $credits += $credit;

            $normalized[] = [
                'account_id' => $account->id,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'memo' => isset($line['memo']) ? trim((string) $line['memo']) : null,
            ];
        }

        abort_unless($debits > 0 && $debits === $credits, 422, 'Journal Entry is not balanced.');

        if ($reverses instanceof JournalEntry) {
            abort_unless((int) $reverses->ledger_id === (int) $ledger->id, 422, 'Reversal target must belong to this Ledger.');
        }

        if ($correctionOf instanceof JournalEntry) {
            abort_unless((int) $correctionOf->ledger_id === (int) $ledger->id, 422, 'Correction target must belong to this Ledger.');
        }

        return DB::transaction(function () use (
            $ledger,
            $user,
            $actor,
            $kind,
            $occurredOn,
            $description,
            $normalized,
            $reverses,
            $correctionOf,
            $sourceType,
            $sourceUuid,
            $idempotencyKey,
        ): JournalEntry {
            Ledger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = JournalEntry::query()
                    ->where('ledger_id', $ledger->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof JournalEntry) {
                    return $existing->load(['ledger.monetaryUnit', 'lines.account', 'creator.user', 'actingUser']);
                }
            }

            if ($reverses instanceof JournalEntry) {
                abort_if(
                    JournalEntry::query()->where('reverses_entry_id', $reverses->id)->exists(),
                    422,
                    'This Journal Entry has already been reversed.',
                );
            }

            $entry = JournalEntry::query()->create([
                'ledger_id' => $ledger->id,
                'kind' => $kind,
                'occurred_on' => $occurredOn,
                'description' => $description !== '' ? $description : null,
                'source_type' => $sourceType,
                'source_uuid' => $sourceUuid,
                'idempotency_key' => $idempotencyKey,
                'reverses_entry_id' => $reverses?->id,
                'correction_of_entry_id' => $correctionOf?->id,
                'created_by_actor_id' => $actor->id,
                'acting_user_id' => $user->id,
                'posted_at' => now(),
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            return $entry->fresh([
                'ledger.monetaryUnit',
                'lines.account',
                'creator.user',
                'actingUser',
            ]);
        }, attempts: 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current->actor;
    }
}
