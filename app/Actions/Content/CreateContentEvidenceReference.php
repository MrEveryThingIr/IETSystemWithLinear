<?php

namespace App\Actions\Content;

use App\ContentEvidenceTarget;
use App\Models\Actor;
use App\Models\ContentEvidenceReference;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateContentEvidenceReference
{
    /** @param array<string, mixed> $metadata */
    public function execute(
        SpaceContent $content,
        SpaceContentRevision $revision,
        User $user,
        ContentEvidenceTarget $targetType = ContentEvidenceTarget::Revision,
        ?string $targetUuid = null,
        ?string $fieldKey = null,
        array $metadata = [],
    ): ContentEvidenceReference {
        return DB::transaction(function () use (
            $content,
            $revision,
            $user,
            $targetType,
            $targetUuid,
            $fieldKey,
            $metadata,
        ): ContentEvidenceReference {
            $current = SpaceContent::query()->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('view', $current);

            $sealed = SpaceContentRevision::query()
                ->where('space_content_id', $current->id)
                ->lockForUpdate()
                ->findOrFail($revision->id);

            abort_unless($sealed->hasVerifiableManifest(), 422, 'Evidence references require a sealed published revision.');

            [$targetUuid, $fieldKey] = $this->validateTarget(
                $sealed,
                $targetType,
                $targetUuid,
                $fieldKey,
            );

            $actor = $this->actor($user);

            return ContentEvidenceReference::query()->create([
                'context_id' => $current->context_id,
                'space_content_id' => $current->id,
                'space_content_revision_id' => $sealed->id,
                'target_type' => $targetType,
                'target_uuid' => $targetUuid,
                'field_key' => $fieldKey,
                'created_by_actor_id' => $actor->id,
                'metadata' => $metadata,
            ]);
        }, 3);
    }

    /** @return array{?string, ?string} */
    private function validateTarget(
        SpaceContentRevision $revision,
        ContentEvidenceTarget $targetType,
        ?string $targetUuid,
        ?string $fieldKey,
    ): array {
        if ($targetType === ContentEvidenceTarget::Revision) {
            return [null, null];
        }

        if ($targetType === ContentEvidenceTarget::Field) {
            $fieldKey = trim((string) $fieldKey);
            $version = $revision->definitionVersion()->firstOrFail();
            $exists = collect($version->schema['fields'] ?? [])
                ->contains(fn (mixed $field): bool => is_array($field) && ($field['key'] ?? null) === $fieldKey);
            abort_unless($fieldKey !== '' && $exists, 422, 'Evidence field is not part of this revision.');

            return [null, $fieldKey];
        }

        $targetUuid = trim((string) $targetUuid);
        abort_unless(Str::isUuid($targetUuid), 422, 'Evidence target UUID is invalid.');

        $exists = match ($targetType) {
            ContentEvidenceTarget::Block => $revision->blocks()->where('uuid', $targetUuid)->exists(),
            ContentEvidenceTarget::Asset => DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $revision->id)
                ->where('uuid', $targetUuid)
                ->exists(),
            ContentEvidenceTarget::Relationship => $revision->relationships()->where('uuid', $targetUuid)->exists(),
        };

        abort_unless($exists, 422, 'Evidence target is not part of this revision.');

        return [$targetUuid, null];
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
