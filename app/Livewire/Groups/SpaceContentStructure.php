<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Content Structure')]
class SpaceContentStructure extends Component
{
    public Group $group;

    public GroupSpace $space;

    public SpaceContent $content;

    /** @var list<int> */
    public array $childIds = [];

    public string $selectedChildId = '';

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        Gate::authorize('update', $content);

        $this->group = $group;
        $this->space = $space;
        $this->content = $content;
        $this->reloadStructure();
    }

    public function addChild(): void
    {
        $this->resetErrorBag('selectedChildId');
        abort_unless(ctype_digit($this->selectedChildId), 422);
        $childId = (int) $this->selectedChildId;

        $candidate = $this->candidateContents()->firstWhere('id', $childId);
        if (! $candidate instanceof SpaceContent) {
            $this->addError('selectedChildId', __('structure.invalid_child'));

            return;
        }

        if (in_array($childId, $this->childIds, true)) {
            $this->addError('selectedChildId', __('structure.duplicate_child'));

            return;
        }

        $this->childIds[] = $childId;
        $this->selectedChildId = '';
    }

    public function removeChild(int $childId): void
    {
        $this->childIds = array_values(array_filter(
            $this->childIds,
            static fn (int $id): bool => $id !== $childId,
        ));
    }

    public function moveUp(int $childId): void
    {
        $index = array_search($childId, $this->childIds, true);
        if (! is_int($index) || $index <= 0) {
            return;
        }

        [$this->childIds[$index - 1], $this->childIds[$index]] = [
            $this->childIds[$index],
            $this->childIds[$index - 1],
        ];
    }

    public function moveDown(int $childId): void
    {
        $index = array_search($childId, $this->childIds, true);
        if (! is_int($index) || $index >= count($this->childIds) - 1) {
            return;
        }

        [$this->childIds[$index + 1], $this->childIds[$index]] = [
            $this->childIds[$index],
            $this->childIds[$index + 1],
        ];
    }

    public function save(UpdateSpaceContentStructure $updateStructure): void
    {
        $this->content = $updateStructure->execute($this->content, $this->user(), $this->childIds);
        $this->reloadStructure();
        session()->flash('status', __('structure.saved'));
    }

    public function render(): View
    {
        $current = $this->content->fresh();
        abort_unless($current instanceof SpaceContent, 404);
        $this->content = $current;
        Gate::forUser($this->user())->authorize('update', $this->content);

        $candidateContents = $this->candidateContents();
        $itemsById = $candidateContents->keyBy('id');
        $structureItems = collect($this->childIds)
            ->map(fn (int $id): ?SpaceContent => $itemsById->get($id))
            ->filter()
            ->values();

        return view('livewire.groups.space-content-structure', compact(
            'candidateContents',
            'structureItems',
        ));
    }

    /** @return Collection<int, SpaceContent> */
    private function candidateContents(): Collection
    {
        $user = $this->user();

        return $this->space->contents()
            ->where('id', '!=', $this->content->id)
            ->where('status', '!=', 'archived')
            ->with(['activeRevision', 'draftRevision', 'author.user'])
            ->orderBy('id')
            ->get()
            ->filter(fn (SpaceContent $candidate): bool => Gate::forUser($user)->allows('view', $candidate))
            ->values();
    }

    private function reloadStructure(): void
    {
        $revision = $this->content->draftRevisionRecord() ?? $this->content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        $this->childIds = $revision->relationships()
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->orderBy('position')
            ->pluck('child_content_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
