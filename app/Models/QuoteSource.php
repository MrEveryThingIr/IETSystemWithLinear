<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'key',
    'name',
    'source_type',
    'trust_tier',
    'active',
    'metadata',
])]
class QuoteSource extends Model
{
    protected $attributes = [
        'source_type' => 'manual',
        'trust_tier' => 1,
        'active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            $source->uuid ??= (string) Str::uuid();
            $source->key = strtolower(trim((string) $source->key));
        });

        static::updating(function (self $source): void {
            if ($source->isDirty(['uuid', 'key'])) {
                throw new LogicException('Quote source identity is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Quote sources preserve valuation provenance and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<MarketQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(MarketQuote::class);
    }

    protected function casts(): array
    {
        return [
            'trust_tier' => 'integer',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
