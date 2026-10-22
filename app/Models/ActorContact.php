<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A contact between two people: pending (asked), accepted, or declined. Lower actor id first. */
#[Fillable(['actor_one_id', 'actor_two_id', 'requested_by_actor_id', 'status', 'note', 'responded_at'])]
class ActorContact extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contact): void {
            $contact->uuid ??= (string) Str::uuid();
        });
    }

    /** @param Builder<ActorContact> $query */
    public function scopeInvolving(Builder $query, int $actorId): void
    {
        $query->where(fn (Builder $either) => $either->where('actor_one_id', $actorId)->orWhere('actor_two_id', $actorId));
    }

    /** @param Builder<ActorContact> $query */
    public function scopeBetween(Builder $query, int $a, int $b): void
    {
        [$one, $two] = DirectContext::pair($a, $b);
        $query->where('actor_one_id', $one)->where('actor_two_id', $two);
    }

    public function otherThan(int $actorId): int
    {
        return (int) $this->actor_one_id === $actorId ? (int) $this->actor_two_id : (int) $this->actor_one_id;
    }

    /** @return BelongsTo<Actor, $this> */
    public function actorOne(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_one_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actorTwo(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_two_id');
    }
}
