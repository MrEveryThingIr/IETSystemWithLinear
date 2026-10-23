<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class OccurrenceParticipation extends Model
{
    protected $table = 'plan_occurrence_participations';

    protected $guarded = ['id'];

    protected $attributes = ['phase' => 'not_started', 'active_seconds' => 0, 'confidence' => 'self_reported'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'immutable_datetime', 'attended_at' => 'immutable_datetime',
            'actual_start_at' => 'immutable_datetime', 'actual_end_at' => 'immutable_datetime', 'active_seconds' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $row): void {
            if ($row->isDirty(['plan_occurrence_id', 'actor_id'])) {
                throw new LogicException('Participant identity is immutable.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Participation preserves history.');
        });
    }
}
