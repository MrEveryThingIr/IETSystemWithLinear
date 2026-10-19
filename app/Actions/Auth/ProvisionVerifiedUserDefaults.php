<?php

namespace App\Actions\Auth;

use App\Actions\Bookkeeping\EnsurePersonalLedger;
use App\Models\Actor;
use App\Models\User;

class ProvisionVerifiedUserDefaults
{
    public function __construct(
        private readonly EnsurePersonalLedger $personalBooks,
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

        $this->personalBooks->execute($user);
    }
}
