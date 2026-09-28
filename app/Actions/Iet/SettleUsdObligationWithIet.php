<?php

namespace App\Actions\Iet;

use App\Actions\Financial\PostFinancialObligationAccounting;
use App\Actions\Financial\PostSettlementAccounting;
use App\Actions\Financial\ProposeSettlement;
use App\Actions\Financial\RespondToSettlement;
use App\Models\FinancialObligation;
use App\Models\Settlement;
use App\Models\User;
use App\Support\IetPricing;
use Carbon\CarbonImmutable;

final class SettleUsdObligationWithIet
{
    public function __construct(
        private readonly IetPricing $pricing,
        private readonly TransferIet $transfer,
        private readonly ProposeSettlement $propose,
        private readonly RespondToSettlement $respond,
        private readonly PostFinancialObligationAccounting $postObligation,
        private readonly PostSettlementAccounting $postSettlement,
    ) {}

    public function execute(
        FinancialObligation $obligation,
        User $debtor,
        int $usdMinor,
    ): Settlement {
        $obligation->loadMissing(['debtor.user', 'creditor.user', 'monetaryUnit', 'fulfillment']);

        abort_unless($obligation->monetaryUnit->code === 'USD', 422, __('iet.validation.usd_obligation_only'));
        abort_unless((int) $obligation->debtor->user_id === (int) $debtor->id, 403);
        abort_if($usdMinor <= 0 || $usdMinor > $obligation->outstandingMinor(), 422, __('financial.validation.amount'));

        $creditor = $obligation->creditor->user;
        abort_unless($creditor instanceof User, 422, __('iet.validation.receiver'));

        $quote = $this->pricing->quoteUsdMinor($usdMinor);

        $internal = $this->transfer->execute(
            $debtor,
            $creditor,
            $quote['iet_minor'],
            $quote['rate'],
            $usdMinor,
            'financial_obligation',
            $obligation->uuid,
            'IET settlement for USD obligation '.$obligation->uuid,
        );

        $settlement = $this->propose->execute(
            $obligation,
            $debtor,
            $usdMinor,
            CarbonImmutable::now()->subSecond(),
            'IET internal transfer',
            $internal->uuid,
            'System-verified IET transfer at rate version #'.$quote['rate']->sequence,
        );

        $settlement = $this->respond->confirm($settlement, $creditor);

        $this->postObligation->execute($obligation, $debtor);
        $this->postObligation->execute($obligation, $creditor);
        $this->postSettlement->execute($settlement, $debtor);
        $this->postSettlement->execute($settlement, $creditor);

        return $settlement->fresh([
            'obligation',
            'proposedBy.user',
            'confirmedBy.user',
        ]);
    }
}
