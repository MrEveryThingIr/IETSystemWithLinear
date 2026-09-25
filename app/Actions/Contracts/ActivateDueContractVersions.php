<?php

namespace App\Actions\Contracts;

use App\ContractVersionStatus;
use App\Models\ContractVersion;

class ActivateDueContractVersions
{
    public function __construct(private readonly ActivateContractVersion $activate) {}

    public function execute(): int
    {
        $count = 0;

        ContractVersion::query()
            ->where('status', ContractVersionStatus::Accepted->value)
            ->where('effective_from', '<=', now())
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
