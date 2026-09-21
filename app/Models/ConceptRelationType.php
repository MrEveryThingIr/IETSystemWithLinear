<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ConceptRelationType extends Model
{
    protected static function booted(): void
    {
        static::creating(function (): never {
            throw new LogicException('Concept relation types are a trusted application registry.');
        });

        static::updating(function (): never {
            throw new LogicException('Concept relation types are a trusted application registry.');
        });

        static::deleting(function (): never {
            throw new LogicException('Concept relation types are a trusted application registry.');
        });
    }

    /** @return HasMany<ConceptRelation, $this> */
    public function relations(): HasMany
    {
        return $this->hasMany(ConceptRelation::class, 'relation_type_id');
    }

    protected function casts(): array
    {
        return [
            'symmetric' => 'boolean',
            'transitive' => 'boolean',
        ];
    }
}
