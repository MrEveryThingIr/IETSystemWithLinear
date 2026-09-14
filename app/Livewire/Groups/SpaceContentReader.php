<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\ToggleSpaceContentReaction;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentReaction;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentPublishedOutline;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
#[Title('Content')]
class SpaceContentReader extends Component
{
    use WithFileUploads;

    public Group $group;

    public GroupSpace $space;

    public SpaceContent $content;

    public string $revisionUuid = '';

    public string $commentBody = '';

    public ?string $replyingTo = null;

    public string $replyBody = '';

    public string $replyKind = SpaceContentAnnotation::KIND_REPLY;

    public bool $annotationComposerOpen = false;

    public string $annotationKind = SpaceContentAnnotation::KIND_NOTE;

    public string $annotationVisibility = SpaceContentAnnotation::VISIBILITY_SPACE;

    public string $annotationBody = '';

    /** @var list<array<string, mixed>> */
    public array $annotationAnchors = [];

    public mixed $annotationUpload = null;

    public string $annotationRightsStatus = 'unknown';

    public string $annotationCaption = '';

    public bool $annotationUploadIsRecording = false;

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

    public function openRevisionAnnotation(): void
    {
        $revision = $this->interactionRevision();
        abort_unless($revision instanceof SpaceContentRevision, 409, __('interactions.edition_changed'));

        $this->annotationComposerOpen = true;
        $this->annotationAnchors = [[
            'target_type' => SpaceContentAnnotationAnchor::TARGET_REVISION,
            'target_uuid' => $revision->uuid,
            'field_key' => null,
            'selector' => ['label' => __('interactions.anchor.entire_edition')],
        ]];
    }

    public function addFieldAnchor(string $fieldKey): void
    {
        $this->appendAnchor([
            'target_type' => SpaceContentAnnotationAnchor::TARGET_FIELD,
            'target_uuid' => null,
            'field_key' => $fieldKey,
            'selector' => [],
        ]);
    }

    public function addAssetAnchor(string $placementUuid): void
    {
        $this->appendAnchor([
            'target_type' => SpaceContentAnnotationAnchor::TARGET_ASSET,
            'target_uuid' => $placementUuid,
            'field_key' => null,
            'selector' => [],
        ]);
    }

    public function addRelationshipAnchor(string $relationshipUuid, string $label = ''): void
    {
        $this->appendAnchor([
            'target_type' => SpaceContentAnnotationAnchor::TARGET_RELATIONSHIP,
            'target_uuid' => $relationshipUuid,
            'field_key' => null,
            'selector' => ['label' => mb_substr(trim($label), 0, 255)],
        ]);
    }

    public function startTextAnnotation(string $fieldKey, string $exact, string $prefix = '', string $suffix = ''): void
    {
        $exact = trim($exact);
        if ($exact === '') {
            return;
        }

        $this->annotationComposerOpen = true;
        $this->annotationAnchors = [[
            'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
            'target_uuid' => null,
            'field_key' => $fieldKey,
            'selector' => [
                'exact' => mb_substr($exact, 0, 2000),
                'prefix' => mb_substr($prefix, -120),
                'suffix' => mb_substr($suffix, 0, 120),
            ],
        ]];
    }

    public function removeAnnotationAnchor(int $index): void
    {
        if (! array_key_exists($index, $this->annotationAnchors)) {
            return;
        }

        unset($this->annotationAnchors[$index]);
        $this->annotationAnchors = array_values($this->annotationAnchors);
    }

    public function clearAnnotationComposer(): void
    {
        $this->annotationComposerOpen = false;
        $this->reset(
            'annotationBody',
            'annotationAnchors',
            'annotationUpload',
            'annotationCaption',
            'annotationUploadIsRecording',
        );
        $this->annotationKind = SpaceContentAnnotation::KIND_NOTE;
        $this->annotationVisibility = SpaceContentAnnotation::VISIBILITY_SPACE;
        $this->annotationRightsStatus = 'unknown';
        $this->resetErrorBag();
    }

