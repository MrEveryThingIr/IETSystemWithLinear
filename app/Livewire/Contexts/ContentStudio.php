<?php

namespace App\Livewire\Contexts;

use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\RemoveAssetFromSpaceContent;
use App\Actions\Groups\RestoreSpaceContent;
use App\Actions\Groups\RetryAssetMediaProcessing;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateAssetRightsStatus;
use App\Actions\Groups\UpdateSpaceContentBlocks;
use App\Models\Asset;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentBlock;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\ContextPresenter;
use App\Support\MediaLimits;
use App\Support\SpaceContentPublicationEvidence;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * One authoring workspace: title, the document canvas (fields and blocks),
 * media, and secondary tools (AI, appearance, outline, history) as panels.
 *
 * Every save still goes through the existing revision actions, so revisions
 * stay immutable and publication rules stay authoritative. The canvas keeps
 * unsaved state locally and only writes what changed.
 */
#[Layout('layouts.app')]
#[Title('Content Studio')]
class ContentStudio extends Component
{
    use WithFileUploads;

    /** Block types the canvas can insert directly. */
    public const TEXT_BLOCKS = ['paragraph', 'heading', 'quote', 'list', 'callout', 'divider'];

    public Context $context;

    public SpaceContent $content;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    public string $compositionMode = SpaceContentRevision::COMPOSITION_FIELDS;

    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    /** Fingerprint of the last saved state, used to detect unsaved changes. */
    public string $savedFingerprint = '';

    /** Another panel changed the draft while this canvas had unsaved edits. */
    public bool $draftChangedElsewhere = false;

    public mixed $assetUpload = null;

    public string $assetRightsStatus = 'owned';

    public string $assetCaption = '';

    /** Media kind requested from the insert bar ('' = add to the media list only). */
    public string $insertKind = '';

    public bool $mediaDialogOpen = false;

    public bool $publishPanelOpen = false;

    public string $archiveReason = '';

    public string $restoreReason = '';

    public bool $showArchiveConfirmation = false;

