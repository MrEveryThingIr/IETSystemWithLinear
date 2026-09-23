<?php

namespace App\Actions\Content;

use App\Actions\Contexts\EnsureReferenceContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
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
        private readonly EnsureReferenceContext $ensureContext,
    ) {}

    /**
     * @return array{
     *     context: Context,
     *     root: SpaceContent,
     *     chapters: Collection<int, SpaceContent>
     * }
     */
    public function execute(User $owner, bool $syncSource = false): array
    {
        $actor = $this->actor($owner);
        $context = $this->ensureContext->execute($owner, SystemManualContent::REFERENCE_KEY);
        $catalog = $this->blueprints->execute();

        $guideVersion = $this->activeBlueprintVersion($catalog, 'guide-documentation');
        $bookVersion = $this->activeBlueprintVersion($catalog, 'book-booklet');
        $manual = $this->source->english();

        $chapters = collect();

        foreach ($manual['chapters'] as $chapter) {
            $chapters->push($this->ensureContent(
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
                syncSource: $syncSource,
            ));
        }

        $existingRoot = $this->findContent($context, $actor, SystemManualContent::ROOT_TITLE);

        $root = $this->ensureContent(
            $context,
            $bookVersion,
            $owner,
            SystemManualContent::ROOT_TITLE,
            ['summary' => $manual['summary']],
            publish: false,
            syncSource: $syncSource,
        );

        if (! $existingRoot instanceof SpaceContent || $syncSource) {
            $root = $this->updateStructure->execute(
                $root,
                $owner,
                $chapters->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            );
        }

        return [
            'context' => $context->refresh(),
            'root' => $this->publishIfDraft($root, $owner)->refresh(),
            'chapters' => $chapters->map(static fn (SpaceContent $content): SpaceContent => $content->refresh()),
        ];
    }

    /** @param Collection<int, ContentBlueprint> $catalog */
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

    /** @param array<string, mixed> $payload */
    private function ensureContent(
        Context $context,
        ContentBlueprintVersion $blueprint,
        User $owner,
        string $title,
        array $payload,
        bool $publish = true,
        bool $syncSource = false,
    ): SpaceContent {
        $actor = $this->actor($owner);

        $content = $this->findContent($context, $actor, $title);

        if (! $content instanceof SpaceContent) {
            $content = $this->createContent->execute(
                $context,
                $blueprint,
                $owner,
                $title,
                $payload,
            );
        } elseif ($syncSource) {
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

    private function findContent(Context $context, Actor $actor, string $title): ?SpaceContent
    {
        return SpaceContent::query()
            ->where('context_id', $context->id)
            ->where('author_actor_id', $actor->id)
            ->whereHas('revisions', static fn ($query) => $query->where('title', $title))
            ->orderBy('id')
            ->first();
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