    public function updatedAnnotationUpload(): void
    {
        $this->annotationUploadIsRecording = false;
        $this->annotationComposerOpen = true;
    }

    public function markAnnotationRecordingReady(): void
    {
        abort_unless($this->annotationUpload instanceof UploadedFile, 422);
        $this->annotationUploadIsRecording = true;
        $this->annotationRightsStatus = 'owned';
        $this->annotationComposerOpen = true;
    }

    public function clearAnnotationUpload(): void
    {
        $this->reset('annotationUpload', 'annotationCaption', 'annotationUploadIsRecording');
        $this->annotationRightsStatus = 'unknown';
        $this->resetErrorBag('annotationUpload');
    }

    public function postAnnotation(AddSpaceContentAnnotation $add): void
    {
        $this->validate([
            'annotationKind' => ['required', Rule::in(SpaceContentAnnotation::TOP_LEVEL_KINDS)],
            'annotationVisibility' => ['required', Rule::in(SpaceContentAnnotation::VISIBILITIES)],
            'annotationBody' => ['nullable', 'string', 'max:5000'],
            'annotationUpload' => ['nullable', 'file', 'max:12288'],
            'annotationRightsStatus' => ['required', Rule::in(Asset::RIGHTS_STATUSES)],
            'annotationCaption' => ['nullable', 'string', 'max:1000'],
        ]);

        $upload = $this->annotationUpload instanceof UploadedFile ? $this->annotationUpload : null;
        if (trim($this->annotationBody) === '' && ! $upload instanceof UploadedFile) {
            $this->addError('annotationBody', __('interactions.annotation_needs_content'));

            return;
        }

        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        try {
            $add->execute(
                $this->content,
                $revision,
                $this->user(),
                $this->annotationBody,
                null,
                $this->annotationKind,
                $this->annotationVisibility,
                $this->annotationAnchors,
                $upload,
                $this->annotationUploadIsRecording ? 'owned' : $this->annotationRightsStatus,
                $this->annotationCaption,
            );
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 409) {
                $this->addError('interaction', __('interactions.edition_changed'));

                return;
            }

            if ($exception->getStatusCode() === 422) {
                $this->addError('annotationBody', $exception->getMessage());

                return;
            }

            throw $exception;
        }

