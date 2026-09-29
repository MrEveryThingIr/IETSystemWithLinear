<?php

namespace App\Actions\Exchange;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\EnsureMonetaryUnit;
use App\ContextKind;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Ledger;
use App\Models\SystemContext;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Facades\DB;

class EnsureIetTreasury
{
    public function __construct(
        private readonly EnsureMonetaryUnit $units,
        private readonly CreateLedgerAccount $accounts,
    ) {}

    /**
     * @return array{
     *     ledger: Ledger,
     *     exchange_reserve: Account,
     *     service_advance_receivable: Account,
     *     settlement_clearing: Account,
     *     issuance: Account,
     *     fee_income: Account
     * }
     */
    public function execute(User $user): array
    {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        $unit = $this->units->execute('IET');

        return DB::transaction(function () use ($current, $unit): array {
            $binding = SystemContext::query()
                ->with('context')
                ->where('key', 'iet-treasury')
                ->first();

            if (! $binding instanceof SystemContext) {
                $context = Context::query()->create([
                    'kind' => ContextKind::System,
                ]);

                $binding = SystemContext::query()->create([
                    'context_id' => $context->id,
                    'key' => 'iet-treasury',
                    'name' => 'IET Treasury',
                ])->load('context');
            }

            $ledger = Ledger::query()
                ->where('context_id', $binding->context_id)
                ->where('monetary_unit_id', $unit->id)
                ->where('key', 'treasury')
                ->first();

            if (! $ledger instanceof Ledger) {
                $ledger = Ledger::query()->create([
                    'context_id' => $binding->context_id,
                    'monetary_unit_id' => $unit->id,
                    'key' => 'treasury',
                    'name' => 'IET System Treasury',
                    'created_by_actor_id' => $current->actor->id,
                ]);
            }

            $exchangeReserve = $this->accounts->execute(
                $ledger,
                $current,
                'External exchange reserve equivalent',
                AccountType::Asset,
                'iet_exchange_reserve',
            );

            $serviceAdvanceReceivable = $this->accounts->execute(
                $ledger,
                $current,
                'Service advance receivable',
                AccountType::Asset,
                'iet_service_advance_receivable',
            );

            $settlementClearing = $this->accounts->execute(
                $ledger,
                $current,
                'Settlement clearing',
                AccountType::Asset,
                'iet_settlement_clearing',
            );

            $issuance = $this->accounts->execute(
                $ledger,
                $current,
                'Net IET issuance',
                AccountType::Equity,
                'iet_issuance',
            );

            $feeIncome = $this->accounts->execute(
                $ledger,
                $current,
                'System fee income',
                AccountType::Income,
                'iet_fee_income',
            );

            return [
                'ledger' => $ledger->fresh(['context.systemBinding', 'monetaryUnit']),
                'exchange_reserve' => $exchangeReserve,
                'service_advance_receivable' => $serviceAdvanceReceivable,
                'settlement_clearing' => $settlementClearing,
                'issuance' => $issuance,
                'fee_income' => $feeIncome,
            ];
        }, attempts: 3);
    }
}
