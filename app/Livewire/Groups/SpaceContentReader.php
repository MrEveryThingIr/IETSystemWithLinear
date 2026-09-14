<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentPublishedOutline;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Content')]
class SpaceContentReader extends Component
{
    public Group $group;

    public GroupSpace $space;

    public SpaceContent $content;

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        abort_unless($content->status === 'published' && $content->active_revision_id !== null, 404);
        Gate::authorize('view', $content);

        $this->group = $group;
        $this->space = $space;
        $this->content = $content;
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

        $definitionVersion = $revision->definitionVersion()->firstOrFail();

        $outline = $outlineBuilder->forRevision($revision, $user);
        $canEnterStudio = Gate::forUser($user)->allows('update', $current)
            || Gate::forUser($user)->allows('revisions', $current);
        $legacyEvidence = ! $revision->hasVerifiableManifest();

        return view('livewire.groups.space-content-reader', compact(
            'revision',
            'definitionVersion',
            'outline',
            'canEnterStudio',
            'legacyEvidence',
        ));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
