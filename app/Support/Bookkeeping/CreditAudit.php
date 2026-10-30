<?php

namespace App\Support\Bookkeeping;

use App\Models\Actor;
use App\Models\Bookkeeping\Transaction;

final class CreditAudit
{
    public function record(Transaction $entry, Actor $actor): void
    {
        if ($entry->type->isPersonal()) {
            return;
        }
        $snapshot = app(CreditAccounting::class)->snapshot($entry->ledger, currentRead: true);
        app(BookkeepingRecorder::class)->audit($entry->ledger, $actor, 'credit.projected', $entry, after: [
            'policy' => 'fifo_v1', 'trigger_version' => $entry->version,
            'snapshot' => $snapshot,
        ]);
    }
}
