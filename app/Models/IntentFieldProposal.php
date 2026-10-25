<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntentFieldProposal extends Model
{
    public const PROPOSED = 'proposed';

    public const REVIEWED = 'reviewed';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const PUBLISHED = 'published';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'reviewed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $proposal): void {
            $proposal->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Actor, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'proposed_by_actor_id');
    }
}
