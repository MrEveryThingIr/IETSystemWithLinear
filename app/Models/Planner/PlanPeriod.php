<?php

namespace App\Models\Planner;

use App\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A tuning period: a stretch of days in which one thing is set a certain
 * way (a replacement routine, an in-between target time, a preparation),
 * like setting a radio to one frequency, so periods can be compared.
 *
 * @property ?array<int, mixed> $automaticity Four answers (1–5) of the
 *                                            Self-Report Behavioural Automaticity Index (Gardner et al., 2012).
 */
class PlanPeriod extends Model
{
    public const VERDICTS = ['keep', 'adjust', 'drop'];

    public const LENGTHS = [7, 14, 21, 30, 66];

    protected $table = 'plan_periods';

    protected $fillable = ['uuid', 'plan_id', 'number', 'focus', 'target_time', 'starts_on', 'ends_on', 'ended_on', 'verdict', 'reflection', 'automaticity'];

    protected static function booted(): void
    {
        static::creating(function (self $period): void {
            $period->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'ended_on' => 'immutable_date',
            'automaticity' => 'array',
            'number' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** The last day that belongs to this period. */
    public function lastDay(): CarbonImmutable
    {
        return $this->ended_on ?? $this->ends_on;
    }

    public function covers(string $date): bool
    {
        return $date >= $this->starts_on->toDateString() && $date <= $this->lastDay()->toDateString();
    }

    public function days(): int
    {
        return (int) $this->starts_on->diffInDays($this->lastDay()) + 1;
    }

    public function isFinished(): bool
    {
        return $this->verdict !== null;
    }

    public function automaticityScore(): ?float
    {
        $answers = array_values(array_filter((array) $this->automaticity, fn ($value) => is_numeric($value)));

        return count($answers) === 4 ? round(array_sum($answers) / 4, 1) : null;
    }
}
