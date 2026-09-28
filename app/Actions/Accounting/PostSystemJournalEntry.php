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
use Illuminate\Support\Str;

class PostSystemJournalEntry
{
    /**
     * @param  list<array{account: Account, debit_minor?: int, credit_minor?: int, memo?: ?string}>  $lines
     */
    public function execute(
        Ledger $ledger,
        Actor $actor,
        JournalEntryKind $kind,
        string $occurredOn,
        ?string $description,
        array $lines,
        ?User $actingUser = null,
        ?string $sourceType = null,
        ?string $sourceUuid = null,
        ?string $idempotencyKey = null,
    ): JournalEntry {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $occurredOn);
        abort_unless($date !== null && $date->format('Y-m-d') === $occurredOn, 422, 'Invalid accounting date.');
        abort_if(count($lines) < 2, 422, 'A Journal Entry requires at least two lines.');

        $description = trim((string) $description);
        abort_if(mb_strlen($description) > 500, 422, 'Journal Entry description is too long.');

        $sourceType = $sourceType !== null ? Str::squish($sourceType) : null;
        $sourceUuid = $sourceUuid !== null ? trim($sourceUuid) : null;
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;

        abort_if($sourceType !== null && ($sourceType === '' || mb_strlen($sourceType) > 80), 422);
        abort_unless($sourceUuid === null || Str::isUuid($sourceUuid), 422);
        abort_if(($sourceType === null) !== ($sourceUuid === null), 422);
        abort_if($idempotencyKey !== null && ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 190), 422);

        $debits = 0;
        $credits = 0;
        $normalized = [];

        foreach ($lines as $line) {
            $account = $line['account'];
            abort_unless((int) $account->ledger_id === (int) $ledger->id, 422);
            abort_unless($account->status === 'active', 422);

            $debit = (int) ($line['debit_minor'] ?? 0);
            $credit = (int) ($line['credit_minor'] ?? 0);
            abort_unless(($debit > 0) xor ($credit > 0), 422);

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

        return DB::transaction(function () use (
            $ledger,
            $actor,
            $kind,
            $occurredOn,
            $description,
            $normalized,
            $actingUser,
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
                    return $existing->load(['ledger.monetaryUnit', 'lines.account']);
                }
            }

            $entry = JournalEntry::query()->create([
                'ledger_id' => $ledger->id,
                'kind' => $kind,
                'occurred_on' => $occurredOn,
                'description' => $description !== '' ? $description : null,
                'source_type' => $sourceType,
                'source_uuid' => $sourceUuid,
                'idempotency_key' => $idempotencyKey,
                'created_by_actor_id' => $actor->id,
                'acting_user_id' => $actingUser?->id,
                'posted_at' => now(),
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            return $entry->fresh(['ledger.monetaryUnit', 'lines.account']);
        }, attempts: 3);
    }
}
