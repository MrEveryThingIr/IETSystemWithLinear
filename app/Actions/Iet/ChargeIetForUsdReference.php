<?php

namespace App\Actions\Iet;

use App\Actions\Accounting\PostJournalEntry;
use App\JournalEntryKind;
use App\Models\IetFlowCharge;
use App\Models\User;
use App\Support\IetPricing;
use App\Support\IetWalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChargeIetForUsdReference
{
    public function __construct(
        private readonly EnsureIetWallet $wallets,
        private readonly IetWalletBalance $balances,
        private readonly IetPricing $pricing,
        private readonly PostJournalEntry $post,
    ) {}

    public function execute(
        User $user,
        int $usdReferenceMinor,
        string $sourceType,
        string $sourceUuid,
        ?string $description = null,
    ): IetFlowCharge {
        abort_unless(Str::isUuid($sourceUuid), 422, 'IET flow source UUID is invalid.');
        $sourceType = trim($sourceType);
        abort_if($sourceType === '' || mb_strlen($sourceType) > 80, 422, 'IET flow source type is invalid.');

        $existing = IetFlowCharge::query()
            ->where('user_id', $user->id)
            ->where('source_type', $sourceType)
            ->where('source_uuid', $sourceUuid)
            ->first();

        if ($existing instanceof IetFlowCharge) {
            return $existing;
        }

        $quote = $this->pricing->quoteUsdMinor($usdReferenceMinor);
        $this->balances->assertAtLeast($user, $quote['iet_minor']);
        $side = $this->wallets->execute($user);

        return DB::transaction(function () use (
            $user,
            $quote,
            $sourceType,
            $sourceUuid,
            $description,
            $side,
        ): IetFlowCharge {
            $already = IetFlowCharge::query()
                ->where('user_id', $user->id)
                ->where('source_type', $sourceType)
                ->where('source_uuid', $sourceUuid)
                ->lockForUpdate()
                ->first();

            if ($already instanceof IetFlowCharge) {
                return $already;
            }

            $this->balances->assertAtLeast($user, $quote['iet_minor']);

            $expense = $side['ledger']->accounts()->where('system_key', 'general_expense')->firstOrFail();

            $entry = $this->post->execute(
                $side['ledger'],
                $user,
                JournalEntryKind::IetFlowCharge,
                now()->toDateString(),
                $description ?? 'IET internal flow charge',
                [
                    [
                        'account' => $expense,
                        'debit_minor' => $quote['iet_minor'],
                        'memo' => 'IET flow charge',
                    ],
                    [
                        'account' => $side['wallet'],
                        'credit_minor' => $quote['iet_minor'],
                        'memo' => 'IET wallet charge',
                    ],
                ],
                sourceType: $sourceType,
                sourceUuid: $sourceUuid,
                idempotencyKey: 'iet-flow:'.$user->id.':'.$sourceType.':'.$sourceUuid,
            );

            return IetFlowCharge::query()->create([
                'user_id' => $user->id,
                'rate_version_id' => $quote['rate']->id,
                'usd_reference_minor' => $quote['usd_minor'],
                'iet_amount_minor' => $quote['iet_minor'],
                'source_type' => $sourceType,
                'source_uuid' => $sourceUuid,
                'description' => $description,
                'journal_entry_id' => $entry->id,
            ]);
        }, attempts: 3);
    }
}
