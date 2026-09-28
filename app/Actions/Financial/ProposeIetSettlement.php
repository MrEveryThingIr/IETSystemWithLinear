<?php

namespace App\Actions\Financial;

use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\Settlement;
use App\Models\User;
use App\Support\IetRequirement;
use App\Support\IetSettlementRail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProposeIetSettlement
{
    public function __construct(
        private readonly IetRequirement $requirement,
        private readonly ProposeSettlement $settlements,
        private readonly IetSettlementRail $rail,
    ) {}

    public function execute(
        FinancialObligation $obligation,
        User $user,
        int $usdAmountMinor,
        ?string $note = null,
    ): Settlement {
        Gate::forUser($user)->authorize('proposeSettlement', $obligation);

        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current?->actor instanceof Actor, 403);

        $obligation->loadMissing(['debtor.user', 'creditor.user', 'monetaryUnit']);

        abort_unless(
            (int) $current->actor->id === (int) $obligation->debtor_actor_id,
            403,
            'Only the debtor can initiate an IET Settlement.',
        );
        abort_unless(
            $obligation->monetaryUnit->code === 'USD',
            422,
            'IET Settlement currently supports USD-denominated obligations only.',
        );
        abort_if($usdAmountMinor <= 0, 422);

        $quote = $this->requirement->requireForUsdMinor($current, $usdAmountMinor);

        return DB::transaction(function () use (
            $obligation,
            $current,
            $usdAmountMinor,
            $note,
            $quote,
        ): Settlement {
            $settlement = $this->settlements->execute(
                $obligation,
                $current,
                $usdAmountMinor,
                now(),
                'IET',
                'valuation:'.$quote['snapshot']->uuid,
                $note,
                $quote['snapshot'],
                $quote['iet_minor'],
            );

            $this->rail->reserve($settlement, $current);

            return $settlement->fresh([
                'obligation.debtor.user',
                'obligation.creditor.user',
                'obligation.monetaryUnit',
                'ietValuation',
                'proposedBy.user',
            ]);
        }, attempts: 3);
    }
}
