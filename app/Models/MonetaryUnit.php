<?php

namespace App\Models;

use Database\Factories\MonetaryUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'code', 'name', 'symbol', 'exponent'])]
class MonetaryUnit extends Model
{
    /** @use HasFactory<MonetaryUnitFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $unit): void {
            $unit->uuid ??= (string) Str::uuid();
            $unit->code = strtoupper(trim((string) $unit->code));
        });

        static::updating(function (self $unit): void {
            if ($unit->isDirty(['uuid', 'code', 'exponent'])) {
                throw new LogicException('Monetary unit identity and exponent are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Monetary units cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<Ledger, $this> */
    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    protected function casts(): array
    {
        return ['exponent' => 'integer'];
    }
}
