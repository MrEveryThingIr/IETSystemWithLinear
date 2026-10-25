<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IntentDetailTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'terms' => 'array', 'context' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->uuid ??= (string) Str::uuid();
        });
    }
}
