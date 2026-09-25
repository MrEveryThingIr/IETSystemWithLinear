<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserInboxChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $connection = 'database';

    public string $queue = 'notifications';

    public function __construct(
        public readonly int $userId,
        public readonly string $notificationId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'inbox.changed';
    }

    /** @return array{notification_id: string} */
    public function broadcastWith(): array
    {
        return ['notification_id' => $this->notificationId];
    }
}
