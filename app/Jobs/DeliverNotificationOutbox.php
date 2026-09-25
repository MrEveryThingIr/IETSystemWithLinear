<?php

namespace App\Jobs;

use App\Events\UserInboxChanged;
use App\Models\NotificationOutbox;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeliverNotificationOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly int $outboxId) {}

    public function handle(): void
    {
        $delivered = DB::transaction(function (): ?array {
            /** @var NotificationOutbox|null $outbox */
            $outbox = NotificationOutbox::query()->lockForUpdate()->find($this->outboxId);

            if (! $outbox instanceof NotificationOutbox
                || $outbox->dispatched_at !== null
                || $outbox->discarded_at !== null) {
                return null;
            }

            $recipient = User::query()->find($outbox->recipient_user_id);

            if (! $recipient instanceof User
                || $recipient->status !== 'active'
                || $recipient->email_verified_at === null) {
                $outbox->discard('Recipient is no longer active and verified.');

                return null;
            }

            DB::table('notifications')->insertOrIgnore([
                'id' => $outbox->uuid,
                'type' => $outbox->kind,
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => $recipient->id,
                'data' => json_encode($outbox->data, JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => $outbox->requested_at,
                'updated_at' => now(),
            ]);

            $outbox->markDispatched();

            return [
                'user_id' => (int) $recipient->id,
                'notification_id' => $outbox->uuid,
            ];
        }, attempts: 3);

        if ($delivered !== null) {
            UserInboxChanged::dispatch($delivered['user_id'], $delivered['notification_id']);
        }
    }
}
