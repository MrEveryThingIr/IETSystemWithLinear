<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'recipient_user_id',
    'dedupe_key',
    'kind',
    'context_id',
    'subject_type',
    'subject_uuid',
    'data',
    'requested_at',
    'dispatched_at',
    'discarded_at',
    'discard_reason',
])]
class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    private bool $applyingDeliveryLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $outbox): void {
            $outbox->uuid ??= (string) Str::uuid();
            $outbox->requested_at ??= now();
        });

        static::updating(function (self $outbox): void {
            if ($outbox->isDirty([
                'uuid',
                'recipient_user_id',
                'dedupe_key',
                'kind',
                'context_id',
                'subject_type',
                'subject_uuid',
                'data',
                'requested_at',
            ])) {
                throw new LogicException('Notification outbox payload and provenance are immutable.');
            }

            if ($outbox->isDirty(['dispatched_at', 'discarded_at', 'discard_reason'])
                && ! $outbox->applyingDeliveryLifecycle) {
                throw new LogicException('Notification outbox delivery state requires a dedicated lifecycle method.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Notification outbox history cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    public function markDispatched(): void
    {
        if ($this->dispatched_at !== null || $this->discarded_at !== null) {
            return;
        }

        $this->saveDeliveryLifecycle(['dispatched_at' => now()]);
    }

    public function discard(string $reason): void
    {
        if ($this->dispatched_at !== null || $this->discarded_at !== null) {
            return;
        }

        $this->saveDeliveryLifecycle([
            'discarded_at' => now(),
            'discard_reason' => Str::limit(Str::squish($reason), 255, ''),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function saveDeliveryLifecycle(array $attributes): void
    {
        $this->applyingDeliveryLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingDeliveryLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'requested_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'discarded_at' => 'immutable_datetime',
        ];
    }
}
