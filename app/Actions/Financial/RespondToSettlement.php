<?php

namespace App\Actions\Financial;

use App\Actions\Exchange\EnsureIetWallet;
use App\FinancialObligationEventType;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\User;
use App\SettlementStatus;
use App\Support\IetAvailableBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RespondToSettlement
{
    public function __construct(
        private readonly EnsureIetWallet $ietWallets,
        private readonly IetAvailableBalance $available,
        private readonly PostFinancialObligationAccounting $postObligationAccounting,
        private readonly PostSettlementAccounting $postSettlementAccounting,
    ) {}

    public function confirm(Settlement $settlement, User $user): Settlement
    {
        return $this->respond($settlement, $user, true, null);
    }

    public function reject(Settlement $settlement, User $user, string $note): Settlement
    {
        $note = trim($note);
        abort_if($note === '' || mb_strlen($note) > 5000, 422, 'Rejecting a Settlement requires a note up to 5000 characters.');

        return $this->respond($settlement, $user, false, $note);
    }

    private function respond(
        Settlement $settlement,
        User $user,
        bool $confirm,
        ?string $note,
    ): Settlement {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('respond', $settlement);

        return DB::transaction(function () use ($settlement, $current, $confirm, $note): Settlement {
            $locked = Settlement::query()
                ->with([
                    'obligation.fulfillment',
                    'obligation.monetaryUnit',
                    'obligation.debtor.user',
                    'obligation.creditor.user',
                ])
                ->lockForUpdate()
                ->findOrFail($settlement->id);

            Gate::forUser($current)->authorize('respond', $locked);

            $obligation = FinancialObligation::query()
                ->with(['fulfillment', 'monetaryUnit', 'debtor.user', 'creditor.user'])
                ->lockForUpdate()
                ->findOrFail($locked->financial_obligation_id);

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            if ($confirm) {
                abort_unless(
                    $obligation->isEconomicallyAccepted(),
                    422,
                    'Settlement cannot be confirmed while the source Fulfillment is not accepted.',
                );

                $confirmedOther = (int) Settlement::query()
                    ->where('financial_obligation_id', $obligation->id)
                    ->where('status', SettlementStatus::Confirmed->value)
                    ->where('id', '!=', $locked->id)
                    ->sum('amount_minor');

                abort_if(
                    $confirmedOther + (int) $locked->amount_minor > (int) $obligation->amount_minor,
                    422,
                    'Confirming this Settlement would exceed the Financial Obligation amount.',
                );

                $isIet = $obligation->monetaryUnit->code === 'IET';
                $debtorUser = null;
                $creditorUser = null;

                if ($isIet) {
                    $debtorUser = $obligation->debtor->user;
                    $creditorUser = $obligation->creditor->user;
                    abort_unless($debtorUser instanceof User && $creditorUser instanceof User, 422, 'IET settlement requires active user wallets.');

                    $debtorSide = $this->ietWallets->execute($debtorUser);
                    $creditorSide = $this->ietWallets->execute($creditorUser);

                    Ledger::query()
                        ->whereIn('id', [$debtorSide['ledger']->id, $creditorSide['ledger']->id])
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    abort_if(
                        $this->available->forUser($debtorUser, $debtorSide['wallet']) < (int) $locked->amount_minor,
                        422,
                        'Debtor has insufficient IET balance for this Settlement.',
                    );

                    $this->postObligationAccounting->execute($obligation, $debtorUser);
                    $this->postObligationAccounting->execute($obligation, $creditorUser);
                }

                $locked->confirm($actor, now());

                if ($isIet) {
                    abort_unless($debtorUser instanceof User && $creditorUser instanceof User, 422);
                    $this->postSettlementAccounting->execute($locked, $debtorUser);
                    $this->postSettlementAccounting->execute($locked, $creditorUser);
                }

                $eventType = FinancialObligationEventType::SettlementConfirmed;
                $payload = [
                    'amount_minor' => $locked->amount_minor,
                    'confirmed_at' => $locked->confirmed_at?->toISOString(),
                ];
            } else {
                $locked->reject($actor, (string) $note, now());

                $eventType = FinancialObligationEventType::SettlementRejected;
                $payload = [
                    'rejection_note' => $locked->rejection_note,
                    'rejected_at' => $locked->rejected_at?->toISOString(),
                ];
            }

            FinancialObligationEvent::query()->create([
                'financial_obligation_id' => $obligation->id,
                'settlement_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => $eventType,
                'payload' => $payload,
            ]);

            return $locked->fresh([
                'obligation.debtor.user',
                'obligation.creditor.user',
                'obligation.monetaryUnit',
                'proposedBy.user',
                'confirmedBy.user',
                'rejectedBy.user',
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
