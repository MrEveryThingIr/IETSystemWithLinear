<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admission_id', 'actor_id', 'event', 'note', 'metadata'])]
class AdmissionEvent extends Model
{
    protected function casts(): array { return ['metadata' => 'array']; }
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function actor(): BelongsTo { return $this->belongsTo(Actor::class); }
}
