<?php

namespace App\Models;

use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\Parties\Party;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * The shape of a schedule that can be reused: how often, at what time, for
 * how long, reminders and allowed early/late windows. It never holds people,
 * dates or history, so applying it to new work shares nothing from old work.
 */
#[Fillable([
    'owner_actor_id', 'owner_business_id', 'owner_group_id', 'name', 'frequency', 'timing_mode', 'interval',
    'start_time', 'duration_minutes', 'weekdays', 'month_day', 'occurrence_limit', 'window_before_minutes',
    'window_after_minutes', 'reminder_offsets', 'status', 'created_by_actor_id', 'source_plan_uuid',
])]
class PlanTemplate extends Model
{
    public const FREQUENCIES = [PlanScheduleFrequency::Once, PlanScheduleFrequency::Daily, PlanScheduleFrequency::Weekly, PlanScheduleFrequency::Monthly];

    protected function casts(): array
    {
        return [
            'frequency' => PlanScheduleFrequency::class,
            'timing_mode' => PlanTimingMode::class,
            'weekdays' => 'array',
            'reminder_offsets' => 'array',
            'interval' => 'integer',
            'duration_minutes' => 'integer',
            'month_day' => 'integer',
            'occurrence_limit' => 'integer',
            'window_before_minutes' => 'integer',
            'window_after_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->uuid ??= (string) Str::uuid();
        });
        static::updating(function (self $template): void {
            foreach (['owner_actor_id', 'owner_business_id', 'owner_group_id', 'created_by_actor_id'] as $column) {
                if ($template->isDirty($column)) {
                    throw new LogicException('A template keeps its owner.');
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): Party
    {
        $owner = Party::fromRecord($this, 'owner');
        if (! $owner instanceof Party) {
            throw new LogicException('A template always has an owner.');
        }

        return $owner;
    }

    /**
     * Active templates of every party the given ones stand for.
     *
     * @param  Builder<PlanTemplate>  $query
     * @param  list<Party>  $parties
     */
    public function scopeOwnedByAny(Builder $query, array $parties): void
    {
        $query->where('status', 'active')->where(function (Builder $owned) use ($parties): void {
            $owned->whereRaw('1 = 0');
            foreach ($parties as $party) {
                $owned->orWhere($party->column('owner'), $party->id);
            }
        });
    }
}
