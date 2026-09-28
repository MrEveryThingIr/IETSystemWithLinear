<?php

namespace App\Actions\Iet;

use App\Actions\Accounting\CreatePersonalLedger;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;

final class EnsureIetWallet
{
    public function __construct(
        private readonly EnsureIetEconomy $economy,
        private readonly CreatePersonalLedger $ledgers,
    ) {}

    /** @return array{ledger:Ledger,wallet:Account,clearing:Account} */
    public function execute(User $user): array
    {
        $this->economy->execute();

        $ledger = $this->ledgers->execute($user, 'IET', 'IET Wallet');
        $ledger->loadMissing('accounts', 'monetaryUnit');

        $wallet = $ledger->accounts->firstWhere('system_key', 'cash');
        $clearing = $ledger->accounts->firstWhere('system_key', 'opening_equity');

        abort_unless($wallet instanceof Account && $clearing instanceof Account, 500);

        if ($wallet->name !== 'IET Wallet') {
            $wallet->forceFill(['name' => 'IET Wallet'])->save();
        }

        if ($clearing->name !== 'IET Exchange clearing') {
            $clearing->forceFill(['name' => 'IET Exchange clearing'])->save();
        }

        return compact('ledger', 'wallet', 'clearing');
    }
}
