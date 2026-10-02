<?php

namespace App\Support;

use App\Actions\Exchange\EnsureIetWallet;
use App\Models\FinancialObligation;
use App\Models\MonetaryUnit;
use App\Models\Settlement;
use App\Models\User;
use App\SettlementStatus;

final class IetPosition
{
    public function __construct(
        private readonly EnsureIetWallet $wallets,
        private readonly AccountingSummary $accounting,
        private readonly IetAvailableBalance $available,
    ) {}

    /**
     * @return array{
     *   wallet_minor:int,
     *   receivable_minor:int,
     *   payable_minor:int,
     *   net_position_minor:int,
     *   positive_position_minor:int,
     *   debt_position_minor:int,
     *   cashout_eligible_minor:int
     * }
     */
    public function forUser(User $user): array
    {
        $actorId = $user->actor?->id;
        abort_unless($actorId !== null, 403);

        $unitId = MonetaryUnit::query()->where('code', 'IET')->value('id');

        $side = $this->wallets->execute($user);
        $walletMinor = $this->accounting->accountBalanceMinor($side['wallet']);
        $availableWalletMinor = $this->available->forUser($user, $side['wallet']);

        if ($unitId === null) {
            return [
                'wallet_minor' => $walletMinor,
                'receivable_minor' => 0,
                'payable_minor' => 0,
                'net_position_minor' => $walletMinor,
                'positive_position_minor' => max(0, $walletMinor),
                'debt_position_minor' => max(0, -$walletMinor),
                'cashout_eligible_minor' => max(0, min($availableWalletMinor, $walletMinor)),
            ];
        }

        $receivableMinor = $this->outstandingFor((int) $unitId, (int) $actorId, creditor: true);
        $payableMinor = $this->outstandingFor((int) $unitId, (int) $actorId, creditor: false);
        $netPositionMinor = $walletMinor + $receivableMinor - $payableMinor;

        return [
            'wallet_minor' => $walletMinor,
            'receivable_minor' => $receivableMinor,
            'payable_minor' => $payableMinor,
            'net_position_minor' => $netPositionMinor,
            'positive_position_minor' => max(0, $netPositionMinor),
            'debt_position_minor' => max(0, -$netPositionMinor),
            'cashout_eligible_minor' => max(
                0,
                min($availableWalletMinor, max(0, $netPositionMinor)),
            ),
        ];
    }

    private function outstandingFor(int $unitId, int $actorId, bool $creditor): int
    {
        $column = $creditor ? 'creditor_actor_id' : 'debtor_actor_id';

        return FinancialObligation::query()
            ->where('monetary_unit_id', $unitId)
            ->where($column, $actorId)
            ->with(['settlements' => fn ($query) => $query
                ->where('status', SettlementStatus::Confirmed->value)])
            ->get()
            ->sum(function (FinancialObligation $obligation): int {
                $confirmed = $obligation->settlements->sum(
                    fn (Settlement $settlement): int => (int) $settlement->amount_minor,
                );

                return max(0, (int) $obligation->amount_minor - $confirmed);
            });
    }
}
