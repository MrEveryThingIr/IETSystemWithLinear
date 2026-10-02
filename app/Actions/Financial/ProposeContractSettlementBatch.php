<?php

namespace App\Actions\Financial;

use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractSettlementBatch;
use App\Models\FinancialObligation;
use App\Models\IetValuationQuote;
use App\Models\MarketQuote;
use App\Models\MonetaryUnit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProposeContractSettlementBatch
{
    public function __construct(private readonly ProposeSettlement $settlements) {}

    public function execute(
        Contract $contract,
        User $user,
        MonetaryUnit $unit,
        int $amountMinor,
        CarbonInterface $paidAt,
        string $method = 'cash',
        ?string $reference = null,
        ?string $note = null,
        string $perspective = 'paid',
        ?MonetaryUnit $referenceUnit = null,
        ?int $referenceAmountMinor = null,
        ?int $referenceUsdAmountMinor = null,
        ?MarketQuote $referenceMarketQuote = null,
        ?IetValuationQuote $ietValuationQuote = null,
    ): ContractSettlementBatch {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('view', $contract);

        abort_if($amountMinor <= 0, 422, 'Settlement batch amount must be positive.');
        abort_if($paidAt->isFuture(), 422, 'Settlement paid time cannot be in the future.');
        abort_unless(
            in_array($perspective, ['paid', 'received'], true),
            422,
            'Cash settlement perspective must be paid or received.',
        );

        $method = Str::squish($method);
        $reference = $reference !== null ? Str::squish($reference) : null;
        $note = trim((string) $note);

        abort_unless($method === 'cash', 422, 'The first release supports cash settlement only.');
        abort_if($reference !== null && mb_strlen($reference) > 255, 422, 'Settlement reference is too long.');
        abort_if(mb_strlen($note) > 5000, 422, 'Settlement note is too long.');

        return DB::transaction(function () use (
            $contract,
            $current,
            $unit,
            $amountMinor,
            $paidAt,
            $method,
            $reference,
            $note,
            $perspective,
            $referenceUnit,
            $referenceAmountMinor,
            $referenceUsdAmountMinor,
            $referenceMarketQuote,
            $ietValuationQuote,
        ): ContractSettlementBatch {
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            Gate::forUser($current)->authorize('view', $lockedContract);

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);
            $lockedUnit = MonetaryUnit::query()->lockForUpdate()->findOrFail($unit->id);

            $obligationQuery = FinancialObligation::query()
                ->where('monetary_unit_id', $lockedUnit->id)
                ->whereHas(
                    'contractVersion',
                    fn ($query) => $query->where('contract_id', $lockedContract->id),
                )
                ->whereHas(
                    'fulfillment',
                    fn ($query) => $query->where('status', FulfillmentStatus::Accepted->value),
                );

            if ($perspective === 'paid') {
                $obligationQuery->where('debtor_actor_id', $actor->id);
            } else {
                $obligationQuery->where('creditor_actor_id', $actor->id);
            }

            $obligations = $obligationQuery
                ->with(['fulfillment', 'settlements', 'debtor.user', 'creditor.user', 'contractVersion'])
                ->orderByRaw('due_at IS NULL')
                ->orderBy('due_at')
                ->orderBy('recognized_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (FinancialObligation $obligation): bool => $obligation->availableToSettleMinor() > 0)
                ->values();

            abort_if(
                $obligations->isEmpty(),
                422,
                'There is no outstanding accepted obligation available for this cash settlement.',
            );

            $counterpartyColumn = $perspective === 'paid'
                ? 'creditor_actor_id'
                : 'debtor_actor_id';

            $counterpartyIds = $obligations->pluck($counterpartyColumn)->unique()->values();

            abort_unless(
                $counterpartyIds->count() === 1,
                422,
                'A cash settlement batch can cover only one counterparty at a time.',
            );

            $counterpartyId = (int) $counterpartyIds->first();
            $debtorActorId = $perspective === 'paid' ? $actor->id : $counterpartyId;
            $creditorActorId = $perspective === 'paid' ? $counterpartyId : $actor->id;

            $availableMinor = $obligations->sum(
                fn (FinancialObligation $obligation): int => $obligation->availableToSettleMinor(),
            );

            abort_if(
                $amountMinor > $availableMinor,
                422,
                'Settlement batch amount exceeds currently payable outstanding value.',
            );

            $batch = ContractSettlementBatch::query()->create([
                'contract_id' => $lockedContract->id,
                'debtor_actor_id' => $debtorActorId,
                'creditor_actor_id' => $creditorActorId,
                'monetary_unit_id' => $lockedUnit->id,
                'reference_monetary_unit_id' => $referenceUnit?->id,
                'proposed_by_actor_id' => $actor->id,
                'amount_minor' => $amountMinor,
                'reference_amount_minor' => $referenceAmountMinor,
                'reference_usd_amount_minor' => $referenceUsdAmountMinor,
                'reference_market_quote_id' => $referenceMarketQuote?->id,
                'iet_valuation_quote_id' => $ietValuationQuote?->id,
                'method' => $method,
                'paid_at' => $paidAt->utc(),
                'reference' => $reference !== '' ? $reference : null,
                'note' => $note !== '' ? $note : null,
            ]);

            $remaining = $amountMinor;

            /** @var Collection<int, FinancialObligation> $obligations */
            foreach ($obligations as $obligation) {
                if ($remaining <= 0) {
                    break;
                }

                $allocation = min($remaining, $obligation->availableToSettleMinor());

                $this->settlements->execute(
                    $obligation,
                    $current,
                    $allocation,
                    $paidAt,
                    $method,
                    $reference,
                    $note !== ''
                        ? $note
                        : 'Allocated from Contract cash settlement '.$batch->uuid,
                    $batch,
                );

                $remaining -= $allocation;
            }

            abort_unless($remaining === 0, 500, 'Settlement allocation did not consume the requested amount.');

            return $batch->fresh([
                'contract',
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'proposedBy.user',
                'settlements.obligation.fulfillment',
                'settlements.proposedBy.user',
            ]);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
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

        return $current;
    }
}
