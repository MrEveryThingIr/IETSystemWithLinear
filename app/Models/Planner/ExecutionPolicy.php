<?php

namespace App\Models\Planner;

use App\Models\PlanScheduleRule;
use App\PlanTimingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class ExecutionPolicy extends Model
{
    protected $table = 'plan_execution_policies';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
            $row->created_at ??= now();
        });
        static::updating(function (): never {
            throw new LogicException('Execution policy versions are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Execution policies preserve history.');
        });
    }

    /** @return array{version: int, window_style: string, reporting_days: int} */
    public static function snapshot(?PlanScheduleRule $rule): array
    {
        $policy = $rule?->execution_policy_id === null ? null : self::query()->find($rule->execution_policy_id);

        return ['version' => (int) ($policy->version ?? 0),
            'window_style' => $policy->window_style ?? ($rule?->timing_mode === PlanTimingMode::FlexibleDay ? 'flexible' : 'hard'),
            'reporting_days' => (int) ($policy->reporting_days ?? 30)];
    }
}
