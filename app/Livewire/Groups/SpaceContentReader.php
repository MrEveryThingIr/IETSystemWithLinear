<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\ToggleSpaceContentReaction;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentReaction;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentPublishedOutline;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
#[Title('Content')]
class SpaceContentReader extends Component
{
    public Group $group;

    public GroupSpace $space;

    public SpaceContent $content;

    public string $revisionUuid = '';

    public string $commentBody = '';

    public ?string $replyingTo = null;

    public string $replyBody = '';

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        abort_unless($content->status === 'published' && $content->active_revision_id !== null, 404);
        Gate::authorize('view', $content);

        $revision = $content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        $this->group = $group;
        $this->space = $space;
        $this->content = $content;
        $this->revisionUuid = $revision->uuid;
    }

    public function toggleReaction(string $type, ToggleSpaceContentReaction $toggle): void
    {
        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        try {
            $toggle->execute($this->content, $revision, $this->user(), $type);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            $this->addError('interaction', __('interactions.edition_changed'));
        }
    }

    public function addComment(AddSpaceContentAnnotation $add): void
    {
        $this->validate([
            'commentBody' => ['required', 'string', 'max:5000'],
        ]);

        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        try {
            $add->execute($this->content, $revision, $this->user(), $this->commentBody);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $this->reset('commentBody');
        $this->resetErrorBag('interaction');
    }

    public function startReply(string $annotationUuid): void
    {
        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $exists = $revision->annotations()
            ->where('uuid', $annotationUuid)
            ->whereNull('parent_annotation_id')
            ->where('kind', SpaceContentAnnotation::KIND_COMMENT)
            ->where('status', SpaceContentAnnotation::STATUS_ACTIVE)
            ->exists();
        abort_unless($exists, 404);

        $this->replyingTo = $annotationUuid;
        $this->replyBody = '';
    }

    public function cancelReply(): void
    {
        $this->reset('replyingTo', 'replyBody');
        $this->resetErrorBag('replyBody');
    }

    public function addReply(AddSpaceContentAnnotation $add): void
    {
        $this->validate([
            'replyingTo' => ['required', 'uuid'],
            'replyBody' => ['required', 'string', 'max:5000'],
        ]);

        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $parent = $revision->annotations()
            ->where('uuid', $this->replyingTo)
            ->whereNull('parent_annotation_id')
            ->where('kind', SpaceContentAnnotation::KIND_COMMENT)
            ->where('status', SpaceContentAnnotation::STATUS_ACTIVE)
            ->first();
        abort_unless($parent instanceof SpaceContentAnnotation, 404);

        try {
            $add->execute($this->content, $revision, $this->user(), $this->replyBody, $parent);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $this->reset('replyingTo', 'replyBody');
        $this->resetErrorBag('interaction');
    }

    public function render(SpaceContentPublishedOutline $outlineBuilder): View
    {
        $user = $this->user();
        $current = SpaceContent::query()
            ->with(['author.user', 'definition', 'activeRevision'])
            ->findOrFail($this->content->id);
        abort_unless((int) $current->group_space_id === (int) $this->space->id, 404);
        abort_unless($current->status === 'published', 404);
        Gate::forUser($user)->authorize('view', $current);
        $this->content = $current;

        $revision = $current->activeRevision;
        abort_unless($revision instanceof SpaceContentRevision, 404);
        $revision->loadMissing('assets');

        if ($this->revisionUuid !== $revision->uuid) {
            $this->revisionUuid = $revision->uuid;
            $this->reset('commentBody', 'replyingTo', 'replyBody');
        }

        $definitionVersion = $revision->definitionVersion()->firstOrFail();
        $outline = $outlineBuilder->forRevision($revision, $user);
        $canEnterStudio = Gate::forUser($user)->allows('update', $current)
            || Gate::forUser($user)->allows('revisions', $current);
        $legacyEvidence = ! $revision->hasVerifiableManifest();
        $canInteract = Gate::forUser($user)->allows('interact', $current);

        $actor = $this->actor();
        $reactionCounts = $revision->reactions()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $viewerReactions = $revision->reactions()
            ->where('actor_id', $actor->id)
            ->pluck('type')
            ->filter(static fn (mixed $type): bool => is_string($type))
            ->values()
            ->all();

        $annotations = $revision->annotations()
            ->whereNull('parent_annotation_id')
            ->where('kind', SpaceContentAnnotation::KIND_COMMENT)
            ->where('status', SpaceContentAnnotation::STATUS_ACTIVE)
            ->with(['author.user', 'replies.author.user'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $reactionTypes = SpaceContentReaction::TYPES;

        return view('livewire.groups.space-content-reader', compact(
            'revision',
            'definitionVersion',
            'outline',
            'canEnterStudio',
            'legacyEvidence',
            'canInteract',
            'reactionCounts',
            'viewerReactions',
            'reactionTypes',
            'annotations',
        ));
    }

    private function interactionRevision(): ?SpaceContentRevision
    {
        $current = SpaceContent::query()->with('space')->find($this->content->id);
        if (! $current instanceof SpaceContent || $current->active_revision_id === null) {
            return null;
        }

        $revision = $current->revisions()->where('uuid', $this->revisionUuid)->first();
        if (
            ! $revision instanceof SpaceContentRevision
            || (int) $current->active_revision_id !== (int) $revision->id
        ) {
            return null;
        }

        $this->content = $current;

        return $revision;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function actor(): Actor
    {
        $user = User::query()->with('actor')->find($this->user()->id);
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user->actor;
    }
}
