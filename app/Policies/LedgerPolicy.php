<?php

namespace App\Policies;

use App\ContextKind;
use App\Models\Ledger;
use App\Models\User;

class LedgerPolicy
{
    public function __construct(private readonly ContextPolicy $contexts) {}

    public function view(User $user, Ledger $ledger): bool
    {
        $ledger->loadMissing('context');

        return $ledger->context->kind === ContextKind::Personal
            && $this->contexts->view($user, $ledger->context);
    }

    public function manage(User $user, Ledger $ledger): bool
    {
        $ledger->loadMissing('context');

        return $ledger->status === 'active'
            && $ledger->context->kind === ContextKind::Personal
            && $this->contexts->manageContent($user, $ledger->context);
    }
}
