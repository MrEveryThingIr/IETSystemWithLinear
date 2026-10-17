<?php

namespace App\Support\PublicContent;

use App\Models\Asset;
use App\Models\AssetMediaTrack;
use App\Models\AssetMediaVariant;
use App\Models\ContentInvitation;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single authority for what a guest (anyone, signed in or not) may read.
 *
 * Two ways in, both chosen by the author:
 * - Public: Content whose author opened it to everyone. Listed on /public.
 * - Invitation: a personal link to one piece of Content. A course or module
 *   invitation also opens the lessons it contains, as long as they are the
 *   inviter's own or public themselves, so an invitation never exposes
 *   someone else's private work.
 *
 * Guests only ever see the published edition; drafts, history, comments and
 * places stay private. Anything not readable answers 404, never revealing that
 * it exists.
 */
final class GuestContentAccess
{
    /** How many levels below an invited course are opened (course → module → lesson). */
    public const MAX_DEPTH = 3;

    /** @var array<int, list<int>> */
    private array $invitationScopes = [];

    /** Published, has an edition, and its author's account is active. */
    public function isReadable(SpaceContent $content): bool
    {
        if ($content->status !== 'published' || $content->active_revision_id === null) {
            return false;
        }

        $content->loadMissing('author');

        return $content->author !== null && $content->author->status === 'active';
    }

    public function isPublic(SpaceContent $content): bool
    {
        return $content->public_access === SpaceContent::ACCESS_PUBLIC && $this->isReadable($content);
    }

    public function viaInvitation(ContentInvitation $invitation, SpaceContent $content): bool
    {
        if (! $invitation->isUsable()) {
            return false;
        }

        return in_array((int) $content->id, $this->invitationScope($invitation), true);
    }

    public function allows(SpaceContent $content, ?ContentInvitation $invitation): bool
    {
        return $invitation instanceof ContentInvitation
            ? $this->viaInvitation($invitation, $content)
            : $this->isPublic($content);
    }

    /**
     * Content ids an invitation opens: the invited piece and, below it, the
     * published pieces it contains that belong to the same author or are
     * public themselves.
     *
     * @return list<int>
     */
    public function invitationScope(ContentInvitation $invitation): array
    {
        if (isset($this->invitationScopes[$invitation->id])) {
            return $this->invitationScopes[$invitation->id];
        }

        $root = $invitation->content()->with('author')->first();
        if (! $root instanceof SpaceContent || ! $this->isReadable($root)) {
            return $this->invitationScopes[$invitation->id] = [];
        }

        $allowed = [(int) $root->id];
        $frontier = [$root];

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $next = [];
            foreach ($frontier as $parent) {
                foreach ($this->containedChildren($parent) as $child) {
                    if (in_array((int) $child->id, $allowed, true)) {
                        continue;
                    }

                    $sameAuthor = (int) $child->author_actor_id === (int) $root->author_actor_id;
                    if ($this->isReadable($child) && ($sameAuthor || $this->isPublic($child))) {
                        $allowed[] = (int) $child->id;
                        $next[] = $child;
                    }
                }
            }
            $frontier = $next;
        }

