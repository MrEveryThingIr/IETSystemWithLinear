<?php

namespace App\Livewire\Concerns;

use App\Actions\Content\DeleteContentPermanently;
use App\Actions\Content\RenameContent;
use App\Actions\Groups\ArchiveSpaceContent;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Rename, archive and delete-for-good from any screen that lists or edits
 * Content. The dialogs live in resources/views/livewire/content/partials/lifecycle-dialogs.blade.php.
 */
trait ManagesContentLifecycle
{
    public ?string $renameUuid = null;

    public string $renameTitle = '';

    public ?string $deleteUuid = null;

    public string $deleteConfirmation = '';

    /** @var array{blockers: array<string, int>, removes: array<string, int>, title: string, can_archive: bool} */
    public array $deleteAssessment = ['blockers' => [], 'removes' => [], 'title' => '', 'can_archive' => false];

    public function openRename(string $uuid): void
    {
        $content = $this->lifecycleContent($uuid);
        Gate::forUser($this->lifecycleUser())->authorize('update', $content);
        $this->renameUuid = $content->uuid;
        $this->renameTitle = $this->lifecycleTitle($content);
        $this->resetErrorBag('renameTitle');
    }

    public function closeRename(): void
    {
        $this->reset('renameUuid', 'renameTitle');
    }

    public function rename(RenameContent $rename): void
    {
        abort_if($this->renameUuid === null, 404);
        $this->validate(['renameTitle' => ['required', 'string', 'max:255']]);
        $result = $rename->execute($this->lifecycleContent($this->renameUuid), $this->lifecycleUser(), $this->renameTitle);
        $this->closeRename();
        $this->afterContentRenamed($result['content']);
        session()->flash('status', $result['live'] ? __('studio.rename.done_live') : __('studio.rename.done_draft'));
    }

    public function openDelete(string $uuid, DeleteContentPermanently $delete): void
    {
        $content = $this->lifecycleContent($uuid);
        $user = $this->lifecycleUser();
        Gate::forUser($user)->authorize('delete', $content);
        $this->deleteUuid = $content->uuid;
        $this->deleteConfirmation = '';
        $this->deleteAssessment = [
            ...$delete->assess($content, $user),
            'title' => $this->lifecycleTitle($content),
            'can_archive' => Gate::forUser($user)->allows('archive', $content),
        ];
        $this->resetErrorBag('deleteConfirmation');
    }

    public function closeDelete(): void
    {
        $this->reset('deleteUuid', 'deleteConfirmation', 'deleteAssessment');
    }

    public function deleteForGood(DeleteContentPermanently $delete): mixed
    {
        abort_if($this->deleteUuid === null, 404);
        $result = $delete->execute($this->lifecycleContent($this->deleteUuid), $this->lifecycleUser(), $this->deleteConfirmation);
        $this->closeDelete();
        session()->flash('status', __('studio.delete.done', ['title' => $result['title']]));

        return $this->afterContentDeleted();
    }

    /** The alternative when other people have engaged with it. */
    public function archiveInstead(ArchiveSpaceContent $archive): void
    {
        abort_if($this->deleteUuid === null, 404);
        $archive->execute($this->lifecycleContent($this->deleteUuid), $this->lifecycleUser(), __('studio.delete.archive_reason'));
        $this->closeDelete();
        session()->flash('status', __('studio.archived'));
    }

    protected function afterContentRenamed(SpaceContent $content): void {}

    protected function afterContentDeleted(): mixed
    {
        return null;
    }

    private function lifecycleContent(string $uuid): SpaceContent
    {
        return SpaceContent::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function lifecycleTitle(SpaceContent $content): string
    {
        return (string) ($content->draftRevisionRecord()->title ?? $content->activeRevisionRecord()->title ?? '');
    }

    private function lifecycleUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
