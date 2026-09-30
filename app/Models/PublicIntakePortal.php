<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PublicIntakePortal extends Model
{
    protected $fillable = [
        'uuid',
        'public_token',
        'type',
        'title',
        'welcome_heading',
        'welcome_body',
        'success_message',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $portal): void {
            $portal->uuid ??= (string) Str::uuid();
            $portal->public_token ??= Str::random(48);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function realEstateCases(): HasMany
    {
        return $this->hasMany(PublicRealEstateCase::class, 'public_intake_portal_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(PublicIntakePortalGrant::class, 'public_intake_portal_id');
    }

}
