<?php

namespace App\Actions\Accounting;

use App\AccountType;
use App\Actions\Contexts\EnsurePersonalContext;
use App\ContextKind;
use App\Models\Actor;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreatePersonalLedger
{
    public function __construct(
        private readonly EnsurePersonalContext $contexts,
        private readonly EnsureMonetaryUnit $units,
        private readonly CreateLedgerAccount $accounts,
    ) {}

    public function execute(User $user, string $unitCode, ?string $name = null): Ledger
    {
        $actor = $this->actor($user);
        $context = $this->contexts->execute($user);
        abort_unless($context->kind === ContextKind::Personal, 422);
        Gate::forUser($user)->authorize('manageContent', $context);

        $unit = $this->units->execute($unitCode);

        return DB::transaction(function () use ($context, $unit, $actor, $user, $name): Ledger {
            $existing = Ledger::query()
                ->with('monetaryUnit')
                ->where('context_id', $context->id)
                ->where('monetary_unit_id', $unit->id)
                ->where('key', 'main')
                ->first();

            if ($existing instanceof Ledger) {
                return $existing;
            }

            $ledger = Ledger::query()->create([
                'context_id' => $context->id,
                'monetary_unit_id' => $unit->id,
                'key' => 'main',
                'name' => trim((string) $name) !== '' ? trim((string) $name) : "Personal {$unit->code}",
                'created_by_actor_id' => $actor->id,
            ]);

            $this->accounts->execute($ledger, $user, 'Cash', AccountType::Asset, 'cash');
            $this->accounts->execute($ledger, $user, 'Opening balance', AccountType::Equity, 'opening_equity');
            $this->accounts->execute($ledger, $user, 'General income', AccountType::Income, 'general_income');
            $this->accounts->execute($ledger, $user, 'General expense', AccountType::Expense, 'general_expense');

            return $ledger->fresh(['context', 'monetaryUnit', 'accounts']);
        }, attempts: 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current->actor;
    }
}
