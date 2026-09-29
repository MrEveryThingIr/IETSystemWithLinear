<?php

namespace App\Models;

use Database\Factories\DevelopmentOriginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'created_by_actor_id',
    'supersedes_origin_id',
    'source_type',
    'source_url',
    'title',
    'summary',
    'phase_key',
    'system_version',
    'branch',
    'baseline_commit_sha',
    'result_commit_sha',
    'repository_paths',
    'occurred_at',
])]
class DevelopmentOrigin extends Model
{
    /** @use HasFactory<DevelopmentOriginFactory> */
    use HasFactory;

    public const SOURCE_TYPES = [
        'chatgpt',
        'design_session',
        'external_discussion',
        'manual_note',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $origin): void {
            $origin->uuid ??= (string) Str::uuid();

            if (! in_array($origin->source_type, self::SOURCE_TYPES, true)) {
                throw new LogicException('Unknown development origin source type.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Development origins are immutable historical provenance.');
        });

        static::deleting(function (): never {
            throw new LogicException('Development origins are preserved as historical provenance.');
        });
    }

    protected function casts(): array
    {
        return [
            'repository_paths' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Actor, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return BelongsTo<self, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_origin_id');
    }
}
