<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ExperimentObservation extends Model
{
    protected $table = 'planner_experiment_observations';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
            $row->created_at ??= now();
        });
        static::updating(function (): never {
            throw new LogicException('Correct an observation with a new immutable observation.');
        });
        static::deleting(function (): never {
            throw new LogicException('Observation history is immutable.');
        });
    }

    protected function casts(): array
    {
        return ['observed_at' => 'immutable_datetime', 'value' => 'decimal:3', 'context' => 'array'];
    }
}
