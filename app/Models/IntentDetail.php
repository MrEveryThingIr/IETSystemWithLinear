<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IntentDetail extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $detail): void {
            $detail->uuid ??= (string) Str::uuid();
        });
    }
}
