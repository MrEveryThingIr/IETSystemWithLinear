<?php

namespace App\Support;

use App\Actions\Accounting\PostSystemJournalEntry;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\Ledger;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class IetSettlementRail
{
    public function __construct(
        private readonly IetWallet $wallet,
        private readonly PostSystemJournalEntry $post,
    ) {}

    public function reserve(Settlement $settlement, User $debtorUser): void
    {
        $settlement->loadMissing(['obligation.debtor.user', 'ietValuation']);
        $amount = (int) $settlement->iet_amount_minor;

        abort_unless($amount > 0 && $settlement->ietValuation !== null, 422);

        $actor = $this->actor($debtorUser);
        abort_unless((int) $actor->id === (int) $settlement->obligation->debtor_actor_id, 403);

        $wallet = $this->wallet->ensure($debtorUser);

        DB::transaction(function () use ($settlement, $debtorUser, $actor, $wallet, $amount): void {
            Ledger::query()->whereKey($wallet['ledger']->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $this->wallet->accountBalanceMinor($wallet['cash']) < $amount,
                422,
                'Insufficient available IET for this settlement.',
            );

            $this->post->execute(
                $wallet['ledger'],
                $actor,
                JournalEntryKind::IetSettlementReserve,
                now()->toDateString(),
                'Reserve IET for Settlement '.$settlement->uuid,
                [
                    [
                        'account' => $wallet['settlement_reserve'],
                        'debit_minor' => $amount,
                        'memo' => 'Reserved for Settlement '.$settlement->uuid,
                    ],
                    [
                        'account' => $wallet['cash'],
                        'credit_minor' => $amount,
                        'memo' => 'Reserved for Settlement '.$settlement->uuid,
                    ],
                ],
                actingUser: $debtorUser,
                sourceType: 'settlement',
                sourceUuid: $settlement->uuid,
                idempotencyKey: 'iet-settlement:'.$settlement->uuid.':reserve',
            );
        }, attempts: 3);
    }

    public function finalize(Settlement $settlement, User $confirmingUser): void
    {
        $settlement->loadMissing([
            'obligation.debtor.user',
            'obligation.creditor.user',
            'ietValuation',
        ]);
        $amount = (int) $settlement->iet_amount_minor;

        abort_unless($amount > 0 && $settlement->ietValuation !== null, 422);

        $confirmer = $this->actor($confirmingUser);
        abort_unless((int) $confirmer->id === (int) $settlement->obligation->creditor_actor_id, 403);

        $debtorUser = $settlement->obligation->debtor->user;
        $creditorUser = $settlement->obligation->creditor->user;
        abort_unless($debtorUser instanceof User && $creditorUser instanceof User, 422);

        $debtorWallet = $this->wallet->ensure($debtorUser);
        $creditorWallet = $this->wallet->ensure($creditorUser);

        DB::transaction(function () use (
            $settlement,
            $confirmingUser,
            $confirmer,
            $debtorWallet,
            $creditorWallet,
            $amount,
        ): void {
            Ledger::query()
                ->whereIn('id', [$debtorWallet['ledger']->id, $creditorWallet['ledger']->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_if(
                $this->wallet->accountBalanceMinor($debtorWallet['settlement_reserve']) < $amount,
                422,
                'Reserved IET is no longer sufficient for this Settlement.',
            );

            $this->post->execute(
                $debtorWallet['ledger'],
                $confirmer,
                JournalEntryKind::IetSettlementTransfer,
                now()->toDateString(),
                'Finalize outgoing IET Settlement '.$settlement->uuid,
                [
                    [
                        'account' => $debtorWallet['transfer_out'],
                        'debit_minor' => $amount,
                        'memo' => 'IET sent for Settlement '.$settlement->uuid,
                    ],
                    [
                        'account' => $debtorWallet['settlement_reserve'],
                        'credit_minor' => $amount,
                        'memo' => 'Consume Settlement reserve',
                    ],
                ],
                actingUser: $confirmingUser,
                sourceType: 'settlement',
                sourceUuid: $settlement->uuid,
                idempotencyKey: 'iet-settlement:'.$settlement->uuid.':debit',
            );

            $this->post->execute(
                $creditorWallet['ledger'],
                $confirmer,
                JournalEntryKind::IetSettlementTransfer,
                now()->toDateString(),
                'Finalize incoming IET Settlement '.$settlement->uuid,
                [
                    [
                        'account' => $creditorWallet['cash'],
                        'debit_minor' => $amount,
                        'memo' => 'IET received for Settlement '.$settlement->uuid,
                    ],
                    [
                        'account' => $creditorWallet['transfer_in'],
                        'credit_minor' => $amount,
                        'memo' => 'IET received for Settlement '.$settlement->uuid,
                    ],
                ],
                actingUser: $confirmingUser,
                sourceType: 'settlement',
                sourceUuid: $settlement->uuid,
                idempotencyKey: 'iet-settlement:'.$settlement->uuid.':credit',
            );
        }, attempts: 3);
    }

    public function release(Settlement $settlement, User $rejectingUser): void
    {
        $settlement->loadMissing(['obligation.debtor.user', 'obligation.creditor.user', 'ietValuation']);
        $amount = (int) $settlement->iet_amount_minor;

        abort_unless($amount > 0 && $settlement->ietValuation !== null, 422);

        $rejector = $this->actor($rejectingUser);
        abort_unless((int) $rejector->id === (int) $settlement->obligation->creditor_actor_id, 403);

        $debtorUser = $settlement->obligation->debtor->user;
        abort_unless($debtorUser instanceof User, 422);

        $wallet = $this->wallet->ensure($debtorUser);

        DB::transaction(function () use (
            $settlement,
            $rejectingUser,
            $rejector,
            $wallet,
            $amount,
        ): void {
            Ledger::query()->whereKey($wallet['ledger']->id)->lockForUpdate()->firstOrFail();

            abort_if(
                $this->wallet->accountBalanceMinor($wallet['settlement_reserve']) < $amount,
                422,
                'Reserved IET is no longer sufficient for this Settlement.',
            );

            $this->post->execute(
                $wallet['ledger'],
                $rejector,
                JournalEntryKind::IetSettlementRelease,
                now()->toDateString(),
                'Release rejected IET Settlement '.$settlement->uuid,
                [
                    [
                        'account' => $wallet['cash'],
                        'debit_minor' => $amount,
                        'memo' => 'Rejected Settlement returned to available IET',
                    ],
                    [
                        'account' => $wallet['settlement_reserve'],
                        'credit_minor' => $amount,
                        'memo' => 'Release Settlement reserve',
                    ],
                ],
                actingUser: $rejectingUser,
                sourceType: 'settlement',
                sourceUuid: $settlement->uuid,
                idempotencyKey: 'iet-settlement:'.$settlement->uuid.':release',
            );
        }, attempts: 3);
    }

    private function actor(User $user): Actor
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

        return $current->actor;
    }
}
