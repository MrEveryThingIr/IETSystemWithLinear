<?php

namespace App\Support;

use App\Models\Ledger;

final class IetLedgerGuard
{
    public static function rejectManualMutation(Ledger $ledger): void
    {
        $ledger->loadMissing('monetaryUnit');

        abort_if(
            $ledger->monetaryUnit->code === 'IET',
            422,
            'IET wallet entries require dedicated Exchange, internal-flow, or Settlement actions.',
        );
    }
}
