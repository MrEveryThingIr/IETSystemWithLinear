<?php

namespace App\Actions\Exchange;

use App\Actions\Accounting\PostJournalEntry;
use App\JournalEntryKind;
use App\Models\IetInternalCharge;
use App\Models\Ledger;
use App\Models\User;
use App\Support\IetAvailableBalance;
use App\Support\IetPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChargeIetForUsdFlow
{
    public function __construct(
        private readonly IetPricing $pricing,
        private readonly EnsureIetWallet $wallets,
        private readonly IetAvailableBalance $available,
        private readonly PostJournalEntry $post,
    ) {}

    public function execute(
        User $user,
        int $usdAmountMinor,
        string $sourceType,
        string $sourceUuid,
        ?string $description = null,
    ): IetInternalCharge {
        abort_if($usdAmountMinor <= 0, 422, 'USD requirement must be positive.');
        abort_unless(Str::isUuid($sourceUuid), 422, 'Internal charge source UUID is invalid.');
        $sourceType = Str::squish($sourceType);
        abort_if($sourceType === '' || mb_strlen($sourceType) > 80, 422, 'Internal charge source type is invalid.');

        return DB::transaction(function () use (
            $user,
            $usdAmountMinor,
            $sourceType,
            $sourceUuid,
            $description,
        ): IetInternalCharge {
            $existing = IetInternalCharge::query()
                ->where('user_id', $user->id)
                ->where('source_type', $sourceType)
                ->where('source_uuid', $sourceUuid)
                ->first();

            if ($existing instanceof IetInternalCharge) {
                return $existing->load(['valuationQuote', 'journalEntry']);
            }

            $quote = $this->pricing->currentQuote();
            $ietAmount = $this->pricing->ietForUsdMinor($usdAmountMinor, $quote);
            $side = $this->wallets->execute($user);
            $ledger = $side['ledger'];

            Ledger::query()->whereKey($ledger->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $this->available->forUser($user, $side['wallet']) < $ietAmount,
                422,
                'Insufficient IET balance for this internal flow.',
            );

            $chargeUuid = (string) Str::uuid();

            $entry = $this->post->execute(
                $ledger,
                $user,
                JournalEntryKind::InternalCharge,
                now()->toDateString(),
                $description ?? 'IET internal flow charge',
                [
                    ['account' => $side['internal_expense'], 'debit_minor' => $ietAmount],
                    ['account' => $side['wallet'], 'credit_minor' => $ietAmount],
                ],
                sourceType: 'iet_internal_charge',
                sourceUuid: $chargeUuid,
                idempotencyKey: 'iet-charge:'.$user->id.':'.$sourceType.':'.$sourceUuid,
            );

            return IetInternalCharge::query()->create([
                'uuid' => $chargeUuid,
                'user_id' => $user->id,
                'source_type' => $sourceType,
                'source_uuid' => $sourceUuid,
                'usd_amount_minor' => $usdAmountMinor,
                'valuation_quote_id' => $quote->id,
                'iet_amount' => $ietAmount,
                'journal_entry_id' => $entry->id,
            ])->fresh(['valuationQuote', 'journalEntry']);
        }, attempts: 3);
    }
}
