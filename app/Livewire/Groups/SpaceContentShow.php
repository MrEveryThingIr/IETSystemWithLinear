<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\RemoveAssetFromSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Models\Asset;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentFieldRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Content')]
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

    public string $assetRightsStatus = 'private_study_only';

    public string $assetCaption = '';

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        Gate::authorize('view', $content);

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

        $this->content = $reviseContent->execute(
            $this->content,
            $this->user(),
            $this->title,
            $this->payload,
        );
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
        session()->flash('status', __('media.attached'));
    }

    public function removeAsset(int $assetId, RemoveAssetFromSpaceContent $removeAsset): void
    {
        $asset = Asset::query()
            ->where('group_space_id', $this->space->id)
            ->findOrFail($assetId);

        $this->content = $removeAsset->execute($this->content, $asset, $this->user());
        $this->fillFromEditableRevision();
        session()->flash('status', __('media.removed'));
    }

    public function publish(PublishSpaceContent $publishContent): void
    {
        $this->content = $publishContent->execute($this->content, $this->user());
        $this->fillFromEditableRevision();
        session()->flash('status', __('ui.content.published'));
    }

    public function archive(ArchiveSpaceContent $archiveContent): void
    {
        $this->content = $archiveContent->execute($this->content, $this->user());
        session()->flash('status', __('ui.content.archived'));
    }

    public function render(): View
    {
        $registry = app(SpaceContentFieldRegistry::class);
        $user = $this->user();
        $current = $this->content->fresh();
        abort_unless($current instanceof SpaceContent, 404);
        $this->content = $current;

        Gate::forUser($user)->authorize('view', $this->content);
        abort_unless((int) $this->content->group_space_id === (int) $this->space->id, 404);

        $canUpdate = Gate::forUser($user)->allows('update', $this->content);
        $canPublish = Gate::forUser($user)->allows('publish', $this->content);
        $canArchive = Gate::forUser($user)->allows('archive', $this->content);
        $canViewRevisions = Gate::forUser($user)->allows('revisions', $this->content);

        $currentRevision = $this->visibleRevision($canUpdate || $canViewRevisions);
        $currentRevision->loadMissing(['assets.uploader.user']);
        /** @var SpaceContentDefinitionVersion $definitionVersion */
        $definitionVersion = $currentRevision->definitionVersion()->firstOrFail();

        $revisions = $canViewRevisions
            ? $this->content->revisions()
                ->with(['createdBy.user', 'definitionVersion'])
                ->orderByDesc('revision')
                ->get()
            : new Collection;

        $fieldComponents = [];
        foreach ($definitionVersion->schema['fields'] ?? [] as $field) {
            if (is_array($field) && is_string($field['type'] ?? null)) {
                $fieldComponents[$field['type']] = $registry->componentFor($field['type']);
            }
        }

        $mediaAssets = $currentRevision->assets;
        $rightsStatuses = Asset::RIGHTS_STATUSES;

        return view('livewire.groups.space-content-show', compact(
            'currentRevision',
            'definitionVersion',
            'revisions',
            'fieldComponents',
            'canUpdate',
            'canPublish',
            'canArchive',
            'canViewRevisions',
            'mediaAssets',
            'rightsStatuses',
        ));
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

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
