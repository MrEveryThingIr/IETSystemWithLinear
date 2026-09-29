<?php

namespace App\Actions\Auth;

use App\Actions\Accounting\CreatePersonalLedger;
use App\Models\Actor;
use App\Models\User;

class ProvisionVerifiedUserDefaults
{
    public function __construct(
        private readonly CreatePersonalLedger $ledgers,
    ) {}

    public function execute(User $user): void
    {
        $user = User::query()->with('actor')->find($user->id);

        if (! $user instanceof User
            || $user->status !== 'active'
            || $user->email_verified_at === null
            || ! $user->actor instanceof Actor
            || $user->actor->status !== 'active') {
            return;
        }

        $code = strtoupper(trim((string) $user->default_monetary_unit_code));
        $this->ledgers->execute($user, $code !== '' ? $code : 'USD');
    }
}
