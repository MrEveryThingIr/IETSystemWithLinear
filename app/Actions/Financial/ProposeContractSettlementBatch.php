<?php

namespace App\Actions\Financial;

use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractSettlementBatch;
use App\Models\FinancialObligation;
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
    ): ContractSettlementBatch {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('view', $contract);

        abort_if($amountMinor <= 0, 422, 'Settlement batch amount must be positive.');
        abort_if($paidAt->isFuture(), 422, 'Settlement paid time cannot be in the future.');

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
        ): ContractSettlementBatch {
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->id);
            Gate::forUser($current)->authorize('view', $lockedContract);

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);
            $lockedUnit = MonetaryUnit::query()->lockForUpdate()->findOrFail($unit->id);

            $obligations = FinancialObligation::query()
                ->where('monetary_unit_id', $lockedUnit->id)
                ->where('debtor_actor_id', $actor->id)
                ->whereHas(
                    'contractVersion',
                    fn ($query) => $query->where('contract_id', $lockedContract->id),
                )
                ->whereHas(
                    'fulfillment',
                    fn ($query) => $query->where('status', FulfillmentStatus::Accepted->value),
                )
                ->with(['fulfillment', 'settlements', 'creditor.user', 'contractVersion'])
                ->orderByRaw('due_at IS NULL')
                ->orderBy('due_at')
                ->orderBy('recognized_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (FinancialObligation $obligation): bool => $obligation->availableToSettleMinor() > 0)
                ->values();

            abort_if($obligations->isEmpty(), 422, 'There is no outstanding accepted obligation available for payment.');

            $creditorIds = $obligations->pluck('creditor_actor_id')->unique()->values();
            abort_unless(
                $creditorIds->count() === 1,
                422,
                'A settlement batch can cover only one creditor at a time.',
            );

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
                'debtor_actor_id' => $actor->id,
                'creditor_actor_id' => (int) $creditorIds->first(),
                'monetary_unit_id' => $lockedUnit->id,
                'proposed_by_actor_id' => $actor->id,
                'amount_minor' => $amountMinor,
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
