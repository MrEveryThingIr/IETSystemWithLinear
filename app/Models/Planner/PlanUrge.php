<?php

namespace App\Models\Planner;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One moment the old habit called: what was done instead, or that it won this time. */
class PlanUrge extends Model
{
    public const OUTCOMES = ['replaced', 'gave_in'];

    public const UPDATED_AT = null;

    protected $table = 'plan_urges';

    protected $fillable = ['uuid', 'plan_id', 'period_id', 'actor_id', 'occurred_at', 'outcome', 'strength', 'note'];

    protected static function booted(): void
    {
        static::creating(function (self $urge): void {
            $urge->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'strength' => 'integer'];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
