<?php

namespace App\Actions\Exchange;

use App\Actions\Accounting\PostSystemJournalEntry;
use App\IetExchangeDirection;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\IetExchangeRequest;
use App\Models\Ledger;
use App\Models\User;
use App\Support\IetValuation;
use App\Support\IetWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitIetExchangeRequest
{
    public function __construct(
        private readonly IetValuation $valuation,
        private readonly IetWallet $wallet,
        private readonly PostSystemJournalEntry $post,
    ) {}

    public function execute(
        User $user,
        IetExchangeDirection $direction,
        int $usdAmountMinor,
        ?string $externalReference = null,
        ?string $note = null,
    ): IetExchangeRequest {
        abort_if($usdAmountMinor <= 0, 422, 'Exchange amount must be positive.');

        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        $externalReference = $externalReference !== null ? Str::squish($externalReference) : null;
        $note = trim((string) $note);

        abort_if($externalReference !== null && mb_strlen($externalReference) > 255, 422);
        abort_if(mb_strlen($note) > 5000, 422);

        $quote = $this->valuation->quoteUsdMinor($usdAmountMinor);

        return DB::transaction(function () use (
            $current,
            $direction,
            $usdAmountMinor,
            $externalReference,
            $note,
            $quote,
        ): IetExchangeRequest {
            $request = IetExchangeRequest::query()->create([
                'user_id' => $current->id,
                'direction' => $direction,
                'fiat_unit_code' => 'USD',
                'fiat_amount_minor' => $usdAmountMinor,
                'iet_valuation_snapshot_id' => $quote['snapshot']->id,
                'iet_amount_minor' => $quote['iet_minor'],
                'external_reference' => $externalReference !== '' ? $externalReference : null,
                'note' => $note !== '' ? $note : null,
            ]);

            if ($direction === IetExchangeDirection::Cashout) {
                $wallet = $this->wallet->ensure($current);

                Ledger::query()->whereKey($wallet['ledger']->id)->lockForUpdate()->firstOrFail();

                abort_if(
                    $this->wallet->accountBalanceMinor($wallet['cash']) < $quote['iet_minor'],
                    422,
                    'Insufficient available IET for this cashout request.',
                );

                $this->post->execute(
                    $wallet['ledger'],
                    $current->actor,
                    JournalEntryKind::IetExchangeCashout,
                    now()->toDateString(),
                    'Reserve IET for cashout request '.$request->uuid,
                    [
                        [
                            'account' => $wallet['exchange_sink'],
                            'debit_minor' => $quote['iet_minor'],
                            'memo' => 'Cashout reserve',
                        ],
                        [
                            'account' => $wallet['cash'],
                            'credit_minor' => $quote['iet_minor'],
                            'memo' => 'Cashout reserve',
                        ],
                    ],
                    actingUser: $current,
                    sourceType: 'iet_exchange_request',
                    sourceUuid: $request->uuid,
                    idempotencyKey: 'iet-exchange:'.$request->uuid.':cashout-reserve',
                );
            }

            return $request->fresh(['valuation', 'user.actor']);
        }, attempts: 3);
    }
}
