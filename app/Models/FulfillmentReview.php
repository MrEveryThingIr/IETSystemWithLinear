<?php

namespace App\Models;

use App\FulfillmentReviewDecision;
use Database\Factories\FulfillmentReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'fulfillment_id',
    'reviewer_actor_id',
    'decision',
    'note',
    'reviewed_at',
])]
class FulfillmentReview extends Model
{
    /** @use HasFactory<FulfillmentReviewFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $review): void {
            $review->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Fulfillment reviews are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Fulfillment reviews preserve explicit review evidence.');
        });
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'reviewer_actor_id');
    }

    protected function casts(): array
    {
        return [
            'decision' => FulfillmentReviewDecision::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
