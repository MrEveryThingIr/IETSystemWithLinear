<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Business extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'kind',
        'short_intro',
        'description',
        'founded_year',
        'timezone',
        'default_monetary_unit_id',
        'settings',
        'status',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            $business->uuid ??= (string) Str::uuid();

            if (! $business->code) {
                do {
                    $code = 'BIZ-'.Str::upper(Str::random(8));
                } while (self::query()->where('code', $code)->exists());

                $business->code = $code;
            }

            $business->slug = self::uniqueSlug($business->name, $business->slug);
        });

        static::saving(function (self $business): void {
            if (! filled($business->slug)) {
                $business->slug = self::uniqueSlug($business->name);
            }
        });
    }

    private static function uniqueSlug(string $name, ?string $requested = null): string
    {
        $base = Str::slug((string) ($requested ?: $name)) ?: 'business';
        $candidate = $base;
        $suffix = 2;

        while (self::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'owner_actor_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function contactPoints(): MorphMany
    {
        return $this->morphMany(ContactPoint::class, 'contactable');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(ActorAddress::class, 'addressable');
    }

    public function businessContacts(): MorphMany
    {
        return $this->morphMany(BusinessContact::class, 'owner');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(BusinessCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(BusinessListing::class)->latest();
    }

    public function publicIntakePortals(): HasMany
    {
        return $this->hasMany(PublicIntakePortal::class);
    }

    public function contextBinding(): HasOne
    {
        return $this->hasOne(BusinessContext::class);
    }

    public function defaultMonetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class, 'default_monetary_unit_id');
    }
}