        $this->clearAnnotationComposer();
        session()->flash('interaction-status', __('interactions.annotation_posted'));
    }

    public function startReply(string $annotationUuid): void
    {
        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $actor = $this->actor();
        $parent = $revision->annotations()
            ->where('uuid', $annotationUuid)
            ->whereNull('parent_annotation_id')
            ->where('status', SpaceContentAnnotation::STATUS_ACTIVE)
            ->where(function ($query) use ($actor): void {
                $query->where('visibility', SpaceContentAnnotation::VISIBILITY_SPACE)
                    ->orWhere(function ($query) use ($actor): void {
                        $query->where('visibility', SpaceContentAnnotation::VISIBILITY_PRIVATE)
                            ->where('author_actor_id', $actor->id);
                    });
            })
            ->first();
        abort_unless($parent instanceof SpaceContentAnnotation, 404);

        $this->replyingTo = $annotationUuid;
        $this->replyBody = '';
        $this->replyKind = $parent->kind === SpaceContentAnnotation::KIND_QUESTION
            ? SpaceContentAnnotation::KIND_ANSWER
            : SpaceContentAnnotation::KIND_REPLY;
    }

    public function cancelReply(): void
    {
        $this->reset('replyingTo', 'replyBody');
        $this->replyKind = SpaceContentAnnotation::KIND_REPLY;
        $this->resetErrorBag('replyBody');
    }

    public function addReply(AddSpaceContentAnnotation $add): void
    {
        $this->validate([
            'replyingTo' => ['required', 'uuid'],
            'replyBody' => ['required', 'string', 'max:5000'],
            'replyKind' => ['required', Rule::in(SpaceContentAnnotation::CHILD_KINDS)],
        ]);

        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $actor = $this->actor();
        $parent = $revision->annotations()
            ->where('uuid', $this->replyingTo)
            ->whereNull('parent_annotation_id')
            ->where('status', SpaceContentAnnotation::STATUS_ACTIVE)
            ->where(function ($query) use ($actor): void {
                $query->where('visibility', SpaceContentAnnotation::VISIBILITY_SPACE)
                    ->orWhere(function ($query) use ($actor): void {
                        $query->where('visibility', SpaceContentAnnotation::VISIBILITY_PRIVATE)
                            ->where('author_actor_id', $actor->id);
                    });
            })
            ->first();
        abort_unless($parent instanceof SpaceContentAnnotation, 404);

        try {
            $add->execute(
                $this->content,
                $revision,
                $this->user(),
                $this->replyBody,
                $parent,
                $this->replyKind,
                $parent->visibility,
            );
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $this->cancelReply();
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
            $this->clearAnnotationComposer();
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
            ->where('status', SpaceContentAnnotation::STATUS_ACTIVE)
            ->where(function ($query) use ($actor): void {
                $query->where('visibility', SpaceContentAnnotation::VISIBILITY_SPACE)
                    ->orWhere(function ($query) use ($actor): void {
                        $query->where('visibility', SpaceContentAnnotation::VISIBILITY_PRIVATE)
                            ->where('author_actor_id', $actor->id);
                    });
            })
            ->with([
                'author.user',
                'anchors',
                'assets',
                'replies.author.user',
                'replies.anchors',
                'replies.assets',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $fieldAnnotationCounts = [];
        $assetAnnotationCounts = [];
        foreach ($annotations as $annotation) {
            foreach ($annotation->anchors as $anchor) {
                if (in_array($anchor->target_type, [SpaceContentAnnotationAnchor::TARGET_FIELD, SpaceContentAnnotationAnchor::TARGET_TEXT], true)
                    && is_string($anchor->field_key)) {
                    $fieldAnnotationCounts[$anchor->field_key] = ($fieldAnnotationCounts[$anchor->field_key] ?? 0) + 1;
                }
                if ($anchor->target_type === SpaceContentAnnotationAnchor::TARGET_ASSET && is_string($anchor->target_uuid)) {
                    $assetAnnotationCounts[$anchor->target_uuid] = ($assetAnnotationCounts[$anchor->target_uuid] ?? 0) + 1;
                }
            }
        }

        $reactionTypes = SpaceContentReaction::TYPES;
        $annotationKinds = SpaceContentAnnotation::TOP_LEVEL_KINDS;
        $annotationVisibilities = SpaceContentAnnotation::VISIBILITIES;
        $rightsStatuses = Asset::RIGHTS_STATUSES;

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
            'annotationKinds',
            'annotationVisibilities',
            'rightsStatuses',
            'fieldAnnotationCounts',
            'assetAnnotationCounts',
        ));
    }

    /** @param array<string, mixed> $anchor */
    private function appendAnchor(array $anchor): void
    {
        $this->annotationComposerOpen = true;
        $key = implode('|', [
            (string) ($anchor['target_type'] ?? ''),
            (string) ($anchor['target_uuid'] ?? ''),
            (string) ($anchor['field_key'] ?? ''),
        ]);

        foreach ($this->annotationAnchors as $existing) {
            $existingKey = implode('|', [
                (string) ($existing['target_type'] ?? ''),
                (string) ($existing['target_uuid'] ?? ''),
                (string) ($existing['field_key'] ?? ''),
            ]);
            if ($existingKey === $key) {
                return;
            }
        }

        if (count($this->annotationAnchors) < 20) {
            $this->annotationAnchors[] = $anchor;
        }
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
