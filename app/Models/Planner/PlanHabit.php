<?php

namespace App\Models\Planner;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The habit loop of a plan (The Power of Habit): the cue that starts it, the
 * routine (the plan itself), the reward, and why it matters. "replace" mode
 * keeps the cue and the reward of an old habit and puts a new routine in its
 * place; the old habit is named in "replaces".
 */
class PlanHabit extends Model
{
    public const MODES = ['build', 'replace'];

    protected $table = 'plan_habits';

    protected $fillable = ['uuid', 'plan_id', 'created_by_actor_id', 'mode', 'cue', 'reward', 'why', 'replaces'];

    protected static function booted(): void
    {
        static::creating(function (self $habit): void {
            $habit->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function replaces(): bool
    {
        return $this->mode === 'replace';
    }
}
