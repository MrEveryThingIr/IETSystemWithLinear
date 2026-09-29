<?php

namespace App\Actions\Exchange;

use App\Actions\Accounting\PostJournalEntry;
use App\IetExchangeDirection;
use App\JournalEntryKind;
use App\Models\IetExchangeRequest;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use App\Support\AccountingSummary;

class PostIetTreasuryExchange
{
    public function __construct(
        private readonly EnsureIetTreasury $treasury,
        private readonly AccountingSummary $summary,
        private readonly PostJournalEntry $post,
    ) {}

    public function execute(IetExchangeRequest $request, User $reviewer): JournalEntry
    {
        $side = $this->treasury->execute($reviewer);
        $ledger = $side['ledger'];
        $reserve = $side['exchange_reserve'];
        $issuance = $side['issuance'];

        Ledger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();

        if ($request->direction === IetExchangeDirection::Cashout) {
            abort_if(
                $this->summary->accountBalanceMinor($reserve) < $request->iet_amount,
                422,
                'IET treasury exchange reserve is insufficient for this cash-out.',
            );
        }

        return $this->post->execute(
            $ledger,
            $reviewer,
            $request->direction === IetExchangeDirection::Deposit
                ? JournalEntryKind::ExchangeDeposit
                : JournalEntryKind::ExchangeCashout,
            now()->toDateString(),
            $request->direction === IetExchangeDirection::Deposit
                ? 'Treasury recognition of confirmed external funding'
                : 'Treasury retirement for confirmed IET cash-out',
            $request->direction === IetExchangeDirection::Deposit
                ? [
                    [
                        'account' => $reserve,
                        'debit_minor' => $request->iet_amount,
                        'memo' => 'External reserve equivalent backing issued IET',
                    ],
                    [
                        'account' => $issuance,
                        'credit_minor' => $request->iet_amount,
                        'memo' => 'IET issued into circulation',
                    ],
                ]
                : [
                    [
                        'account' => $issuance,
                        'debit_minor' => $request->iet_amount,
                        'memo' => 'IET retired from circulation',
                    ],
                    [
                        'account' => $reserve,
                        'credit_minor' => $request->iet_amount,
                        'memo' => 'External reserve equivalent released for cash-out',
                    ],
                ],
            sourceType: 'iet_exchange_request',
            sourceUuid: $request->uuid,
            idempotencyKey: 'iet-treasury-exchange:'.$request->uuid,
        );
    }
}
