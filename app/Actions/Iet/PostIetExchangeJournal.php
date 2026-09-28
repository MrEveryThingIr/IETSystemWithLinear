<?php

namespace App\Actions\Iet;

use App\IetExchangeDirection;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\IetExchangeRequest;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PostIetExchangeJournal
{
    public function __construct(private readonly EnsureIetWallet $wallets) {}

    public function execute(IetExchangeRequest $request, User $reviewer): JournalEntry
    {
        $owner = User::query()->with('actor')->findOrFail($request->user_id);
        $reviewer->loadMissing('actor');

        abort_unless($owner->actor instanceof Actor && $reviewer->actor instanceof Actor, 403);

        $side = $this->wallets->execute($owner);
        $ledger = $side['ledger'];
        $wallet = $side['wallet'];
        $clearing = $side['clearing'];

        return DB::transaction(function () use (
            $request,
            $reviewer,
            $ledger,
            $wallet,
            $clearing,
        ): JournalEntry {
            Ledger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();

            $existing = JournalEntry::query()
                ->where('ledger_id', $ledger->id)
                ->where('idempotency_key', 'iet-exchange:'.$request->uuid)
                ->first();

            if ($existing instanceof JournalEntry) {
                return $existing;
            }

            $deposit = $request->direction === IetExchangeDirection::Deposit;

            $entry = JournalEntry::query()->create([
                'ledger_id' => $ledger->id,
                'kind' => $deposit
                    ? JournalEntryKind::IetExchangeDeposit
                    : JournalEntryKind::IetExchangeCashout,
                'occurred_on' => now()->toDateString(),
                'description' => $deposit
                    ? 'Confirmed IET deposit request'
                    : 'Confirmed IET cashout request',
                'source_type' => 'iet_exchange_request',
                'source_uuid' => $request->uuid,
                'idempotency_key' => 'iet-exchange:'.$request->uuid,
                'created_by_actor_id' => $reviewer->actor->id,
                'acting_user_id' => $reviewer->id,
                'posted_at' => now(),
            ]);

            if ($deposit) {
                $entry->lines()->create([
                    'account_id' => $wallet->id,
                    'debit_minor' => $request->iet_amount_minor,
                    'credit_minor' => 0,
                    'memo' => 'IET exchange deposit',
                ]);
                $entry->lines()->create([
                    'account_id' => $clearing->id,
                    'debit_minor' => 0,
                    'credit_minor' => $request->iet_amount_minor,
                    'memo' => 'IET exchange clearing',
                ]);
            } else {
                $entry->lines()->create([
                    'account_id' => $clearing->id,
                    'debit_minor' => $request->iet_amount_minor,
                    'credit_minor' => 0,
                    'memo' => 'IET cashout clearing',
                ]);
                $entry->lines()->create([
                    'account_id' => $wallet->id,
                    'debit_minor' => 0,
                    'credit_minor' => $request->iet_amount_minor,
                    'memo' => 'IET exchange cashout',
                ]);
            }

            return $entry->fresh(['ledger.monetaryUnit', 'lines.account']);
        }, attempts: 3);
    }
}
