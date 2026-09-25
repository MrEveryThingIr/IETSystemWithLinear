<?php

namespace App\Actions\Financial;

use App\FinancialObligationEventType;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use App\Models\Settlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProposeSettlement
{
    public function execute(
        FinancialObligation $obligation,
        User $user,
        int $amountMinor,
        CarbonInterface $paidAt,
        ?string $method = null,
        ?string $reference = null,
        ?string $note = null,
    ): Settlement {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('proposeSettlement', $obligation);

        abort_if($amountMinor <= 0, 422, 'Settlement amount must be positive.');

        $method = $method !== null ? Str::squish($method) : null;
        $reference = $reference !== null ? Str::squish($reference) : null;
        $note = trim((string) $note);

        abort_if($method !== null && mb_strlen($method) > 80, 422, 'Settlement method is too long.');
        abort_if($reference !== null && mb_strlen($reference) > 255, 422, 'Settlement reference is too long.');
        abort_if(mb_strlen($note) > 5000, 422, 'Settlement note is too long.');
        abort_if($paidAt->isFuture(), 422, 'Settlement paid time cannot be in the future.');

        return DB::transaction(function () use (
            $obligation,
            $current,
            $amountMinor,
            $paidAt,
            $method,
            $reference,
            $note,
        ): Settlement {
            $locked = FinancialObligation::query()
                ->with(['fulfillment', 'settlements'])
                ->lockForUpdate()
                ->findOrFail($obligation->id);

            Gate::forUser($current)->authorize('proposeSettlement', $locked);
            abort_if(
                $amountMinor > $locked->outstandingMinor(),
                422,
                'Settlement amount exceeds the currently outstanding obligation amount.',
            );

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $settlement = Settlement::query()->create([
                'financial_obligation_id' => $locked->id,
                'amount_minor' => $amountMinor,
                'paid_at' => $paidAt->utc(),
                'method' => $method !== '' ? $method : null,
                'reference' => $reference !== '' ? $reference : null,
                'note' => $note !== '' ? $note : null,
                'proposed_by_actor_id' => $actor->id,
            ]);

            FinancialObligationEvent::query()->create([
                'financial_obligation_id' => $locked->id,
                'settlement_id' => $settlement->id,
                'actor_id' => $actor->id,
                'event_type' => FinancialObligationEventType::SettlementProposed,
                'payload' => [
                    'amount_minor' => $amountMinor,
                    'paid_at' => $settlement->paid_at->toISOString(),
                    'reference' => $settlement->reference,
                ],
            ]);

            return $settlement->fresh([
                'obligation.debtor.user',
                'obligation.creditor.user',
                'obligation.monetaryUnit',
                'proposedBy.user',
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
