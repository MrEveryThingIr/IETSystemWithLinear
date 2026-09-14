<?php

namespace App\Support;

use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SpaceContentPublishedOutline
{
    /**
     * @return list<array{
     *   relationship_uuid: string,
     *   content_uuid: string,
     *   revision_uuid: string,
     *   title: string,
     *   author: string,
     *   can_open_current: bool,
     *   children: array<int, mixed>
     * }>
     */
    public function forRevision(SpaceContentRevision $revision, User $user): array
    {
        return $this->children($revision, $user, 0, []);
    }

    /**
     * @param array<int, true> $visitedRevisionIds
     * @return list<array{
     *   relationship_uuid: string,
     *   content_uuid: string,
     *   revision_uuid: string,
     *   title: string,
     *   author: string,
     *   can_open_current: bool,
     *   children: array<int, mixed>
     * }>
     */
    private function children(
        SpaceContentRevision $revision,
        User $user,
        int $depth,
        array $visitedRevisionIds,
    ): array {
        if ($depth >= 20 || isset($visitedRevisionIds[$revision->id])) {
            return [];
        }

        $visitedRevisionIds[$revision->id] = true;
        $relationships = $revision->relationships()
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->whereNotNull('sealed_at')
            ->with(['childContent.author.user', 'childRevision'])
            ->orderBy('position')
            ->get();

        $items = [];
        foreach ($relationships as $relationship) {
            $child = $relationship->childContent;
            $childRevision = $relationship->childRevision;
            if (! $childRevision instanceof SpaceContentRevision) {
                continue;
            }

            $isCurrentEdition = $child->status === 'published'
                && (int) $child->active_revision_id === (int) $childRevision->id;
            $canOpenCurrent = $isCurrentEdition && Gate::forUser($user)->allows('view', $child);

            $items[] = [
                'relationship_uuid' => $relationship->uuid,
                'content_uuid' => $child->uuid,
                'revision_uuid' => $childRevision->uuid,
                'title' => $childRevision->title,
                'author' => $child->author->user?->username ?? 'Unknown author',
                'can_open_current' => $canOpenCurrent,
                'children' => $this->children($childRevision, $user, $depth + 1, $visitedRevisionIds),
            ];
        }

        return $items;
    }
}
