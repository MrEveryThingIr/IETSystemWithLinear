<?php

namespace App\Support;

use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * "Part of <Course> · Previous / Next" for a published lesson or chapter.
 *
 * Uses only sealed "contains" relationships of the parent's current published
 * edition, and only links to pieces the reader may open, so course
 * navigation never reveals unpublished or private Content.
 */
final class ContentCourseNavigation
{
    /**
     * @return array{
     *   parent: array{title: string, url: string},
     *   previous: array{title: string, url: string}|null,
     *   next: array{title: string, url: string}|null,
     *   position: int,
     *   total: int
     * }|null
     */
    public function for(SpaceContent $content, User $user): ?array
    {
        $memberships = SpaceContentRevisionRelationship::query()
            ->where('child_content_id', $content->id)
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->whereNotNull('sealed_at')
            ->with(['parentRevision.content.context'])
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($memberships as $membership) {
            $parentRevision = $membership->parentRevision;
            $parent = $parentRevision?->content;
            if (! $parentRevision instanceof SpaceContentRevision
                || ! $parent instanceof SpaceContent
                || $parent->status !== 'published'
                || (int) $parent->active_revision_id !== (int) $parentRevision->id
                || ! Gate::forUser($user)->allows('view', $parent)) {
                continue;
            }

            $siblings = $parentRevision->relationships()
                ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
                ->whereNotNull('sealed_at')
                ->with(['childContent.context', 'childRevision'])
                ->orderBy('position')
                ->get()
                ->values();

            $index = $siblings->search(fn (SpaceContentRevisionRelationship $sibling): bool => (int) $sibling->child_content_id === (int) $content->id);
            if ($index === false) {
                continue;
            }

            return [
                'parent' => ['title' => $parentRevision->title, 'url' => route('contexts.contents.show', [$parent->context, $parent])],
                'previous' => $this->link($siblings->get($index - 1), $user),
                'next' => $this->link($siblings->get($index + 1), $user),
                'position' => $index + 1,
                'total' => $siblings->count(),
            ];
        }

        return null;
    }

    /** @return array{title: string, url: string}|null */
    private function link(?SpaceContentRevisionRelationship $relationship, User $user): ?array
    {
        $child = $relationship?->childContent;
        if (! $child instanceof SpaceContent
            || $child->status !== 'published'
            || ! Gate::forUser($user)->allows('view', $child)) {
            return null;
        }

        return [
            'title' => $child->activeRevisionRecord()->title ?? $relationship->childRevision->title ?? '',
            'url' => route('contexts.contents.show', [$child->context, $child]),
        ];
    }
}
