<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ExperimentAdjustment extends Model
{
    protected $table = 'planner_experiment_adjustments';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
            $row->created_at ??= now();
        });
        static::updating(function (self $row): void {
            if ($row->getOriginal('decision') !== null || $row->isDirty(['uuid', 'experiment_id', 'proposal', 'created_at'])) {
                throw new LogicException('Adjustment proposals and decisions preserve history.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Adjustment history is immutable.');
        });
    }
}
