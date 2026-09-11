<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admission_id', 'actor_id', 'event', 'note', 'metadata'])]
class AdmissionEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(422, 'Admission event evidence is immutable.'));
        static::deleting(fn (): never => abort(422, 'Admission event evidence is immutable.'));
    }

    /** @return BelongsTo<Admission, $this> */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
