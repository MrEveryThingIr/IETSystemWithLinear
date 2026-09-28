<?php

namespace App\Actions\Iet;

use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\IetInternalTransfer;
use App\Models\IetRateVersion;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TransferIet
{
    public function __construct(private readonly EnsureIetWallet $wallets) {}

    public function execute(
        User $sender,
        User $receiver,
        int $ietAmountMinor,
        ?IetRateVersion $rate = null,
        ?int $usdReferenceMinor = null,
        ?string $sourceType = null,
        ?string $sourceUuid = null,
        ?string $description = null,
    ): IetInternalTransfer {
        abort_if($ietAmountMinor <= 0, 422, __('iet.validation.iet_amount'));
        abort_if((int) $sender->id === (int) $receiver->id, 422, __('iet.validation.self_transfer'));

        if ($sourceType !== null || $sourceUuid !== null) {
            abort_unless(
                is_string($sourceType)
                && trim($sourceType) !== ''
                && mb_strlen(trim($sourceType)) <= 80
                && is_string($sourceUuid)
                && Str::isUuid($sourceUuid),
                422,
                'Invalid IET transfer source.',
            );

            $existing = IetInternalTransfer::query()
                ->where('source_type', trim($sourceType))
                ->where('source_uuid', $sourceUuid)
                ->first();

            if ($existing instanceof IetInternalTransfer) {
                return $existing;
            }
        }

        $sender->loadMissing('actor');
        $receiver->loadMissing('actor');
        abort_unless(
            $sender->actor instanceof Actor
            && $receiver->actor instanceof Actor
            && $receiver->status === 'active'
            && $receiver->email_verified_at !== null,
            422,
            __('iet.validation.receiver'),
        );

        $senderSide = $this->wallets->execute($sender);
        $receiverSide = $this->wallets->execute($receiver);

        return DB::transaction(function () use (
            $sender,
            $receiver,
            $ietAmountMinor,
            $rate,
            $usdReferenceMinor,
            $sourceType,
            $sourceUuid,
            $description,
            $senderSide,
            $receiverSide,
        ): IetInternalTransfer {
            $ledgerIds = [$senderSide['ledger']->id, $receiverSide['ledger']->id];
            sort($ledgerIds);

            Ledger::query()
                ->whereIn('id', $ledgerIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sourceType !== null && $sourceUuid !== null) {
                $existing = IetInternalTransfer::query()
                    ->where('source_type', trim($sourceType))
                    ->where('source_uuid', $sourceUuid)
                    ->first();

                if ($existing instanceof IetInternalTransfer) {
                    return $existing;
                }
            }

            $balance = (int) $senderSide['wallet']->journalLines()
                ->selectRaw('COALESCE(SUM(debit_minor), 0) - COALESCE(SUM(credit_minor), 0) AS balance_minor')
                ->value('balance_minor');

            abort_if($balance < $ietAmountMinor, 422, __('iet.validation.insufficient_balance'));

            $transfer = IetInternalTransfer::query()->create([
                'sender_user_id' => $sender->id,
                'receiver_user_id' => $receiver->id,
                'iet_amount_minor' => $ietAmountMinor,
                'rate_version_id' => $rate?->id,
                'usd_reference_minor' => $usdReferenceMinor,
                'source_type' => $sourceType !== null ? trim($sourceType) : null,
                'source_uuid' => $sourceUuid,
                'description' => $description,
            ]);

            $senderEntry = $this->postSide(
                $senderSide['ledger'],
                $senderSide['clearing']->id,
                $senderSide['wallet']->id,
                $sender,
                $ietAmountMinor,
                $transfer,
                'IET sent to '.$receiver->username,
            );

            $receiverEntry = $this->postSide(
                $receiverSide['ledger'],
                $receiverSide['wallet']->id,
                $receiverSide['clearing']->id,
                $sender,
                $ietAmountMinor,
                $transfer,
                'IET received from '.$sender->username,
            );

            $transfer->forceFill([
                'sender_journal_entry_id' => $senderEntry->id,
                'receiver_journal_entry_id' => $receiverEntry->id,
            ]);

            DB::table('iet_internal_transfers')
                ->where('id', $transfer->id)
                ->update([
                    'sender_journal_entry_id' => $senderEntry->id,
                    'receiver_journal_entry_id' => $receiverEntry->id,
                    'updated_at' => now(),
                ]);

            return $transfer->fresh(['sender', 'receiver', 'rateVersion']);
        }, attempts: 3);
    }

    private function postSide(
        Ledger $ledger,
        int $debitAccountId,
        int $creditAccountId,
        User $actorUser,
        int $amount,
        IetInternalTransfer $transfer,
        string $description,
    ): JournalEntry {
        $entry = JournalEntry::query()->create([
            'ledger_id' => $ledger->id,
            'kind' => JournalEntryKind::IetInternalTransfer,
            'occurred_on' => now()->toDateString(),
            'description' => $description,
            'source_type' => 'iet_internal_transfer',
            'source_uuid' => $transfer->uuid,
            'idempotency_key' => 'iet-transfer:'.$transfer->uuid.':ledger:'.$ledger->id,
            'created_by_actor_id' => $actorUser->actor->id,
            'acting_user_id' => $actorUser->id,
            'posted_at' => now(),
        ]);

        $entry->lines()->create([
            'account_id' => $debitAccountId,
            'debit_minor' => $amount,
            'credit_minor' => 0,
            'memo' => $description,
        ]);
        $entry->lines()->create([
            'account_id' => $creditAccountId,
            'debit_minor' => 0,
            'credit_minor' => $amount,
            'memo' => $description,
        ]);

        return $entry;
    }
}
