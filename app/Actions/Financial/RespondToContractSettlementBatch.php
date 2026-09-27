<?php

namespace App\Actions\Financial;

use App\Models\Actor;
use App\Models\ContractSettlementBatch;
use App\Models\Settlement;
use App\Models\User;
use App\SettlementStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RespondToContractSettlementBatch
{
    public function __construct(private readonly RespondToSettlement $settlements) {}

    public function confirm(ContractSettlementBatch $batch, User $user): ContractSettlementBatch
    {
        return $this->respond($batch, $user, true, null);
    }

    public function reject(
        ContractSettlementBatch $batch,
        User $user,
        string $note,
    ): ContractSettlementBatch {
        $note = trim($note);
        abort_if($note === '' || mb_strlen($note) > 5000, 422, 'Rejecting a settlement batch requires a note.');

        return $this->respond($batch, $user, false, $note);
    }

    private function respond(
        ContractSettlementBatch $batch,
        User $user,
        bool $confirm,
        ?string $note,
    ): ContractSettlementBatch {
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($batch, $current, $confirm, $note): ContractSettlementBatch {
            $locked = ContractSettlementBatch::query()
                ->with(['contract', 'settlements.obligation'])
                ->lockForUpdate()
                ->findOrFail($batch->id);

            Gate::forUser($current)->authorize('view', $locked->contract);

            $proposerId = (int) $locked->proposed_by_actor_id;
            $debtorId = (int) $locked->debtor_actor_id;
            $creditorId = (int) $locked->creditor_actor_id;

            abort_unless(in_array($proposerId, [$debtorId, $creditorId], true), 500);

            $counterpartyId = $proposerId === $debtorId
                ? $creditorId
                : $debtorId;

            abort_unless(
                (int) $current->actor->id === $counterpartyId,
                403,
                'Only the non-proposing settlement counterparty may confirm or reject this cash claim.',
            );

            $pending = $locked->settlements
                ->filter(fn (Settlement $settlement): bool => $settlement->status === SettlementStatus::PendingConfirmation)
                ->values();

            abort_if($pending->isEmpty(), 422, 'This settlement batch has no pending allocations.');

            foreach ($pending as $settlement) {
                Gate::forUser($current)->authorize('respond', $settlement);

                if ($confirm) {
                    $this->settlements->confirm($settlement, $current);
                } else {
                    $this->settlements->reject($settlement, $current, (string) $note);
                }
            }

            return $locked->fresh([
                'contract',
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'proposedBy.user',
                'settlements.obligation',
                'settlements.proposedBy.user',
                'settlements.confirmedBy.user',
                'settlements.rejectedBy.user',
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
