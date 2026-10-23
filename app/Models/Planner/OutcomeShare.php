<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

class OutcomeShare extends Model
{
    protected $table = 'planner_outcome_shares';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            $row->uuid ??= (string) Str::uuid();
            $row->created_at ??= now();
        });
        static::updating(function (self $row): void {
            if ($row->isDirty(['uuid', 'subject_actor_id', 'recipient_actor_id', 'purpose', 'plan_ids', 'snapshot', 'expires_at', 'created_at'])
                || $row->getOriginal('revoked_at') !== null) {
                throw new LogicException('Share consent is immutable; revoke and issue a new scope.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('Revoke consent to preserve its audit record.');
        });
    }

    protected function casts(): array
    {
        return ['plan_ids' => 'array', 'snapshot' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
