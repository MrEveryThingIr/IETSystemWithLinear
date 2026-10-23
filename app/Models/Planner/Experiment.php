<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class Experiment extends Model
{
    protected $table = 'planner_experiments';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
            $row->created_at ??= now();
        });
        static::updating(function (): never {
            throw new LogicException('Create a new experiment to change its hypothesis or target.');
        });
        static::deleting(function (): never {
            throw new LogicException('Experiment history is immutable.');
        });
    }

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'baseline' => 'decimal:3', 'target' => 'decimal:3'];
    }
}
