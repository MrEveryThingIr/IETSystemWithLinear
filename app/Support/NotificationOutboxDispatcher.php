<?php

namespace App\Support;

use App\Jobs\DeliverNotificationOutbox;
use App\Models\NotificationOutbox;

class NotificationOutboxDispatcher
{
    public function dispatchPending(int $limit = 200): int
    {
        $limit = max(1, min($limit, 1000));

        $ids = NotificationOutbox::query()
            ->whereNull('dispatched_at')
            ->whereNull('discarded_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverNotificationOutbox::dispatch((int) $id)->onQueue('notifications');
        }

        return $ids->count();
    }
}
