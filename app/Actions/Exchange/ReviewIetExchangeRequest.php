<?php

namespace App\Actions\Exchange;

use App\IetExchangeDirection;
use App\JournalEntryKind;
use App\Models\IetExchangeRequest;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use App\PlatformCapability;
use App\Actions\Accounting\PostJournalEntry;
use App\Support\AccountingSummary;
use Illuminate\Support\Facades\DB;

class ReviewIetExchangeRequest
{
    public function __construct(
        private readonly EnsureIetWallet $wallets,
        private readonly AccountingSummary $summary,
        private readonly PostJournalEntry $post,
    ) {}

    public function confirm(IetExchangeRequest $request, User $reviewer): IetExchangeRequest
    {
        abort_unless($reviewer->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        return DB::transaction(function () use ($request, $reviewer): IetExchangeRequest {
            $locked = IetExchangeRequest::query()
                ->with(['user', 'valuationQuote'])
                ->lockForUpdate()
                ->findOrFail($request->id);

            abort_unless($locked->status->value === 'pending', 422);

            $side = $this->wallets->execute($locked->user);
            $ledger = $side['ledger'];
            $wallet = $side['wallet'];
            $funding = $side['funding'];

            Ledger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();

            if ($locked->direction === IetExchangeDirection::Cashout) {
                abort_if(
                    $this->summary->accountBalanceMinor($wallet) < $locked->iet_amount,
                    422,
                    'Insufficient IET balance at confirmation time.',
                );
            }

            $entry = $this->post->execute(
                $ledger,
                $locked->user,
                $locked->direction === IetExchangeDirection::Deposit
                    ? JournalEntryKind::ExchangeDeposit
                    : JournalEntryKind::ExchangeCashout,
                now()->toDateString(),
                $locked->direction === IetExchangeDirection::Deposit
                    ? 'Confirmed IET deposit'
                    : 'Confirmed IET cash-out',
                $locked->direction === IetExchangeDirection::Deposit
                    ? [
                        ['account' => $wallet, 'debit_minor' => $locked->iet_amount],
                        ['account' => $funding, 'credit_minor' => $locked->iet_amount],
                    ]
                    : [
                        ['account' => $funding, 'debit_minor' => $locked->iet_amount],
                        ['account' => $wallet, 'credit_minor' => $locked->iet_amount],
                    ],
                sourceType: 'iet_exchange_request',
                sourceUuid: $locked->uuid,
                idempotencyKey: 'iet-exchange:'.$locked->uuid,
            );

            $locked->confirm($reviewer, $entry);

            return $locked->fresh(['valuationQuote', 'journalEntry', 'reviewer']);
        }, attempts: 3);
    }

    public function reject(IetExchangeRequest $request, User $reviewer, ?string $note = null): IetExchangeRequest
    {
        abort_unless($reviewer->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        return DB::transaction(function () use ($request, $reviewer, $note): IetExchangeRequest {
            $locked = IetExchangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $locked->reject($reviewer, $note);

            return $locked->fresh(['valuationQuote', 'reviewer']);
        });
    }
}
