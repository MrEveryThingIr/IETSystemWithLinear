<?php

namespace App\Models;

use App\Support\SpaceContentSchema;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['space_content_id', 'definition_version_id', 'revision', 'title', 'payload', 'created_by_actor_id', 'content_hash'])]
class SpaceContentRevision extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            $content = SpaceContent::query()->findOrFail($revision->space_content_id);
            $version = SpaceContentDefinitionVersion::query()->findOrFail($revision->definition_version_id);
            if ((int) $version->space_content_definition_id !== (int) $content->space_content_definition_id) {
                throw new LogicException('Content Revision definition version must belong to the Content Definition.');
            }
            if ((int) $revision->revision < 1) {
                throw new LogicException('Content revision number must be positive.');
            }

            $revision->payload = SpaceContentSchema::normalizePayload($version->schema, $revision->payload ?? []);
            $revision->content_hash = SpaceContentSchema::hashRevision($revision->title, $revision->payload);
        });

        static::updating(function (): never {
            throw new LogicException('Content revisions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Content revisions are immutable history.');
        });
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<SpaceContentDefinitionVersion, $this> */
    public function definitionVersion(): BelongsTo
    {
        return $this->belongsTo(SpaceContentDefinitionVersion::class, 'definition_version_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }
}
