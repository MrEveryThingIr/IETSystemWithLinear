<?php

namespace App\Livewire\Contexts;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\ToggleSpaceContentReaction;
use App\ContentEvidenceTarget;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentReaction;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\ContentInteractionSettings;
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
class ContentShow extends Component
{
    use WithFileUploads;

    /** @var list<string> */
    private const COMPOSER_PURPOSES = [
        'remember',
        'note',
        'question',
        'translate',
        'comment',
        'file',
        'voice',
        'advanced',
        'correction',
        'idea',
    ];

    /** @var list<string> */
    private const COMPOSER_MEDIA = ['text', 'file', 'voice', 'advanced'];

    /** @var list<string> */
    private const MARKER_FILTERS = ['all', 'mine', 'questions', 'hidden'];

    public Context $context;

    public SpaceContent $content;

    public string $revisionUuid = '';

    public ?string $evidenceReferenceUuid = null;

    public ?string $viewingEvidenceReferenceUuid = null;

    public ?string $viewingRevisionUuid = null;

    public string $commentBody = '';

    public ?string $replyingTo = null;

    public string $replyBody = '';

    public string $replyKind = SpaceContentAnnotation::KIND_REPLY;

    public bool $annotationComposerOpen = false;

    public ?string $annotationParentUuid = null;

    public string $annotationKind = SpaceContentAnnotation::KIND_NOTE;

    public string $annotationVisibility = SpaceContentAnnotation::VISIBILITY_PRIVATE;

    public string $annotationBody = '';

    public string $annotationComposerMode = 'note';

    public string $annotationMedium = 'text';

    public int $annotationComposerX = 0;

    public int $annotationComposerY = 0;

    /** @var list<array<string, mixed>> */
    public array $annotationAnchors = [];

    /** @var list<array<string, mixed>> */
    public array $selectedTargets = [];

    public mixed $annotationUpload = null;

    public string $annotationRightsStatus = 'unknown';

    public string $annotationCaption = '';

    public bool $annotationUploadIsRecording = false;

    public string $markerFilter = 'all';

    /** @var list<string> */
    public array $previewAnnotationUuids = [];

