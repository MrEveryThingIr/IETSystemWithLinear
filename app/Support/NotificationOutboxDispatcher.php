<?php

namespace App\Support;

use App\Jobs\DeliverNotificationOutbox;
use App\Models\NotificationOutbox;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationOutboxDispatcher
{
    /**
     * Delivers this person's own pending notifications right now. The queue stays the normal path; this is the
     * safety net for hosts where no worker is running, so something that reasonably needs a person's attention is
     * never invisible. Only rows the queue has not picked up for a few seconds are touched, at most every ten seconds.
     */
    public function deliverPendingFor(User $user, int $limit = 25): int
    {
        if (! Cache::add('notification-outbox:self-heal:'.$user->getKey(), 1, 10)) {
            return 0;
        }

        $ids = NotificationOutbox::query()
            ->where('recipient_user_id', $user->getKey())
            ->whereNull('dispatched_at')
            ->whereNull('discarded_at')
            ->where('requested_at', '<=', now()->subSeconds(5))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            (new DeliverNotificationOutbox((int) $id))->handle();
        }

        return $ids->count();
    }

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
