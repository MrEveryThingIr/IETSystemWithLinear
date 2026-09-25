<?php

namespace App\Models;

use App\CommitmentEventType;
use Database\Factories\CommitmentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'commitment_id',
    'actor_id',
    'event_type',
    'payload',
])]
class CommitmentEvent extends Model
{
    /** @use HasFactory<CommitmentEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Commitment events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Commitment events preserve authoritative history.');
        });
    }

    /** @return BelongsTo<Commitment, $this> */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    protected function casts(): array
    {
        return [
            'event_type' => CommitmentEventType::class,
            'payload' => 'array',
        ];
    }
}
