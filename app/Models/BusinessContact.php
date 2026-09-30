<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class BusinessContact extends Model
{
    protected $fillable = [
        'uuid',
        'display_name',
        'status',
        'source',
        'claimed_by_type',
        'claimed_by_id',
        'claimed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contact): void {
            $contact->uuid ??= (string) Str::uuid();
        });
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function contactPoints(): MorphMany
    {
        return $this->morphMany(ContactPoint::class, 'contactable');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(ActorAddress::class, 'addressable');
    }
}
