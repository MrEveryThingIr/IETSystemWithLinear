<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActorProfession extends Model
{
    protected $fillable = [
        'profession_id',
        'level',
        'years_experience',
        'is_primary',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<Profession, $this> */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }
}
