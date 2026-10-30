<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A pointer from a Plan (and optionally one occurrence) to a piece of Content. It grants no access to either. */
#[Fillable(['uuid', 'plan_id', 'plan_occurrence_id', 'space_content_id', 'linked_by_actor_id'])]
class PlanContentLink extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<PlanOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(PlanOccurrence::class, 'plan_occurrence_id');
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }
}