    public function mount(Context $context, SpaceContent $content): void
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);
        $user = $this->user();
        Gate::forUser($user)->authorize('view', $content);

        $revision = $this->evidenceRevision($context, $content);

        if (! $revision instanceof SpaceContentRevision) {
            $revision = $content->activeRevisionRecord();
            if (! $revision instanceof SpaceContentRevision
                && Gate::forUser($user)->allows('revisions', $content)) {
                $revision = $content->draftRevisionRecord();
            }
        }
        abort_unless($revision instanceof SpaceContentRevision, 404);

        $this->context = $context;
        $this->content = $content;
        $this->revisionUuid = $revision->uuid;
    }

    public function createRevisionEvidence(CreateContentEvidenceReference $create): void
    {
        $revision = $this->interactionRevision();
        abort_unless($revision instanceof SpaceContentRevision, 409, __('interactions.edition_changed'));

        $reference = $create->execute(
            $this->content,
            $revision,
            $this->user(),
            ContentEvidenceTarget::Revision,
        );

        $this->evidenceReferenceUuid = $reference->uuid;
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
        $this->validate(['commentBody' => ['required', 'string', 'max:5000']]);
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

        $this->openContextComposer('advanced', [
            'target_type' => SpaceContentAnnotationAnchor::TARGET_REVISION,
            'target_uuid' => $revision->uuid,
            'field_key' => null,
            'selector' => ['label' => __('interactions.anchor.entire_edition')],
        ]);
    }

    /** @param array<string, mixed> $anchor */
    public function openContextComposer(string $purpose, array $anchor, int $x = 0, int $y = 0): void
    {
        abort_unless(in_array($purpose, self::COMPOSER_PURPOSES, true), 422);
        abort_unless($this->interactionRevision() instanceof SpaceContentRevision, 409, __('interactions.edition_changed'));

        $anchor = $this->sanitizeClientAnchor($anchor);
        $targets = [
            ...$this->selectedTargets,
            $anchor,
        ];

        $this->resetComposerState(false);
        $this->annotationComposerOpen = true;
        $this->annotationComposerMode = $purpose;
        $this->annotationMedium = match ($purpose) {
            'file' => 'file',
            'voice' => 'voice',
            'advanced' => 'advanced',
            default => 'text',
        };
        $this->annotationKind = match ($purpose) {
            'question' => SpaceContentAnnotation::KIND_QUESTION,
            'comment' => SpaceContentAnnotation::KIND_COMMENT,
            'correction' => SpaceContentAnnotation::KIND_CORRECTION,
            'idea' => SpaceContentAnnotation::KIND_IDEA,
            default => SpaceContentAnnotation::KIND_NOTE,
        };
        $this->annotationVisibility = $this->defaultAnnotationVisibility();
        $this->annotationComposerX = max(12, min(3000, $x));
        $this->annotationComposerY = max(12, min(3000, $y));
        $this->annotationAnchors = $this->mergeAnchors(array_map(
            fn (array $target): array => $this->withIntent($target, $purpose),
            $targets,
        ));
        $this->selectedTargets = [];
    }

    public function openSelectionComposer(string $purpose, int $x = 0, int $y = 0): void
    {
        abort_unless(in_array($purpose, self::COMPOSER_PURPOSES, true), 422);
        abort_if($this->selectedTargets === [], 422, __('interactions.selection_empty'));

        $targets = $this->selectedTargets;
        $anchor = array_pop($targets);
        $this->selectedTargets = $targets;
        $this->openContextComposer($purpose, $anchor, $x, $y);
    }

    /** @param array<string, mixed> $anchor */
    public function addTargetToSelection(array $anchor): void
    {
        abort_unless($this->interactionRevision() instanceof SpaceContentRevision, 409, __('interactions.edition_changed'));
        $this->selectedTargets = $this->mergeAnchors([
            ...$this->selectedTargets,
            $this->sanitizeClientAnchor($anchor),
        ]);
        $this->annotationComposerOpen = false;
    }

    public function removeSelectedTarget(int $index): void
    {
        if (! array_key_exists($index, $this->selectedTargets)) {
            return;
        }

        unset($this->selectedTargets[$index]);
        $this->selectedTargets = array_values($this->selectedTargets);
    }

    public function clearTargetSelection(): void
    {
        $this->selectedTargets = [];
    }

    public function setComposerMedium(string $medium): void
    {
        abort_unless(in_array($medium, self::COMPOSER_MEDIA, true), 422);
        $this->annotationMedium = $medium;
        if ($medium === 'voice') {
            $this->annotationRightsStatus = 'owned';
        }
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

    public function addBlockAnchor(string $blockUuid): void
    {
        $this->appendAnchor([
            'target_type' => SpaceContentAnnotationAnchor::TARGET_BLOCK,
            'target_uuid' => $blockUuid,
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

    public function startTextAnnotation(
        string $fieldKey,
        string $exact,
        string $prefix = '',
        string $suffix = '',
        ?int $start = null,
        ?int $end = null,
    ): void {
        if (trim($exact) === '') {
            return;
        }

        $selector = [
            'exact' => mb_substr($exact, 0, 2000),
            'prefix' => mb_substr($prefix, -120),
            'suffix' => mb_substr($suffix, 0, 120),
        ];
        if ($start !== null && $end !== null) {
            $selector['start'] = $start;
            $selector['end'] = $end;
        }

        $this->openContextComposer('note', [
            'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
            'target_uuid' => null,
            'field_key' => $fieldKey,
            'selector' => $selector,
        ]);
    }

    public function removeAnnotationAnchor(int $index): void
    {
        if ($this->annotationParentUuid !== null || ! array_key_exists($index, $this->annotationAnchors)) {
            return;
        }

        unset($this->annotationAnchors[$index]);
        $this->annotationAnchors = array_values($this->annotationAnchors);
    }

    public function clearAnnotationComposer(): void
    {
        $this->resetComposerState(false);
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
        $this->annotationMedium = 'voice';
        $this->annotationComposerOpen = true;
    }

    public function clearAnnotationUpload(): void
    {
        $this->reset('annotationUpload', 'annotationCaption', 'annotationUploadIsRecording');
        $this->annotationRightsStatus = 'unknown';
        $this->resetErrorBag('annotationUpload');
    }

    public function openReplyComposer(string $annotationUuid, int $x = 0, int $y = 0): void
    {
        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $parent = $this->visibleTopLevelAnnotation($revision, $annotationUuid);
        abort_unless($parent instanceof SpaceContentAnnotation, 404);
        $parent->loadMissing('anchors');

        $this->resetComposerState(true);
        $this->annotationComposerOpen = true;
        $this->annotationParentUuid = $parent->uuid;
        $this->annotationKind = $parent->kind === SpaceContentAnnotation::KIND_QUESTION
            ? SpaceContentAnnotation::KIND_ANSWER
            : SpaceContentAnnotation::KIND_REPLY;
        $this->annotationComposerMode = $this->annotationKind;
        $this->annotationMedium = 'text';
        $this->annotationVisibility = $parent->visibility;
        $this->annotationComposerX = max(12, min(3000, $x));
        $this->annotationComposerY = max(12, min(3000, $y));
        $this->annotationAnchors = $parent->anchors->map(static fn ($anchor): array => [
            'target_type' => $anchor->target_type,
            'target_uuid' => $anchor->target_uuid,
            'field_key' => $anchor->field_key,
            'selector' => $anchor->selector,
        ])->all();
    }

    public function postAnnotation(AddSpaceContentAnnotation $add): void
    {
        $allowedKinds = $this->annotationParentUuid !== null
            ? SpaceContentAnnotation::CHILD_KINDS
            : SpaceContentAnnotation::TOP_LEVEL_KINDS;

        $this->validate([
            'annotationKind' => ['required', Rule::in($allowedKinds)],
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

        $parent = $this->annotationParentUuid !== null
            ? $this->visibleTopLevelAnnotation($revision, $this->annotationParentUuid)
            : null;
        if ($this->annotationParentUuid !== null && ! $parent instanceof SpaceContentAnnotation) {
            abort(404);
        }

        try {
            $add->execute(
                $this->content,
                $revision,
                $this->user(),
                $this->annotationBody,
                $parent,
                $this->annotationKind,
                $parent instanceof SpaceContentAnnotation ? $parent->visibility : $this->annotationVisibility,
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

        $this->resetComposerState(true);
        session()->flash('interaction-status', __('interactions.annotation_posted'));
    }

    public function startReply(string $annotationUuid): void
    {
        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            $this->addError('interaction', __('interactions.edition_changed'));

            return;
        }

        $parent = $this->visibleTopLevelAnnotation($revision, $annotationUuid);
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

        $parent = $this->visibleTopLevelAnnotation($revision, (string) $this->replyingTo);
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

    public function setMarkerFilter(string $filter): void
    {
        abort_unless(in_array($filter, self::MARKER_FILTERS, true), 422);
        $this->markerFilter = $filter;
        $this->previewAnnotationUuids = [];
    }

    /** @param list<mixed> $uuids */
    public function openMarkerPreview(array $uuids): void
    {
        $revision = $this->interactionRevision();
        if (! $revision instanceof SpaceContentRevision) {
            return;
        }

        $visible = [];
        foreach (array_slice($uuids, 0, 20) as $uuid) {
            if (! is_string($uuid)) {
                continue;
            }
            $annotation = $this->visibleTopLevelAnnotation($revision, $uuid);
            if ($annotation instanceof SpaceContentAnnotation) {
                $visible[] = $annotation->uuid;
            }
        }

        $this->previewAnnotationUuids = array_values(array_unique($visible));
    }

    public function closeMarkerPreview(): void
    {
        $this->previewAnnotationUuids = [];
    }

    public function render(SpaceContentPublishedOutline $outlineBuilder, ContentInteractionSettings $interactionSettings): View
    {
        $user = $this->user();
        $current = SpaceContent::query()
            ->with(['author.user', 'definition', 'activeRevision'])
            ->findOrFail($this->content->id);
        abort_unless((int) $current->context_id === (int) $this->context->id, 404);
        Gate::forUser($user)->authorize('view', $current);
        $this->content = $current;

        $revision = $this->evidenceRevision($this->context, $current);

        if (! $revision instanceof SpaceContentRevision) {
            $revision = $current->activeRevision;
            if (! $revision instanceof SpaceContentRevision
                && Gate::forUser($user)->allows('revisions', $current)) {
                $revision = $current->draftRevisionRecord();
            }
        }
        abort_unless($revision instanceof SpaceContentRevision, 404);
        $revision->loadMissing('assets');

        if ($this->revisionUuid !== $revision->uuid) {
            $this->revisionUuid = $revision->uuid;
            $this->reset('commentBody', 'replyingTo', 'replyBody');
            $this->resetComposerState(true);
            $this->previewAnnotationUuids = [];
        }

        $definitionVersion = $revision->definitionVersion()->firstOrFail();
        $outline = $outlineBuilder->forRevision($revision, $user);
        $canEnterStudio = Gate::forUser($user)->allows('update', $current)
            || Gate::forUser($user)->allows('revisions', $current);
        $legacyEvidence = ! $revision->hasVerifiableManifest();
        $canInteract = $this->viewingEvidenceReferenceUuid === null
            && $this->viewingRevisionUuid === null
            && $current->active_revision_id !== null
            && (int) $current->active_revision_id === (int) $revision->id
            && Gate::forUser($user)->allows('interact', $current);
        $canAnnotate = $canInteract && $interactionSettings->annotationsEnabled($current);
        $canReact = $canInteract && $interactionSettings->reactionsEnabled($current);

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

        $markerAnnotations = $annotations->filter(function (SpaceContentAnnotation $annotation) use ($actor): bool {
            return match ($this->markerFilter) {
                'hidden' => false,
                'mine' => (int) $annotation->author_actor_id === (int) $actor->id,
                'questions' => $annotation->kind === SpaceContentAnnotation::KIND_QUESTION,
                default => true,
            };
        });

        [$rangeMarkers, $fieldMarkerUuids, $assetMarkerUuids, $blockMarkerUuids] = $this->markerState($markerAnnotations->all());
        $previewAnnotations = $annotations
            ->whereIn('uuid', $this->previewAnnotationUuids)
            ->values();

        $reactionTypes = SpaceContentReaction::TYPES;
        $annotationKinds = $this->annotationParentUuid !== null
            ? SpaceContentAnnotation::CHILD_KINDS
            : SpaceContentAnnotation::TOP_LEVEL_KINDS;
        $annotationVisibilities = SpaceContentAnnotation::VISIBILITIES;
        $rightsStatuses = Asset::RIGHTS_STATUSES;

        $viewerTimezone = $user->timezone ?: 'UTC';

        return view('livewire.contexts.content-show', compact(
            'revision',
            'definitionVersion',
            'outline',
            'canEnterStudio',
            'legacyEvidence',
            'canInteract',
            'canAnnotate',
            'canReact',
            'reactionCounts',
            'viewerReactions',
            'reactionTypes',
            'annotations',
            'annotationKinds',
            'annotationVisibilities',
            'rightsStatuses',
            'rangeMarkers',
            'fieldMarkerUuids',
            'assetMarkerUuids',
            'viewerTimezone',
            'blockMarkerUuids',
            'previewAnnotations',
        ));
    }

    /** @param array<string, mixed> $anchor */
    private function appendAnchor(array $anchor): void
    {
        if ($this->annotationParentUuid !== null) {
            return;
        }

        $this->annotationComposerOpen = true;
        $this->annotationComposerMode = 'advanced';
        $this->annotationMedium = 'advanced';
        $this->annotationVisibility = $this->defaultAnnotationVisibility();
        $this->annotationAnchors = $this->mergeAnchors([
            ...$this->annotationAnchors,
            $this->sanitizeClientAnchor($anchor),
        ]);
    }

    /**
     * @param  list<SpaceContentAnnotation>  $annotations
     * @return array{list<array<string, mixed>>, array<string, list<string>>, array<string, list<string>>, array<string, list<string>>}
     */
    private function markerState(array $annotations): array
    {
        $ranges = [];
        $fields = [];
        $assets = [];
        $blocks = [];

        foreach ($annotations as $annotation) {
            foreach ($annotation->anchors as $anchor) {
                $selector = is_array($anchor->selector) ? $anchor->selector : [];
                $uuid = $annotation->uuid;

                if (in_array($anchor->target_type, [SpaceContentAnnotationAnchor::TARGET_FIELD, SpaceContentAnnotationAnchor::TARGET_TEXT], true)
                    && is_string($anchor->field_key)) {
                    $fields[$anchor->field_key] ??= [];
                    $fields[$anchor->field_key][] = $uuid;
                }
                if ($anchor->target_type === SpaceContentAnnotationAnchor::TARGET_ASSET && is_string($anchor->target_uuid)) {
                    $assets[$anchor->target_uuid] ??= [];
                    $assets[$anchor->target_uuid][] = $uuid;
                }
                if ($anchor->target_type === SpaceContentAnnotationAnchor::TARGET_BLOCK && is_string($anchor->target_uuid)) {
                    $blocks[$anchor->target_uuid] ??= [];
                    $blocks[$anchor->target_uuid][] = $uuid;
                }

                $start = $this->selectorInteger($selector['start'] ?? null);
                $end = $this->selectorInteger($selector['end'] ?? null);
                if ($start === null || $end === null || $end <= $start) {
                    continue;
                }
                if ($anchor->target_type !== SpaceContentAnnotationAnchor::TARGET_TEXT
                    && $anchor->target_type !== SpaceContentAnnotationAnchor::TARGET_BLOCK) {
                    continue;
                }

                $key = implode('|', [
                    $anchor->target_type,
                    $anchor->target_uuid ?? '',
                    $anchor->field_key ?? '',
                    (string) $start,
                    (string) $end,
                ]);
                if (! isset($ranges[$key])) {
                    $ranges[$key] = [
                        'target_type' => $anchor->target_type,
                        'target_uuid' => $anchor->target_uuid,
                        'field_key' => $anchor->field_key,
                        'start' => $start,
                        'end' => $end,
                        'annotation_uuids' => [],
                        'private' => true,
                        'question' => false,
                    ];
                }
                $ranges[$key]['annotation_uuids'][] = $uuid;
                $ranges[$key]['private'] = $ranges[$key]['private']
                    && $annotation->visibility === SpaceContentAnnotation::VISIBILITY_PRIVATE;
                $ranges[$key]['question'] = $ranges[$key]['question']
                    || $annotation->kind === SpaceContentAnnotation::KIND_QUESTION;
            }
        }

        foreach ($fields as $key => $uuids) {
            $fields[$key] = array_values(array_unique($uuids));
        }
        foreach ($assets as $key => $uuids) {
            $assets[$key] = array_values(array_unique($uuids));
        }
        foreach ($blocks as $key => $uuids) {
            $blocks[$key] = array_values(array_unique($uuids));
        }

        foreach ($ranges as &$range) {
            $range['annotation_uuids'] = array_values(array_unique($range['annotation_uuids']));
        }
        unset($range);

        return [array_values($ranges), $fields, $assets, $blocks];
    }

    private function visibleTopLevelAnnotation(SpaceContentRevision $revision, string $uuid): ?SpaceContentAnnotation
    {
        $actor = $this->actor();

        return $revision->annotations()
            ->where('uuid', $uuid)
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
    }

    private function evidenceRevision(Context $context, SpaceContent $content): ?SpaceContentRevision
    {
        $evidenceUuid = $this->viewingEvidenceReferenceUuid;
        if ($evidenceUuid === null) {
            $queryValue = request()->query('evidence');
            $evidenceUuid = is_string($queryValue) && $queryValue !== '' ? $queryValue : null;
        }

        if ($evidenceUuid !== null) {
            $reference = ContentEvidenceReference::query()
                ->where('uuid', $evidenceUuid)
                ->where('context_id', $context->id)
                ->where('space_content_id', $content->id)
                ->firstOrFail();

            $revision = $reference->revision()->firstOrFail();
            abort_unless($revision->hasVerifiableManifest(), 404);

            $this->viewingEvidenceReferenceUuid = $reference->uuid;
            $this->viewingRevisionUuid = null;

            return $revision;
        }

        $revisionUuid = $this->viewingRevisionUuid;
        if ($revisionUuid === null) {
            $queryValue = request()->query('revision');
            $revisionUuid = is_string($queryValue) && $queryValue !== '' ? $queryValue : null;
        }

        if ($revisionUuid === null) {
            return null;
        }

        $revision = $content->revisions()
            ->where('uuid', $revisionUuid)
            ->firstOrFail();
        abort_unless($revision->hasVerifiableManifest(), 404);

        $this->viewingRevisionUuid = $revision->uuid;
        $this->viewingEvidenceReferenceUuid = null;

        return $revision;
    }

    private function interactionRevision(): ?SpaceContentRevision
    {
        $current = SpaceContent::query()->with('context')->find($this->content->id);
        if (! $current instanceof SpaceContent || $current->active_revision_id === null) {
            return null;
        }

        $revision = $current->revisions()->where('uuid', $this->revisionUuid)->first();
        if (! $revision instanceof SpaceContentRevision
            || (int) $current->active_revision_id !== (int) $revision->id) {
            return null;
        }

        $this->content = $current;

        return $revision;
    }

    private function resetComposerState(bool $clearSelection): void
    {
        $this->annotationComposerOpen = false;
        $this->annotationParentUuid = null;
        $this->reset(
            'annotationBody',
            'annotationAnchors',
            'annotationUpload',
            'annotationCaption',
            'annotationUploadIsRecording',
        );
        $this->annotationKind = SpaceContentAnnotation::KIND_NOTE;
        $this->annotationVisibility = $this->defaultAnnotationVisibility();
        $this->annotationRightsStatus = 'unknown';
        $this->annotationComposerMode = 'note';
        $this->annotationMedium = 'text';
        $this->annotationComposerX = 0;
        $this->annotationComposerY = 0;
        if ($clearSelection) {
            $this->selectedTargets = [];
        }
        $this->resetErrorBag();
    }

    /**
     * @param  list<mixed>  $anchors
     * @return list<array<string, mixed>>
     */
    private function mergeAnchors(array $anchors): array
    {
        $merged = [];
        $keys = [];
        foreach ($anchors as $anchor) {
            if (! is_array($anchor)) {
                continue;
            }
            $anchor = $this->sanitizeClientAnchor($anchor);
            $key = $this->anchorKey($anchor);
            if (isset($keys[$key])) {
                continue;
            }
            $keys[$key] = true;
            $merged[] = $anchor;
            if (count($merged) >= 20) {
                break;
            }
        }

        return $merged;
    }

    /** @param array<string, mixed> $anchor @return array<string, mixed> */
    private function sanitizeClientAnchor(array $anchor): array
    {
        $type = (string) ($anchor['target_type'] ?? '');
        abort_unless(in_array($type, SpaceContentAnnotationAnchor::TARGET_TYPES, true), 422);

        $targetUuid = is_string($anchor['target_uuid'] ?? null) ? trim((string) $anchor['target_uuid']) : null;
        $fieldKey = is_string($anchor['field_key'] ?? null) ? trim((string) $anchor['field_key']) : null;
        $selector = is_array($anchor['selector'] ?? null) ? $anchor['selector'] : [];
        if (isset($selector['exact']) && is_string($selector['exact'])) {
            $selector['exact'] = mb_substr($selector['exact'], 0, 2000);
        }
        if (isset($selector['prefix']) && is_string($selector['prefix'])) {
            $selector['prefix'] = mb_substr($selector['prefix'], -120);
        }
        if (isset($selector['suffix']) && is_string($selector['suffix'])) {
            $selector['suffix'] = mb_substr($selector['suffix'], 0, 120);
        }

        return [
            'target_type' => $type,
            'target_uuid' => $targetUuid === '' ? null : $targetUuid,
            'field_key' => $fieldKey === '' ? null : $fieldKey,
            'selector' => $selector,
        ];
    }

    /** @param array<string, mixed> $anchor @return array<string, mixed> */
    private function withIntent(array $anchor, string $intent): array
    {
        $selector = is_array($anchor['selector'] ?? null) ? $anchor['selector'] : [];
        $selector['intent'] = $intent;
        $anchor['selector'] = $selector;

        return $anchor;
    }

    /** @param array<string, mixed> $anchor */
    private function anchorKey(array $anchor): string
    {
        $selector = is_array($anchor['selector'] ?? null) ? $anchor['selector'] : [];

        return implode('|', [
            (string) ($anchor['target_type'] ?? ''),
            (string) ($anchor['target_uuid'] ?? ''),
            (string) ($anchor['field_key'] ?? ''),
            (string) ($selector['start'] ?? ''),
            (string) ($selector['end'] ?? ''),
            (string) ($selector['exact'] ?? ''),
        ]);
    }

    private function selectorInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function defaultAnnotationVisibility(): string
    {
        return app(ContentInteractionSettings::class)->defaultAnnotationVisibility($this->content);
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
