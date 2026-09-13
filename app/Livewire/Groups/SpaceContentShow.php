<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use App\Support\SpaceContentFieldRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Content')]
class SpaceContentShow extends Component
{
    public Group $group;

    public GroupSpace $space;

    public SpaceContent $content;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        Gate::authorize('view', $content);

        $this->group = $group;
        $this->space = $space;
        $this->content = $content;
        $this->fillFromCurrentRevision();
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
        $this->fillFromCurrentRevision();
        session()->flash('status', __('ui.content.revised'));
    }

    public function publish(PublishSpaceContent $publishContent): void
    {
        $this->content = $publishContent->execute($this->content, $this->user());
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
        Gate::forUser($user)->authorize('view', $this->content);
        abort_unless((int) $this->content->group_space_id === (int) $this->space->id, 404);

        $currentRevision = $this->content->currentRevisionRecord();
        /** @var SpaceContentDefinitionVersion $definitionVersion */
        $definitionVersion = $currentRevision->definitionVersion()->firstOrFail();
        $revisions = $this->content->revisions()
            ->with(['createdBy.user', 'definitionVersion'])
            ->orderByDesc('revision')
            ->get();

        $fieldComponents = [];
        foreach ($definitionVersion->schema['fields'] ?? [] as $field) {
            if (is_array($field) && is_string($field['type'] ?? null)) {
                $fieldComponents[$field['type']] = $registry->componentFor($field['type']);
            }
        }

        $canUpdate = Gate::forUser($user)->allows('update', $this->content);
        $canPublish = Gate::forUser($user)->allows('publish', $this->content);
        $canArchive = Gate::forUser($user)->allows('archive', $this->content);

        return view('livewire.groups.space-content-show', compact(
            'currentRevision',
            'definitionVersion',
            'revisions',
            'fieldComponents',
            'canUpdate',
            'canPublish',
            'canArchive',
        ));
    }

    private function fillFromCurrentRevision(): void
    {
        $revision = $this->content->currentRevisionRecord();
        $this->title = $revision->title;
        $this->payload = $revision->payload;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
