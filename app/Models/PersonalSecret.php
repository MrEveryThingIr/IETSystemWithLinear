<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'user_id',
    'kind',
    'title',
    'identifier',
    'secret',
    'url',
    'notes',
])]
#[Hidden(['identifier', 'secret', 'url', 'notes'])]
class PersonalSecret extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $secret): void {
            $secret->uuid ??= (string) Str::uuid();
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

    protected function casts(): array
    {
        return [
            'identifier' => 'encrypted',
            'secret' => 'encrypted',
            'url' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }
}
