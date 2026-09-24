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

class PostJournalEntry
{
    /**
     * @param  list<array{account: Account, debit_minor?: int, credit_minor?: int, memo?: ?string}>  $lines
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
    ): JournalEntry {
        Gate::forUser($user)->authorize('manage', $ledger);

        $actor = $this->actor($user);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $occurredOn);
        abort_unless($date !== null && $date->format('Y-m-d') === $occurredOn, 422, 'Invalid accounting date.');
        abort_if(count($lines) < 2, 422, 'A Journal Entry requires at least two lines.');

        $description = trim((string) $description);
        abort_if(mb_strlen($description) > 500, 422, 'Journal Entry description is too long.');

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
            $actor,
            $kind,
            $occurredOn,
            $description,
            $normalized,
            $reverses,
            $correctionOf,
        ): JournalEntry {
            Ledger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();

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
                'reverses_entry_id' => $reverses?->id,
                'correction_of_entry_id' => $correctionOf?->id,
                'created_by_actor_id' => $actor->id,
                'posted_at' => now(),
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            return $entry->fresh(['ledger.monetaryUnit', 'lines.account', 'creator.user']);
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
