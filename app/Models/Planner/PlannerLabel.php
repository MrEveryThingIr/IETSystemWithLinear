<?php

namespace App\Models\Planner;

use App\Models\Actor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A person's own plan label with a colour. */
#[Fillable(['uuid', 'actor_id', 'name', 'color'])]
class PlannerLabel extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $label): void {
            $label->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
