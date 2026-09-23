<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use App\Models\User;
use App\Support\SpaceContentRevisionComposition;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateSpaceContentStructure
{
    public function __construct(private readonly SpaceContentRevisionComposition $composition) {}

    /** @param array<int, int|string> $childContentIds */
    public function execute(SpaceContent $content, User $user, array $childContentIds): SpaceContent
    {
        $childContentIds = $this->normalizeIds($childContentIds);
        abort_if(count($childContentIds) > 200, 422, 'A Content structure may contain at most 200 direct children.');

        return DB::transaction(function () use ($content, $user, $childContentIds): SpaceContent {
            $current = SpaceContent::query()->with('context')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content structure cannot be changed.');
            abort_if(in_array($current->id, $childContentIds, true), 422, 'Content cannot contain itself.');

            $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($source instanceof SpaceContentRevision, 422, 'Content has no revision to structure.');
            $source = SpaceContentRevision::query()->lockForUpdate()->findOrFail($source->id);

            /** @var Collection<int, SpaceContent> $contextContents */
            $contextContents = SpaceContent::query()
                ->where('context_id', $current->context_id)
                ->with('space')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($childContentIds as $childId) {
                $child = $contextContents->get($childId);
                abort_unless($child instanceof SpaceContent, 404);
                abort_if($child->status === 'archived', 422, 'Archived Content cannot be added to a structure.');
                Gate::forUser($user)->authorize('view', $child);
            }

            $this->assertAcyclic($current, $childContentIds, $contextContents);

            $existingIds = DB::table('space_content_revision_relationships')
                ->where('parent_revision_id', $source->id)
                ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
                ->orderBy('position')
                ->pluck('child_content_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($existingIds === $childContentIds) {
                return $current->refresh();
            }

            $actor = $this->actor($user);
            $nextRevision = ((int) $current->revisions()->max('revision')) + 1;
            $revision = $current->revisions()->create([
                'uuid' => (string) Str::uuid(),
                'definition_version_id' => $source->definition_version_id,
                'revision' => $nextRevision,
                'title' => $source->title,
                'payload' => $source->payload,
                'render_template_key' => $source->render_template_key,
                'render_template_uuid' => $source->render_template_uuid,
                'presentation' => $source->presentation,
                'composition_mode' => $source->composition_mode,
                'created_by_actor_id' => $actor->id,
                'content_hash' => SpaceContentSchema::hashRevision($source->title, $source->payload),
                'evidence_status' => SpaceContentRevision::EVIDENCE_UNSEALED,
                'manifest_hash' => null,
                'manifest_version' => null,
                'canonicalization_version' => null,
                'manifest_algorithm' => null,
                'canonical_manifest' => null,
                'manifest_sealed_at' => null,
            ]);

            $placementMap = $this->composition->copyAssets($source, $revision);
            $this->composition->copyBlocks($source, $revision, $placementMap);
            $this->composition->copyRelationships(
                $source,
                $revision,
                SpaceContentRevisionRelationship::TYPE_CONTAINS,
            );
            $this->composition->copyConceptAssertions($source, $revision, $actor);

            foreach ($childContentIds as $position => $childId) {
                SpaceContentRevisionRelationship::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'parent_revision_id' => $revision->id,
                    'child_content_id' => $childId,
                    'relation_type' => SpaceContentRevisionRelationship::TYPE_CONTAINS,
                    'position' => $position,
                ]);
            }

            $current->applyLifecycle([
                'current_revision' => $nextRevision,
                'draft_revision_id' => $revision->id,
            ]);

            return $current->refresh();
        }, 3);
    }

    /**
     * @param  list<int>  $proposedChildIds
     * @param  Collection<int, SpaceContent>  $contextContents
     */
    private function assertAcyclic(
        SpaceContent $parent,
        array $proposedChildIds,
        Collection $contextContents,
    ): void {
        $workingRevisionIds = $contextContents
            ->mapWithKeys(static function (SpaceContent $content): array {
                $revisionId = $content->draft_revision_id ?? $content->active_revision_id;

                return [$content->id => $revisionId !== null ? (int) $revisionId : null];
            });

        $revisionIds = $workingRevisionIds->filter()->values()->all();
        $relationships = DB::table('space_content_revision_relationships')
            ->whereIn('parent_revision_id', $revisionIds)
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->orderBy('position')
            ->get();

        $revisionToContent = $workingRevisionIds
            ->filter()
            ->mapWithKeys(static fn (mixed $revisionId, mixed $contentId): array => [(int) $revisionId => (int) $contentId]);

        /** @var array<int, list<int>> $adjacency */
        $adjacency = [];
        foreach ($relationships as $relationship) {
            $parentContentId = $revisionToContent->get((int) $relationship->parent_revision_id);
            if (! is_int($parentContentId)) {
                continue;
            }

            $adjacency[$parentContentId][] = (int) $relationship->child_content_id;
        }
        $adjacency[$parent->id] = $proposedChildIds;

        foreach ($proposedChildIds as $childId) {
            $queue = [$childId];
            $visited = [];

            while ($queue !== []) {
                $candidate = array_shift($queue);
                if ($candidate === $parent->id) {
                    abort(422, 'Content containment cannot create a cycle.');
                }

                if (isset($visited[$candidate])) {
                    continue;
                }
                $visited[$candidate] = true;

                if (count($visited) > 1000) {
                    abort(422, 'Content structure is too deep to validate safely.');
                }

                foreach ($adjacency[$candidate] ?? [] as $nextId) {
                    $queue[] = $nextId;
                }
            }
        }
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $stringId = (string) $id;
            abort_unless(ctype_digit($stringId) && (int) $stringId > 0, 422, 'Content structure contains an invalid Content identifier.');
            $normalized[] = (int) $stringId;
        }

        abort_if(count($normalized) !== count(array_unique($normalized)), 422, 'Content cannot contain the same child more than once.');

        return $normalized;
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
