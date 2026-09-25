<?php

namespace App\Actions\Financial;

use App\Actions\Accounting\PostJournalEntry;
use App\FinancialObligationEventType;
use App\JournalEntryKind;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PostFinancialObligationAccounting
{
    public function __construct(
        private readonly EnsureFinancialBridgeAccounts $accounts,
        private readonly PostJournalEntry $post,
    ) {}

    public function execute(FinancialObligation $obligation, User $user): JournalEntry
    {
        Gate::forUser($user)->authorize('postAccounting', $obligation);

        $side = $this->accounts->execute($obligation, $user);
        $actor = $side['actor'];
        $ledger = $side['ledger'];

        $entry = $this->post->execute(
            $ledger,
            $user,
            JournalEntryKind::ObligationRecognition,
            $obligation->recognized_at->toDateString(),
            $obligation->description ?? 'Financial obligation recognition',
            [
                [
                    'account' => $side['recognition_debit'],
                    'debit_minor' => $obligation->amount_minor,
                    'memo' => 'Financial obligation '.$obligation->uuid,
                ],
                [
                    'account' => $side['recognition_credit'],
                    'credit_minor' => $obligation->amount_minor,
                    'memo' => 'Financial obligation '.$obligation->uuid,
                ],
            ],
            sourceType: 'financial_obligation',
            sourceUuid: $obligation->uuid,
            idempotencyKey: 'financial-obligation:'.$obligation->uuid.':actor:'.$actor->uuid,
        );

        FinancialObligationEvent::query()->firstOrCreate(
            ['journal_entry_id' => $entry->id],
            [
                'financial_obligation_id' => $obligation->id,
                'settlement_id' => null,
                'actor_id' => $actor->id,
                'event_type' => FinancialObligationEventType::AccountingPosted,
                'payload' => [
                    'journal_entry_uuid' => $entry->uuid,
                    'ledger_uuid' => $ledger->uuid,
                ],
            ],
        );

        return $entry;
    }
}
