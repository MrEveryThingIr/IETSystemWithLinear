<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class IntentVersion extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'published_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
        });
        static::updating(function (): never {
            throw new LogicException('Published versions are immutable. Publish a new version.');
        });
        static::deleting(function (): never {
            throw new LogicException('Published versions preserve history.');
        });
    }
}
