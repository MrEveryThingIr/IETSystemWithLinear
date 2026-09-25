<?php

namespace App\Actions\Financial;

use App\FinancialObligationEventType;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use App\Models\Settlement;
use App\Models\User;
use App\SettlementStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RespondToSettlement
{
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
                ->with('obligation.fulfillment')
                ->lockForUpdate()
                ->findOrFail($settlement->id);

            Gate::forUser($current)->authorize('respond', $locked);

            $obligation = FinancialObligation::query()
                ->with('fulfillment')
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

                $locked->confirm($actor, now());

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
