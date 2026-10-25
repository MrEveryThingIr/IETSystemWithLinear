<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/** One immutable version of an approved shared field. Only retirement may change a row. */
class IntentFieldDefinition extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'domains' => 'array', 'labels' => 'array', 'descriptions' => 'array', 'aliases' => 'array', 'hidden_options' => 'array',
            'published_at' => 'immutable_datetime', 'retired_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            $definition->uuid ??= (string) Str::uuid();
        });
        static::updating(function (self $definition): void {
            $changed = array_keys($definition->getDirty());
            if (array_diff($changed, ['status', 'retired_at']) !== []) {
                throw new LogicException('Published field definitions are immutable. Publish a new version.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Field definitions are retired, never deleted.');
        });
    }

    /** @param Builder<self> $query */
    public function scopeLatestVersion(Builder $query): void
    {
        $query->whereIn('id', fn ($sub) => $sub->selectRaw('max(id)')->from('intent_field_definitions')->groupBy('field_key'));
    }
}
