<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Ledger;
use App\Models\SystemContext;
use App\Models\User;
use App\PlatformCapability;

final class IetTreasuryMetrics
{
    public function __construct(
        private readonly AccountingSummary $summary,
    ) {}

    /**
     * @return array{
     *     exchange_reserve: int,
     *     service_advance_receivable: int,
     *     settlement_clearing: int,
     *     net_issuance: int,
     *     fee_income: int
     * }
     */
    public function snapshot(User $user): array
    {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $binding = SystemContext::query()
            ->with('context')
            ->where('key', 'iet-treasury')
            ->first();

        if (! $binding instanceof SystemContext) {
            return $this->empty();
        }

        $ledger = Ledger::query()
            ->with('accounts')
            ->where('context_id', $binding->context_id)
            ->where('key', 'treasury')
            ->first();

        if (! $ledger instanceof Ledger) {
            return $this->empty();
        }

        return [
            'exchange_reserve' => $this->balance($ledger, 'iet_exchange_reserve'),
            'service_advance_receivable' => $this->balance($ledger, 'iet_service_advance_receivable'),
            'settlement_clearing' => $this->balance($ledger, 'iet_settlement_clearing'),
            'net_issuance' => $this->balance($ledger, 'iet_issuance'),
            'fee_income' => $this->balance($ledger, 'iet_fee_income'),
        ];
    }

    /**
     * @return array{
     *     exchange_reserve: int,
     *     service_advance_receivable: int,
     *     settlement_clearing: int,
     *     net_issuance: int,
     *     fee_income: int
     * }
     */
    private function empty(): array
    {
        return [
            'exchange_reserve' => 0,
            'service_advance_receivable' => 0,
            'settlement_clearing' => 0,
            'net_issuance' => 0,
            'fee_income' => 0,
        ];
    }

    private function balance(Ledger $ledger, string $systemKey): int
    {
        $account = $ledger->accounts->firstWhere('system_key', $systemKey);

        return $account instanceof Account
            ? $this->summary->accountBalanceMinor($account)
            : 0;
    }
}
