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
use App\Models\Asset;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentPublicationEvidence;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
#[Title('Content Studio')]
class ContentStudio extends Component
{
    use WithFileUploads;

    public Context $context;

    public SpaceContent $content;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    public mixed $assetUpload = null;

    public string $assetRightsStatus = 'unknown';

    public string $assetCaption = '';

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
        $this->fillFromEditableRevision();
    }

    public function saveRevision(ReviseSpaceContent $revise): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['array'],
        ]);

        $this->content = $revise->execute(
            $this->content,
            $this->user(),
            $this->title,
            $this->payload,
        );
        $this->fillFromEditableRevision();
        session()->flash('status', __('ui.content.revised'));
    }

    public function attachAsset(AttachAssetToSpaceContent $attach): void
    {
        $this->validate([
            'assetUpload' => ['required', 'file', 'max:12288'],
            'assetRightsStatus' => ['required', 'string', Rule::in(Asset::RIGHTS_STATUSES)],
            'assetCaption' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($this->assetUpload instanceof UploadedFile, 422);

        $this->content = $attach->execute(
            $this->content,
            $this->user(),
            $this->assetUpload,
            $this->assetRightsStatus,
            $this->assetCaption,
        );

        $this->reset('assetUpload', 'assetCaption');
        $this->fillFromEditableRevision();
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
    ): void {
        $this->content = $remove->execute(
            $this->content,
            $this->asset($assetId),
            $this->user(),
        );
        $this->fillFromEditableRevision();
        $this->resetErrorBag('publish');
        session()->flash('status', __('media.removed'));
    }

    public function publish(PublishSpaceContent $publish): void
    {
        $this->resetErrorBag('publish');

        if ($this->publicationIssues()->isNotEmpty()) {
            $this->addError('publish', __('media.publish_blocked_help'));

            return;
        }

        try {
            $this->content = $publish->execute($this->content, $this->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            $this->addError('publish', $exception->getMessage());

            return;
        }

        $this->fillFromEditableRevision();
        session()->flash('status', __('ui.content.published'));
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
        $this->fillFromEditableRevision();
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
        $rightsStatuses = Asset::RIGHTS_STATUSES;
        $publicationIssues = $canPublish
            ? app(SpaceContentPublicationEvidence::class)->issues($currentRevision)
            : new Collection;
        $publishBlocked = $canPublish && $publicationIssues->isNotEmpty();
        $activeRevision = $current->activeRevisionRecord();
        $canOpenReader = $current->status === 'published'
            && $activeRevision instanceof SpaceContentRevision;

        return view('livewire.contexts.content-studio', compact(
            'currentRevision',
            'definitionVersion',
            'revisions',
            'lifecycleEvents',
            'canUpdate',
            'canPublish',
            'canArchive',
            'canRestore',
            'canViewRevisions',
            'canOpenReader',
            'mediaAssets',
            'rightsStatuses',
            'publicationIssues',
            'publishBlocked',
        ));
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

    private function fillFromEditableRevision(): void
    {
        if (! Gate::forUser($this->user())->allows('update', $this->content)) {
            return;
        }

        $revision = $this->content->draftRevisionRecord()
            ?? $this->content->activeRevisionRecord();

        if (! $revision instanceof SpaceContentRevision) {
            return;
        }

        $this->title = $revision->title;
        $this->payload = $revision->payload;
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
