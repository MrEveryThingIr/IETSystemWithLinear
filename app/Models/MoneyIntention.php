<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'user_id',
    'ledger_id',
    'kind',
    'title',
    'amount_minor',
    'target_on',
    'notes',
    'status',
])]
class MoneyIntention extends Model
{
    protected $attributes = ['status' => 'open'];

    protected static function booted(): void
    {
        static::creating(function (self $intention): void {
            $intention->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'target_on' => 'date:Y-m-d',
        ];
    }
}
