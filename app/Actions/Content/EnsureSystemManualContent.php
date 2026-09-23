<?php

namespace App\Actions\Content;

use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SystemManualContent;
use Illuminate\Support\Collection;

class EnsureSystemManualContent
{
    public function __construct(
        private readonly SystemManualContent $source,
        private readonly EnsureSystemContentBlueprints $blueprints,
        private readonly CreateContentFromBlueprint $createContent,
        private readonly ReviseSpaceContent $reviseContent,
        private readonly PublishSpaceContent $publishContent,
        private readonly UpdateSpaceContentStructure $updateStructure,
        private readonly CreateGroup $createGroup,
        private readonly CreateGroupSpace $createSpace,
        private readonly EnsureGroupSpaceContext $ensureContext,
    ) {}

    /**
     * @return array{
     *     group: Group,
     *     space: GroupSpace,
     *     context: Context,
     *     root: SpaceContent,
     *     chapters: Collection<int, SpaceContent>
     * }
     */
    public function execute(User $owner): array
    {
        $actor = $this->actor($owner);
        $group = $this->manualGroup($actor);
        $space = $this->manualSpace($group, $owner);
        $context = $this->ensureContext->execute($space);
        $catalog = $this->blueprints->execute();

        $guideVersion = $this->activeBlueprintVersion($catalog, 'guide-documentation');
        $bookVersion = $this->activeBlueprintVersion($catalog, 'book-booklet');
        $manual = $this->source->english();

        $chapters = collect();

        foreach ($manual['chapters'] as $chapter) {
            $content = $this->ensureContent(
                $context,
                $guideVersion,
                $owner,
                $chapter['title'],
                [
                    'summary' => $chapter['summary'],
                    'current_behavior' => $chapter['current_behavior'],
                    'how_to_use' => $chapter['how_to_use'],
                    'authorization' => $chapter['authorization'],
                    'ideal_target' => $chapter['ideal_target'],
                    'misunderstandings' => $chapter['misunderstandings'],
                ],
            );

            $chapters->push($content);
        }

        $root = $this->ensureContent(
            $context,
            $bookVersion,
            $owner,
            SystemManualContent::ROOT_TITLE,
            ['summary' => $manual['summary']],
            publish: false,
        );

        $root = $this->updateStructure->execute(
            $root,
            $owner,
            $chapters->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        $root = $this->publishIfDraft($root, $owner);

        return [
            'group' => $group->refresh(),
            'space' => $space->refresh(),
            'context' => $context->refresh(),
            'root' => $root->refresh(),
            'chapters' => $chapters->map(static fn (SpaceContent $content): SpaceContent => $content->refresh()),
        ];
    }

    private function manualGroup(Actor $actor): Group
    {
        $group = Group::query()
            ->where('created_by_actor_id', $actor->id)
            ->where('name', SystemManualContent::GROUP_NAME)
            ->first();

        if ($group instanceof Group) {
            return $group;
        }

        return $this->createGroup->execute(
            $actor,
            SystemManualContent::GROUP_NAME,
            'Official IET educational content, product guidance, questions, corrections, and improvement proposals.',
            'UTC',
        );
    }

    private function manualSpace(Group $group, User $owner): GroupSpace
    {
        $space = GroupSpace::query()
            ->where('group_id', $group->id)
            ->where('slug', SystemManualContent::SPACE_SLUG)
            ->first();

        if ($space instanceof GroupSpace) {
            return $space;
        }

        return $this->createSpace->execute(
            $group,
            $owner,
            SystemManualContent::SPACE_NAME,
            'group',
        );
    }

    /**
     * @param  Collection<int, ContentBlueprint>  $catalog
     */
    private function activeBlueprintVersion(Collection $catalog, string $slug): ContentBlueprintVersion
    {
        $blueprint = $catalog->first(
            static fn (ContentBlueprint $item): bool => $item->slug === $slug,
        );

        abort_unless($blueprint instanceof ContentBlueprint, 500, 'Required system Content Blueprint is unavailable.');

        $version = $blueprint->activeVersion;
        abort_unless(
            $version instanceof ContentBlueprintVersion && $version->published_at !== null,
            500,
            'Required system Content Blueprint version is unavailable.',
        );

        return $version;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureContent(
        Context $context,
        ContentBlueprintVersion $blueprint,
        User $owner,
        string $title,
        array $payload,
        bool $publish = true,
    ): SpaceContent {
        $actor = $this->actor($owner);

        $content = SpaceContent::query()
            ->where('context_id', $context->id)
            ->where('author_actor_id', $actor->id)
            ->whereHas('revisions', static fn ($query) => $query->where('title', $title))
            ->orderBy('id')
            ->first();

        if (! $content instanceof SpaceContent) {
            $content = $this->createContent->execute(
                $context,
                $blueprint,
                $owner,
                $title,
                $payload,
            );
        } else {
            $revision = $content->currentRevisionRecord();

            if ($revision->title !== $title || $revision->payload !== $payload) {
                $content = $this->reviseContent->execute(
                    $content,
                    $owner,
                    $title,
                    $payload,
                );
            }
        }

        $desiredInteractions = [
            'annotations' => true,
            'reactions' => true,
            'default_annotation_visibility' => 'space',
        ];

        if ($content->interaction_settings !== $desiredInteractions) {
            $content->update(['interaction_settings' => $desiredInteractions]);
        }

        return $publish
            ? $this->publishIfDraft($content, $owner)
            : $content->refresh();
    }

    private function publishIfDraft(SpaceContent $content, User $owner): SpaceContent
    {
        $current = $content->refresh();

        if ($current->draftRevisionRecord() instanceof SpaceContentRevision) {
            return $this->publishContent->execute($current, $owner);
        }

        return $current;
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
                && $current->status === 'active'
                && $current->email_verified_at !== null
                && $current->actor instanceof Actor
                && $current->actor->status === 'active',
            403,
        );

        return $current->actor;
    }
}
