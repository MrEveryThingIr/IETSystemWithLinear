<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ExecutionSegment extends Model
{
    protected $table = 'plan_execution_segments';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
            $row->created_at ??= now();
        });
        static::updating(function (self $row): void {
            if ($row->isDirty(['uuid', 'plan_occurrence_id', 'actor_id', 'started_at']) || $row->getRawOriginal('ended_at') !== null) {
                throw new LogicException('Segments can be closed once, never rewritten.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Segments preserve reported history.');
        });
    }
}
