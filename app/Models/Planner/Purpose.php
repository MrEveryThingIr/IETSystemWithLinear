<?php

namespace App\Models\Planner;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use LogicException;

class Purpose extends Model
{
    protected $table = 'planner_purposes';

    protected $attributes = ['status' => 'active'];

    protected $fillable = ['owner_actor_id', 'title', 'description', 'target_on', 'status'];

    protected static function booted(): void
    {
        static::creating(function (self $purpose): void {
            $purpose->uuid ??= (string) Str::uuid();
        });
        static::updating(function (self $purpose): void {
            if ($purpose->isDirty(['uuid', 'owner_actor_id'])) {
                throw new LogicException('Purpose ownership is immutable.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Archive a purpose to preserve its history.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsToMany<Plan, $this> */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'planner_purpose_contributions', 'purpose_id', 'plan_id')->withTimestamps();
    }

    protected function casts(): array
    {
        return ['target_on' => 'immutable_date'];
    }
}
