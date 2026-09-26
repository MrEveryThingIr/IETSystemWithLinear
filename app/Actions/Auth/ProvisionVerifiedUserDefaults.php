<?php

namespace App\Actions\Auth;

use App\Actions\Accounting\CreatePersonalLedger;
use App\Models\Actor;
use App\Models\Ledger;
use App\Models\User;
use App\Support\MonetaryUnitCatalog;

class ProvisionVerifiedUserDefaults
{
    public function __construct(private readonly CreatePersonalLedger $ledgers) {}

    public function execute(User $user): ?Ledger
    {
        $user->loadMissing('actor');

        if (! $user->actor instanceof Actor) {
            return null;
        }

        $code = strtoupper(trim((string) $user->default_monetary_unit_code));

        if (! array_key_exists($code, MonetaryUnitCatalog::all())) {
            $code = 'EUR';
            $user->forceFill(['default_monetary_unit_code' => $code])->save();
        }

        return $this->ledgers->execute($user, $code);
    }
}
