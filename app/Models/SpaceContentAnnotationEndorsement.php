<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['annotation_id', 'actor_id', 'created_at'])]
class SpaceContentAnnotationEndorsement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<SpaceContentAnnotation, $this> */
    public function annotation(): BelongsTo
    {
        return $this->belongsTo(SpaceContentAnnotation::class, 'annotation_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
