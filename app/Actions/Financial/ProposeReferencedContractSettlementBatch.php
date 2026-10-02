<?php

namespace App\Actions\Financial;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Models\Contract;
use App\Models\ContractSettlementBatch;
use App\Models\User;
use App\Support\IetReferencePricing;
use Carbon\CarbonInterface;

final class ProposeReferencedContractSettlementBatch
{
    public function __construct(
        private readonly IetReferencePricing $pricing,
        private readonly EnsureMonetaryUnit $units,
        private readonly ProposeContractSettlementBatch $batches,
    ) {}

    public function execute(
        Contract $contract,
        User $user,
        string $referenceUnitCode,
        int $referenceAmountMinor,
        CarbonInterface $paidAt,
        ?string $reference = null,
        ?string $note = null,
        string $perspective = 'paid',
    ): ContractSettlementBatch {
        $priced = $this->pricing->quote($referenceUnitCode, $referenceAmountMinor);
        $iet = $this->units->execute('IET');

        return $this->batches->execute(
            $contract,
            $user,
            $iet,
            $priced['iet_amount'],
            $paidAt,
            'external_cash',
            $reference,
            $note,
            $perspective,
            $priced['reference_unit'],
            $priced['reference_amount_minor'],
            $priced['usd_amount_minor'],
            $priced['market_quote'],
            $priced['iet_quote'],
        );
    }
}