        return $this->invitationScopes[$invitation->id] = $allowed;
    }

    /**
     * Readable children of a course or module, in order, for the reader.
     *
     * @return list<array{title: string, url: string, kind: string|null}>
     */
    public function readableChildren(SpaceContent $parent, ?ContentInvitation $invitation): array
    {
        $children = [];
        foreach ($this->containedChildren($parent) as $child) {
            if ($this->allows($child, $invitation)) {
                $child->loadMissing(['activeRevision', 'blueprintVersion.blueprint']);
                $children[] = [
                    'title' => (string) ($child->activeRevision->title ?? ''),
                    'url' => $this->url($child, $invitation),
                    'kind' => $child->blueprintVersion->blueprint->name ?? null,
                ];
            }
        }

        return $children;
    }

    /**
     * "Part of …, previous, next" inside the same way of access.
     *
     * @return array{parent: array{title: string, url: string}, previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null, position: int, total: int}|null
     */
    public function navigation(SpaceContent $content, ?ContentInvitation $invitation): ?array
    {
        $memberships = SpaceContentRevisionRelationship::query()
            ->where('child_content_id', $content->id)
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->whereNotNull('sealed_at')
            ->with('parentRevision.content')
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($memberships as $membership) {
            $parentRevision = $membership->parentRevision;
            $parent = $parentRevision?->content;
            if (! $parentRevision instanceof SpaceContentRevision
                || ! $parent instanceof SpaceContent
                || (int) $parent->active_revision_id !== (int) $parentRevision->id
                || ! $this->allows($parent, $invitation)) {
                continue;
            }

            $siblings = collect($this->containedChildren($parent))
                ->filter(fn (SpaceContent $sibling): bool => $this->allows($sibling, $invitation))
                ->values();
            $index = $siblings->search(fn (SpaceContent $sibling): bool => (int) $sibling->id === (int) $content->id);
            if ($index === false) {
                continue;
            }

            $link = function (?SpaceContent $sibling) use ($invitation): ?array {
                if (! $sibling instanceof SpaceContent) {
                    return null;
                }
                $sibling->loadMissing('activeRevision');

                return ['title' => (string) ($sibling->activeRevision->title ?? ''), 'url' => $this->url($sibling, $invitation)];
            };

            return [
                'parent' => ['title' => $parentRevision->title, 'url' => $this->url($parent, $invitation)],
                'previous' => $link($siblings->get($index - 1)),
                'next' => $link($siblings->get($index + 1)),
                'position' => $index + 1,
                'total' => $siblings->count(),
            ];
        }

        return null;
    }

    /** The asset belongs to the edition guests are shown. */
    public function assetInEdition(SpaceContent $content, Asset $asset): bool
    {
        return $content->active_revision_id !== null
            && DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $content->active_revision_id)
                ->where('asset_id', $asset->id)
                ->exists();
    }

    public function url(SpaceContent $content, ?ContentInvitation $invitation): string
    {
        if ($invitation instanceof ContentInvitation) {
            return (int) $invitation->space_content_id === (int) $content->id
                ? route('content-invitations.show', $invitation->token())
                : route('content-invitations.content', [$invitation->token(), $content]);
        }

        return route('public-content.show', $content);
    }

    public function assetUrl(SpaceContent $content, Asset $asset, ?ContentInvitation $invitation, bool $download = false): string
    {
        $name = $download ? 'assets.download' : 'assets.show';

        return $invitation instanceof ContentInvitation
            ? route('content-invitations.'.$name, [$invitation->token(), $content, $asset])
            : route('public-content.'.$name, [$content, $asset]);
    }

    public function variantUrl(SpaceContent $content, Asset $asset, AssetMediaVariant $variant, ?ContentInvitation $invitation): string
    {
        return $invitation instanceof ContentInvitation
            ? route('content-invitations.assets.variants.show', [$invitation->token(), $content, $asset, $variant])
            : route('public-content.assets.variants.show', [$content, $asset, $variant]);
    }

    public function trackUrl(SpaceContent $content, Asset $asset, AssetMediaTrack $track, ?ContentInvitation $invitation): string
    {
        return $invitation instanceof ContentInvitation
            ? route('content-invitations.assets.tracks.show', [$invitation->token(), $content, $asset, $track])
            : route('public-content.assets.tracks.show', [$content, $asset, $track]);
    }

    /** @return list<SpaceContent> */
    private function containedChildren(SpaceContent $parent): array
    {
        if ($parent->active_revision_id === null) {
            return [];
        }

        /** @var Collection<int, SpaceContentRevisionRelationship> $relationships */
        $relationships = SpaceContentRevisionRelationship::query()
            ->where('parent_revision_id', $parent->active_revision_id)
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->whereNotNull('sealed_at')
            ->with('childContent.author')
            ->orderBy('position')
            ->get();

        return $relationships
            ->map(fn (SpaceContentRevisionRelationship $relationship): ?SpaceContent => $relationship->childContent)
            ->filter()
            ->values()
            ->all();
    }
}
