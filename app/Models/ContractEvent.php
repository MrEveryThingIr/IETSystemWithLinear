<?php

namespace App\Models;

use App\ContractEventType;
use Database\Factories\ContractEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'contract_id',
    'contract_version_id',
    'actor_id',
    'event_type',
    'payload',
])]
class ContractEvent extends Model
{
    /** @use HasFactory<ContractEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Contract events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Contract events preserve authoritative lifecycle history.');
        });
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<ContractVersion, $this> */
    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    protected function casts(): array
    {
        return [
            'event_type' => ContractEventType::class,
            'payload' => 'array',
        ];
    }
}