    public function mount(Context $context, SpaceContent $content): void
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);

        $user = $this->user();
        Gate::forUser($user)->authorize('view', $content);
        abort_unless(
            Gate::forUser($user)->allows('update', $content)
                || Gate::forUser($user)->allows('revisions', $content)
                || Gate::forUser($user)->allows('restore', $content),
            403,
        );

        $this->context = $context;
        $this->content = $content;
        $this->reloadFromDraft();
    }

    public function isDirty(): bool
    {
        return $this->savedFingerprint !== '' && $this->savedFingerprint !== $this->fingerprint();
    }

    public function saveRevision(ReviseSpaceContent $revise, UpdateSpaceContentBlocks $updateBlocks): void
    {
        $this->persistDraft($revise, $updateBlocks);
        session()->flash('status', __('ui.content.revised'));
    }

    /** Another panel (AI, appearance, outline) wrote a new revision. */
    #[On('content-revised')]
    public function syncAfterExternalRevision(): void
    {
        if ($this->isDirty()) {
            $this->draftChangedElsewhere = true;

            return;
        }

        $this->reloadFromDraft();
    }

    public function discardAndReload(): void
    {
        $this->reloadFromDraft();
        $this->resetErrorBag();
    }

    // ---- Blocks -------------------------------------------------------

    public function addBlock(string $type): void
    {
        abort_unless(in_array($type, self::TEXT_BLOCKS, true), 422);
        $this->ensureBlockDocument();

        $this->blocks[] = [
            'logical_uuid' => (string) Str::uuid(),
            'type' => $type,
            'data' => match ($type) {
                SpaceContentBlock::TYPE_PARAGRAPH => ['text' => ''],
                SpaceContentBlock::TYPE_HEADING => ['text' => '', 'level' => 2],
                SpaceContentBlock::TYPE_QUOTE => ['text' => '', 'attribution' => ''],
                SpaceContentBlock::TYPE_LIST => ['items_text' => '', 'ordered' => false],
                SpaceContentBlock::TYPE_CALLOUT => ['text' => '', 'tone' => 'info'],
                default => [],
            },
            'style' => [],
        ];
    }

    public function addFieldBlock(string $fieldKey): void
    {
        $this->ensureBlockDocument();
        $this->blocks[] = [
            'logical_uuid' => (string) Str::uuid(),
            'type' => SpaceContentBlock::TYPE_FIELD,
            'data' => ['field_key' => $fieldKey],
            'style' => [],
        ];
    }

    public function addExistingMediaBlock(string $assetUuid): void
    {
        $revision = $this->editableRevision();
        $asset = $revision->assets()->where('assets.uuid', $assetUuid)->first();
        abort_unless($asset instanceof Asset, 404);
        $caption = (string) DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $revision->id)
            ->where('asset_id', $asset->id)
            ->value('caption');
        $this->ensureBlockDocument();
        $this->blocks[] = $this->mediaBlock($asset, $caption);
    }

    public function removeBlock(int $index): void
    {
        if (! array_key_exists($index, $this->blocks)) {
            return;
        }

        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->blocks[$index], $this->blocks[$index - 1])) {
            return;
        }

        [$this->blocks[$index - 1], $this->blocks[$index]] = [$this->blocks[$index], $this->blocks[$index - 1]];
    }

    public function moveDown(int $index): void
    {
        if (! isset($this->blocks[$index], $this->blocks[$index + 1])) {
            return;
        }

        [$this->blocks[$index + 1], $this->blocks[$index]] = [$this->blocks[$index], $this->blocks[$index + 1]];
    }

    /** Return to the plain structured-fields document (keeps media, drops layout blocks). */
    public function useSimpleDocument(): void
    {
        $this->compositionMode = SpaceContentRevision::COMPOSITION_FIELDS;
        $this->blocks = [];
    }

    // ---- Media --------------------------------------------------------

    public function openMediaDialog(string $kind = ''): void
    {
        abort_unless(in_array($kind, ['', 'image', 'video', 'audio', 'file'], true), 422);
        $this->insertKind = $kind;
        $this->mediaDialogOpen = true;
        $this->reset('assetUpload', 'assetCaption');
        $this->resetErrorBag(['assetUpload', 'assetCaption', 'assetRightsStatus']);
    }

    public function closeMediaDialog(): void
    {
        $this->mediaDialogOpen = false;
        $this->reset('assetUpload', 'assetCaption', 'insertKind');
        $this->resetErrorBag(['assetUpload', 'assetCaption', 'assetRightsStatus']);
    }

    public function attachAsset(
        AttachAssetToSpaceContent $attach,
        ReviseSpaceContent $revise,
        UpdateSpaceContentBlocks $updateBlocks,
    ): void {
        $this->validate([
            'assetUpload' => ['required', 'file'],
            'assetRightsStatus' => ['required', 'string', Rule::in(Asset::RIGHTS_STATUSES)],
            'assetCaption' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($this->assetUpload instanceof UploadedFile, 422);

        $mime = (string) $this->assetUpload->getMimeType();
        if (! Asset::supportsMime($mime)) {
            throw ValidationException::withMessages(['assetUpload' => __('studio_ui.upload.unsupported_type')]);
        }
        $this->validate(['assetUpload' => [MediaLimits::rule(MediaLimits::contentKindForMime($mime))]]);

        // Keep the author's unsaved writing: save it first, then attach.
        $this->persistDraft($revise, $updateBlocks);

        $this->content = $attach->execute(
            $this->content,
            $this->user(),
            $this->assetUpload,
            $this->assetRightsStatus,
            $this->assetCaption,
        );

        $placeInDocument = $this->insertKind !== '' || $this->compositionMode === SpaceContentRevision::COMPOSITION_BLOCKS;
        $caption = $this->assetCaption;
        $this->reloadFromDraft();

        if ($placeInDocument) {
            $asset = $this->editableRevision()->assets()
                ->orderByDesc('space_content_revision_assets.id')
                ->first();

            if ($asset instanceof Asset) {
                $this->ensureBlockDocument();
                $this->blocks[] = $this->mediaBlock($asset, $caption);
                $this->persistDraft($revise, $updateBlocks);
            }
        }

        $this->closeMediaDialog();
        $this->resetErrorBag('publish');
        session()->flash('status', __('media.attached'));
    }

    public function updateAssetRights(
        int $assetId,
        string $rightsStatus,
        UpdateAssetRightsStatus $updateRights,
    ): void {
        $this->resetErrorBag('publish');
        $this->resetErrorBag('assetRights.'.$assetId);

        try {
            $updateRights->execute(
                $this->content,
                $this->asset($assetId),
                $this->user(),
                $rightsStatus,
            );
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            $this->addError('assetRights.'.$assetId, $exception->getMessage());

            return;
        }

        session()->flash('status', __('media.rights_updated'));
    }

    public function retryAssetProcessing(
        int $assetId,
        RetryAssetMediaProcessing $retry,
    ): void {
        $retry->execute($this->content, $this->asset($assetId), $this->user());
        session()->flash('status', __('studio.media_retry_queued'));
    }

    public function removeAsset(
        int $assetId,
        RemoveAssetFromSpaceContent $remove,
        ReviseSpaceContent $revise,
        UpdateSpaceContentBlocks $updateBlocks,
    ): void {
        $asset = $this->asset($assetId);

        // Blocks that show this media leave the document together with it.
        $this->blocks = array_values(array_filter(
            $this->blocks,
            fn (array $block): bool => ($block['data']['asset_uuid'] ?? null) !== $asset->uuid,
        ));
        if ($this->compositionMode === SpaceContentRevision::COMPOSITION_BLOCKS && $this->blocks === []) {
            $this->useSimpleDocument();
        }
        $this->persistDraft($revise, $updateBlocks);

        $this->content = $remove->execute($this->content, $asset, $this->user());
        $this->reloadFromDraft();
        $this->resetErrorBag('publish');
        session()->flash('status', __('media.removed'));
    }

    // ---- Publication and lifecycle -------------------------------------

    public function openPublishPanel(ReviseSpaceContent $revise, UpdateSpaceContentBlocks $updateBlocks): void
    {
        $this->persistDraft($revise, $updateBlocks);
        $this->resetErrorBag('publish');
        $this->publishPanelOpen = true;
    }

    public function closePublishPanel(): void
    {
        $this->publishPanelOpen = false;
    }

    public function publish(
        PublishSpaceContent $publish,
        ReviseSpaceContent $revise,
        UpdateSpaceContentBlocks $updateBlocks,
    ): void {
        $this->resetErrorBag('publish');
        $this->persistDraft($revise, $updateBlocks);

        if ($this->publicationIssues()->isNotEmpty()) {
            $this->publishPanelOpen = true;
            $this->addError('publish', __('media.publish_blocked_help'));

            return;
        }

        try {
            $this->content = $publish->execute($this->content, $this->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            $this->publishPanelOpen = true;
            $this->addError('publish', $exception->getMessage());

            return;
        }

        $this->publishPanelOpen = false;
        $this->reloadFromDraft();
        session()->flash('status', __('ui.content.published'));
    }

    public function preview(ReviseSpaceContent $revise, UpdateSpaceContentBlocks $updateBlocks): mixed
    {
        $this->persistDraft($revise, $updateBlocks);
        $current = $this->content->fresh();
        $hasDraft = $current instanceof SpaceContent && $current->draft_revision_id !== null;

        return $this->redirectRoute('contexts.contents.show', array_filter([
            'context' => $this->context,
            'content' => $this->content,
            'preview' => $hasDraft ? 'draft' : null,
        ]));
    }

    public function beginArchive(): void
    {
        Gate::forUser($this->user())->authorize('archive', $this->content);
        $this->showArchiveConfirmation = true;
    }

    public function cancelArchive(): void
    {
        $this->showArchiveConfirmation = false;
        $this->reset('archiveReason');
        $this->resetErrorBag('archiveReason');
    }

    public function archive(ArchiveSpaceContent $archive): void
    {
        $this->validate(['archiveReason' => ['required', 'string', 'max:1000']]);
        $this->content = $archive->execute(
            $this->content,
            $this->user(),
            $this->archiveReason,
        );
        $this->showArchiveConfirmation = false;
        $this->reset('archiveReason');
        session()->flash('status', __('studio.archived'));
    }

    public function restore(RestoreSpaceContent $restore): void
    {
        $this->validate(['restoreReason' => ['required', 'string', 'max:1000']]);
        $this->content = $restore->execute(
            $this->content,
            $this->user(),
            $this->restoreReason,
        );
        $this->reset('restoreReason');
        $this->reloadFromDraft();
        session()->flash('status', __('studio.restored'));
    }

    public function render(): View
    {
        $user = $this->user();
        $current = SpaceContent::query()
            ->with([
                'definition',
                'author.user',
                'blueprintVersion.blueprint',
            ])
            ->findOrFail($this->content->id);

        abort_unless((int) $current->context_id === (int) $this->context->id, 404);
        Gate::forUser($user)->authorize('view', $current);
        $this->content = $current;

        $canUpdate = Gate::forUser($user)->allows('update', $current);
        $canPublish = Gate::forUser($user)->allows('publish', $current);
        $canArchive = Gate::forUser($user)->allows('archive', $current);
        $canRestore = Gate::forUser($user)->allows('restore', $current);
        $canViewRevisions = Gate::forUser($user)->allows('revisions', $current);

        $currentRevision = $this->visibleRevision($canUpdate || $canViewRevisions);
        $currentRevision->loadMissing(['assets.uploader.user']);
        $definitionVersion = $currentRevision->definitionVersion()->firstOrFail();
        $fields = collect($definitionVersion->schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->keyBy(fn (array $field): string => (string) $field['key']);

        $revisions = $canViewRevisions
            ? $current->revisions()
                ->with(['createdBy.user', 'definitionVersion'])
                ->orderByDesc('revision')
                ->get()
            : new Collection;

        $lifecycleEvents = $canViewRevisions
            ? $current->lifecycleEvents()->with('actor.user')->get()
            : new Collection;

        $mediaAssets = $currentRevision->assets;
        $publicationIssues = $canPublish
            ? app(SpaceContentPublicationEvidence::class)->issues($currentRevision)
            : new Collection;
        $publishBlocked = $canPublish && $publicationIssues->isNotEmpty();
        $activeRevision = $current->activeRevisionRecord();
        $canOpenReader = $current->status === 'published'
            && $activeRevision instanceof SpaceContentRevision;

        $this->context->loadMissing(ContextPresenter::RELATIONS);
        $presenter = app(ContextPresenter::class);

        return view('livewire.contexts.content-studio', [
            'currentRevision' => $currentRevision,
            'definitionVersion' => $definitionVersion,
            'fields' => $fields,
            'revisions' => $revisions,
            'lifecycleEvents' => $lifecycleEvents,
            'canUpdate' => $canUpdate,
            'canPublish' => $canPublish,
            'canArchive' => $canArchive,
            'canRestore' => $canRestore,
            'canViewRevisions' => $canViewRevisions,
            'canOpenReader' => $canOpenReader,
            'mediaAssets' => $mediaAssets,
            'mediaByUuid' => $mediaAssets->keyBy('uuid'),
            'rightsStatuses' => Asset::RIGHTS_STATUSES,
            'publicationIssues' => $publicationIssues,
            'publishBlocked' => $publishBlocked,
            'dirty' => $this->isDirty(),
            'placeLabel' => $presenter->label($this->context),
            'placeAudience' => $presenter->audience($this->context),
            'kindLabel' => $current->blueprintVersion->blueprint->name ?? $current->definition->name,
            'uploadLimits' => MediaLimits::contentBytes(),
            'hasOutline' => (bool) ($current->blueprintVersion?->authoring['outline'] ?? true),
        ])->title($currentRevision->title);
    }

    // ---- Internals ------------------------------------------------------

    /**
     * Write whatever changed: fields first (title, payload), then the block
     * document. Both use the existing immutable revision actions; nothing is
     * written when nothing changed.
     */
    private function persistDraft(ReviseSpaceContent $revise, UpdateSpaceContentBlocks $updateBlocks): void
    {
        if (! Gate::forUser($this->user())->allows('update', $this->content) || ! $this->isDirty()) {
            return;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['array'],
        ]);

        $saved = json_decode($this->savedFingerprint, true);
        $fieldsChanged = ($saved['title'] ?? null) !== trim($this->title)
            || ($saved['payload'] ?? null) !== $this->payload;
        $blocksChanged = ($saved['mode'] ?? null) !== $this->compositionMode
            || ($saved['blocks'] ?? null) !== $this->comparableBlocks();

        try {
            DB::transaction(function () use ($revise, $updateBlocks, $fieldsChanged, $blocksChanged): void {
                if ($fieldsChanged) {
                    $this->content = $revise->execute($this->content, $this->user(), $this->title, $this->payload);
                }

                if ($blocksChanged) {
                    $this->content = $updateBlocks->execute(
                        $this->content,
                        $this->user(),
                        $this->compositionMode,
                        $this->preparedBlocks(),
                    );
                }
            });
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            // The transaction rolled back; keep the author's edits and explain.
            throw ValidationException::withMessages(['studio' => $exception->getMessage()]);
        }

        $this->reloadFromDraft();
        $this->dispatch('studio-saved');
    }

    /**
     * Blocks ready for UpdateSpaceContentBlocks: lists become arrays and media
     * blocks point at the media placement of the latest editable revision
     * (placements are renewed with every revision; the asset is stable).
     *
     * @return list<array<string, mixed>>
     */
    private function preparedBlocks(): array
    {
        if ($this->compositionMode !== SpaceContentRevision::COMPOSITION_BLOCKS) {
            return [];
        }

        $placements = DB::table('space_content_revision_assets as placement')
            ->join('assets as asset', 'asset.id', '=', 'placement.asset_id')
            ->where('placement.space_content_revision_id', $this->editableRevision()->id)
            ->select(['placement.uuid as placement_uuid', 'asset.uuid as asset_uuid'])
            ->pluck('placement_uuid', 'asset_uuid');

        $prepared = [];
        foreach ($this->blocks as $block) {
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (($block['type'] ?? null) === SpaceContentBlock::TYPE_LIST) {
                $data['items'] = collect(preg_split('/\R/u', (string) ($data['items_text'] ?? '')) ?: [])
                    ->map(static fn (string $item): string => trim($item))
                    ->filter()
                    ->values()
                    ->all();
                unset($data['items_text']);
            }

            if (isset($data['asset_uuid'])) {
                $placement = $placements[$data['asset_uuid']] ?? null;
                if (! is_string($placement)) {
                    continue;
                }
                $data['asset_placement_uuid'] = $placement;
            }

            $prepared[] = [
                'logical_uuid' => $block['logical_uuid'] ?? (string) Str::uuid(),
                'type' => $block['type'] ?? '',
                'data' => $data,
                'style' => is_array($block['style'] ?? null) ? $block['style'] : [],
            ];
        }

        return $prepared;
    }

    private function ensureBlockDocument(): void
    {
        if ($this->compositionMode === SpaceContentRevision::COMPOSITION_BLOCKS) {
            return;
        }

        $this->compositionMode = SpaceContentRevision::COMPOSITION_BLOCKS;
        $this->blocks = [];

        $version = $this->editableRevision()->definitionVersion()->firstOrFail();
        foreach ($version->schema['fields'] ?? [] as $field) {
            if (is_array($field) && is_string($field['key'] ?? null)) {
                $this->blocks[] = [
                    'logical_uuid' => (string) Str::uuid(),
                    'type' => SpaceContentBlock::TYPE_FIELD,
                    'data' => ['field_key' => $field['key']],
                    'style' => [],
                ];
            }
        }
    }

    /** @return array<string, mixed> */
    private function mediaBlock(Asset $asset, string $caption): array
    {
        $type = match ($asset->mediaKind()) {
            'image' => SpaceContentBlock::TYPE_IMAGE,
            'audio' => SpaceContentBlock::TYPE_AUDIO,
            'video' => SpaceContentBlock::TYPE_VIDEO,
            default => SpaceContentBlock::TYPE_FILE,
        };

        return [
            'logical_uuid' => (string) Str::uuid(),
            'type' => $type,
            'data' => [
                'asset_uuid' => $asset->uuid,
                'filename' => $asset->original_filename,
                'caption' => $caption,
            ],
            'style' => [],
        ];
    }

    private function reloadFromDraft(): void
    {
        $this->draftChangedElsewhere = false;
        $current = $this->content->fresh();
        if (! $current instanceof SpaceContent) {
            return;
        }
        $this->content = $current;

        $revision = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
        if (! $revision instanceof SpaceContentRevision) {
            return;
        }

        $this->title = $revision->title;
        $this->payload = $revision->payload;
        $this->compositionMode = $revision->composition_mode;
        $this->blocks = $revision->composition_mode === SpaceContentRevision::COMPOSITION_BLOCKS
            ? $revision->blocks()->get()->map(function (SpaceContentBlock $block): array {
                $data = $block->data;
                if ($block->type === SpaceContentBlock::TYPE_LIST) {
                    $data['items_text'] = implode("\n", is_array($data['items'] ?? null) ? $data['items'] : []);
                    unset($data['items']);
                }
                unset($data['asset_placement_uuid']);

                return [
                    'logical_uuid' => $block->logical_uuid,
                    'type' => $block->type,
                    'data' => $data,
                    'style' => $block->style ?? [],
                ];
            })->values()->all()
            : [];

        $this->savedFingerprint = $this->fingerprint();
    }

    private function fingerprint(): string
    {
        return (string) json_encode([
            'title' => trim($this->title),
            'payload' => $this->payload,
            'mode' => $this->compositionMode,
            'blocks' => $this->comparableBlocks(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @return list<array<string, mixed>> */
    private function comparableBlocks(): array
    {
        return array_map(static fn (array $block): array => [
            'logical_uuid' => $block['logical_uuid'] ?? null,
            'type' => $block['type'] ?? null,
            'data' => $block['data'] ?? [],
            'style' => array_filter(is_array($block['style'] ?? null) ? $block['style'] : [], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ], $this->blocks);
    }

    /** @return Collection<int, array{asset_id: int, filename: string, code: string}> */
    private function publicationIssues(): Collection
    {
        $current = $this->content->fresh();
        if (! $current instanceof SpaceContent) {
            return new Collection;
        }

        $draft = $current->draftRevisionRecord();
        if (! $draft instanceof SpaceContentRevision) {
            return new Collection;
        }

        return app(SpaceContentPublicationEvidence::class)->issues($draft);
    }

    private function editableRevision(): SpaceContentRevision
    {
        $current = $this->content->fresh();
        abort_unless($current instanceof SpaceContent, 404);

        $revision = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        return $revision;
    }

    private function visibleRevision(bool $canSeeWorkingRevision): SpaceContentRevision
    {
        if ($canSeeWorkingRevision) {
            $revision = $this->content->draftRevisionRecord()
                ?? $this->content->activeRevisionRecord();
            abort_unless($revision instanceof SpaceContentRevision, 404);

            return $revision;
        }

        $revision = $this->content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        return $revision;
    }

    private function asset(int $assetId): Asset
    {
        return Asset::query()
            ->where('context_id', $this->context->id)
            ->findOrFail($assetId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
