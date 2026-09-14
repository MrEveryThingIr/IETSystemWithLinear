<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\RemoveAssetFromSpaceContent;
use App\Actions\Groups\RestoreSpaceContent;
use App\Actions\Groups\RetryAssetMediaProcessing;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateAssetRightsStatus;
use App\Models\Asset;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentFieldRegistry;
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
class SpaceContentShow extends Component
{
    use WithFileUploads;

    public Group $group;
    public GroupSpace $space;
    public SpaceContent $content;
    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    public mixed $assetUpload = null;
    public string $assetRightsStatus = 'unknown';
    public string $assetCaption = '';
    public string $recordingCaption = '';
    public string $archiveReason = '';
    public string $restoreReason = '';
    public bool $showArchiveConfirmation = false;

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);

        $user = $this->user();
        Gate::forUser($user)->authorize('view', $content);
        abort_unless(
            Gate::forUser($user)->allows('update', $content)
                || Gate::forUser($user)->allows('revisions', $content)
                || Gate::forUser($user)->allows('restore', $content),
            403,
        );

        $this->group = $group;
        $this->space = $space;
        $this->content = $content;
        $this->fillFromEditableRevision();
    }

    public function saveRevision(ReviseSpaceContent $reviseContent): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['array'],
        ]);

        $this->content = $reviseContent->execute($this->content, $this->user(), $this->title, $this->payload);
        $this->fillFromEditableRevision();
        session()->flash('status', __('ui.content.revised'));
    }

    public function attachAsset(AttachAssetToSpaceContent $attachAsset): void
    {
        $this->validate([
            'assetUpload' => ['required', 'file', 'max:12288'],
            'assetRightsStatus' => ['required', 'string', Rule::in(Asset::RIGHTS_STATUSES)],
            'assetCaption' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($this->assetUpload instanceof UploadedFile, 422);

        $this->content = $attachAsset->execute(
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

    public function attachRecordedAsset(AttachAssetToSpaceContent $attachAsset): void
    {
        $this->validate([
            'assetUpload' => ['required', 'file', 'max:12288'],
            'recordingCaption' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($this->assetUpload instanceof UploadedFile, 422);

        $this->content = $attachAsset->execute(
            $this->content,
            $this->user(),
            $this->assetUpload,
            'owned',
            $this->recordingCaption,
        );

        $this->reset('assetUpload', 'recordingCaption');
        $this->fillFromEditableRevision();
        $this->resetErrorBag('publish');
        session()->flash('status', __('media.recording_attached'));
    }

    public function updateAssetRights(int $assetId, string $rightsStatus, UpdateAssetRightsStatus $updateRights): void
    {
        $this->resetErrorBag('publish');
        $this->resetErrorBag('assetRights.'.$assetId);
        $asset = $this->asset($assetId);

        try {
            $updateRights->execute($this->content, $asset, $this->user(), $rightsStatus);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            $this->addError('assetRights.'.$assetId, $exception->getMessage());

            return;
        }

        session()->flash('status', __('media.rights_updated'));
    }

    public function retryAssetProcessing(int $assetId, RetryAssetMediaProcessing $retry): void
    {
        $retry->execute($this->content, $this->asset($assetId), $this->user());
        session()->flash('status', __('studio.media_retry_queued'));
    }

    public function removeAsset(int $assetId, RemoveAssetFromSpaceContent $removeAsset): void
    {
        $this->content = $removeAsset->execute($this->content, $this->asset($assetId), $this->user());
        $this->fillFromEditableRevision();
        $this->resetErrorBag('publish');
        session()->flash('status', __('media.removed'));
    }

    public function publish(PublishSpaceContent $publishContent): void
    {
        $this->resetErrorBag('publish');

        if ($this->publicationIssues()->isNotEmpty()) {
            $this->addError('publish', __('media.publish_blocked_help'));

            return;
        }

        try {
            $this->content = $publishContent->execute($this->content, $this->user());
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

    public function archive(ArchiveSpaceContent $archiveContent): void
    {
        $this->validate(['archiveReason' => ['required', 'string', 'max:1000']]);
        $this->content = $archiveContent->execute($this->content, $this->user(), $this->archiveReason);
        $this->showArchiveConfirmation = false;
        $this->reset('archiveReason');
        session()->flash('status', __('studio.archived'));
    }

    public function restore(RestoreSpaceContent $restoreContent): void
    {
        $this->validate(['restoreReason' => ['required', 'string', 'max:1000']]);
        $this->content = $restoreContent->execute($this->content, $this->user(), $this->restoreReason);
        $this->reset('restoreReason');
        $this->fillFromEditableRevision();
        session()->flash('status', __('studio.restored'));
    }

    public function render(): View
    {
        $registry = app(SpaceContentFieldRegistry::class);
        $user = $this->user();
        $current = SpaceContent::query()->with(['definition', 'author.user'])->findOrFail($this->content->id);
        abort_unless((int) $current->group_space_id === (int) $this->space->id, 404);
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
        abort_unless($definitionVersion instanceof SpaceContentDefinitionVersion, 404);

        $revisions = $canViewRevisions
            ? $current->revisions()->with(['createdBy.user', 'definitionVersion'])->orderByDesc('revision')->get()
            : new Collection;

        $lifecycleEvents = $canViewRevisions
            ? $current->lifecycleEvents()->with('actor.user')->get()
            : new Collection;

        $fieldComponents = [];
        foreach ($definitionVersion->schema['fields'] ?? [] as $field) {
            if (is_array($field) && is_string($field['type'] ?? null)) {
                $fieldComponents[$field['type']] = $registry->componentFor($field['type']);
            }
        }

        $mediaAssets = $currentRevision->assets;
        $rightsStatuses = Asset::RIGHTS_STATUSES;
        $publicationIssues = $canPublish
            ? app(SpaceContentPublicationEvidence::class)->issues($currentRevision)
            : new Collection;
        $publishBlocked = $canPublish && $publicationIssues->isNotEmpty();
        $activeRevision = $current->activeRevisionRecord();
        $legacyEvidence = $activeRevision instanceof SpaceContentRevision && ! $activeRevision->hasVerifiableManifest();
        $canOpenReader = $current->status === 'published' && $activeRevision instanceof SpaceContentRevision;

        return view('livewire.groups.space-content-show', compact(
            'currentRevision',
            'definitionVersion',
            'revisions',
            'lifecycleEvents',
            'fieldComponents',
            'canUpdate',
            'canPublish',
            'canArchive',
            'canRestore',
            'canViewRevisions',
            'canOpenReader',
            'legacyEvidence',
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
        $user = $this->user();
        if (! Gate::forUser($user)->allows('update', $this->content)) {
            return;
        }

        $revision = $this->content->draftRevisionRecord() ?? $this->content->activeRevisionRecord();
        if (! $revision instanceof SpaceContentRevision) {
            return;
        }

        $this->title = $revision->title;
        $this->payload = $revision->payload;
    }

    private function visibleRevision(bool $canSeeWorkingRevision): SpaceContentRevision
    {
        if ($canSeeWorkingRevision) {
            $revision = $this->content->draftRevisionRecord() ?? $this->content->activeRevisionRecord();
            abort_unless($revision instanceof SpaceContentRevision, 404);

            return $revision;
        }

        $revision = $this->content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        return $revision;
    }

    private function asset(int $assetId): Asset
    {
        return Asset::query()->where('group_space_id', $this->space->id)->findOrFail($assetId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
