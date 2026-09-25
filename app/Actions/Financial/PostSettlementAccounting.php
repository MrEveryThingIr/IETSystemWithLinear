<?php

namespace App\Actions\Financial;

use App\Actions\Accounting\PostJournalEntry;
use App\FinancialObligationEventType;
use App\JournalEntryKind;
use App\Models\FinancialObligationEvent;
use App\Models\JournalEntry;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PostSettlementAccounting
{
    public function __construct(
        private readonly EnsureFinancialBridgeAccounts $accounts,
        private readonly PostJournalEntry $post,
    ) {}

    public function execute(Settlement $settlement, User $user): JournalEntry
    {
        Gate::forUser($user)->authorize('postAccounting', $settlement);

        $settlement->loadMissing('obligation');
        $obligation = $settlement->obligation;
        $side = $this->accounts->execute($obligation, $user);
        $actor = $side['actor'];
        $ledger = $side['ledger'];

        abort_unless(
            JournalEntry::query()
                ->where('ledger_id', $ledger->id)
                ->where('source_type', 'financial_obligation')
                ->where('source_uuid', $obligation->uuid)
                ->exists(),
            422,
            'Post this Financial Obligation to your Accounting before posting its Settlement.',
        );

        $entry = $this->post->execute(
            $ledger,
            $user,
            JournalEntryKind::Settlement,
            $settlement->paid_at->toDateString(),
            'Settlement for Financial Obligation '.$obligation->uuid,
            [
                [
                    'account' => $side['settlement_debit'],
                    'debit_minor' => $settlement->amount_minor,
                    'memo' => 'Settlement '.$settlement->uuid,
                ],
                [
                    'account' => $side['settlement_credit'],
                    'credit_minor' => $settlement->amount_minor,
                    'memo' => 'Settlement '.$settlement->uuid,
                ],
            ],
            sourceType: 'settlement',
            sourceUuid: $settlement->uuid,
            idempotencyKey: 'settlement:'.$settlement->uuid.':actor:'.$actor->uuid,
        );

        FinancialObligationEvent::query()->firstOrCreate(
            ['journal_entry_id' => $entry->id],
            [
                'financial_obligation_id' => $obligation->id,
                'settlement_id' => $settlement->id,
                'actor_id' => $actor->id,
                'event_type' => FinancialObligationEventType::SettlementAccountingPosted,
                'payload' => [
                    'journal_entry_uuid' => $entry->uuid,
                    'ledger_uuid' => $ledger->uuid,
                ],
            ],
        );

        return $entry;
    }
}
