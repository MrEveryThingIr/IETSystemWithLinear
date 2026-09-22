<?php

namespace App\Models;

use App\ContentEvidenceTarget;
use Database\Factories\ContentEvidenceReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'context_id',
    'space_content_id',
    'space_content_revision_id',
    'target_type',
    'target_uuid',
    'field_key',
    'created_by_actor_id',
    'metadata',
])]
class ContentEvidenceReference extends Model
{
    /** @use HasFactory<ContentEvidenceReferenceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $reference): void {
            $reference->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Content evidence references are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content evidence references are durable historical locators.');
        });
    }

    protected function casts(): array
    {
        return [
            'target_type' => ContentEvidenceTarget::class,
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'space_content_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }
}
