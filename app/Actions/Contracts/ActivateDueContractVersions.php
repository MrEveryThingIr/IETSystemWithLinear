<?php

namespace App\Actions\Contracts;

use App\ContractVersionStatus;
use App\Models\Contract;
use App\Models\ContractVersion;

class ActivateDueContractVersions
{
    public function __construct(private readonly ActivateContractVersion $activate) {}

    public function execute(): int
    {
        return $this->executeDueQuery();
    }

    public function executeForContract(Contract $contract): int
    {
        return $this->executeDueQuery($contract);
    }

    private function executeDueQuery(?Contract $contract = null): int
    {
        $count = 0;

        $query = ContractVersion::query()
            ->where('status', ContractVersionStatus::Accepted->value)
            ->where('effective_from', '<=', now());

        if ($contract instanceof Contract) {
            $query->where('contract_id', $contract->id);
        }

        $query
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get()
            ->each(function (ContractVersion $version) use (&$count): void {
                $activated = $this->activate->execute($version);

                if ($activated->status === ContractVersionStatus::Active) {
                    $count++;
                }
            });

        return $count;
    }
}
