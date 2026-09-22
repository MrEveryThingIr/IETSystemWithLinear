<?php

namespace App\Livewire\Contexts;

use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Outline')]
class ContentOutline extends Component
{
    public Context $context;
    public SpaceContent $content;

    /** @var list<int> */
    public array $childIds = [];

    /** @var list<int> */
    public array $savedChildIds = [];

    public string $selectedChildId = '';
    public string $search = '';

    public function mount(Context $context, SpaceContent $content): void
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);
        Gate::forUser($this->user())->authorize('update', $content);

        $this->context = $context;
        $this->content = $content;
        $this->reloadStructure();
    }

    public function addChild(): void
    {
        $this->resetErrorBag('selectedChildId');
        abort_unless(ctype_digit($this->selectedChildId), 422);
        $childId = (int) $this->selectedChildId;

        $candidate = $this->authorizedCandidatesQuery()->whereKey($childId)->first();
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
        Gate::forUser($this->user())->authorize('update', $current);

        $candidateContents = $this->candidateContents();
        $structureItems = $this->authorizedCandidatesQuery()
            ->whereIn('id', $this->childIds)
            ->with(['activeRevision', 'draftRevision', 'author.user', 'definition'])
            ->get()
            ->keyBy('id');

        $orderedItems = collect($this->childIds)
            ->map(fn (int $id): ?SpaceContent => $structureItems->get($id))
            ->filter()
            ->values();
        $hasUnsavedChanges = $this->childIds !== $this->savedChildIds;

        return view('livewire.contexts.content-outline', [
            'candidateContents' => $candidateContents,
            'structureItems' => $orderedItems,
            'hasUnsavedChanges' => $hasUnsavedChanges,
        ]);
    }

    /** @return Collection<int, SpaceContent> */
    private function candidateContents(): Collection
    {
        $search = trim($this->search);

        return $this->authorizedCandidatesQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('draftRevision', fn (Builder $revision) => $revision->where('title', 'like', '%'.$search.'%'))
                        ->orWhereHas('activeRevision', fn (Builder $revision) => $revision->where('title', 'like', '%'.$search.'%'))
                        ->orWhereHas('definition', fn (Builder $definition) => $definition->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->with(['activeRevision', 'draftRevision', 'author.user', 'definition'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    /** @return Builder<SpaceContent> */
    private function authorizedCandidatesQuery(): Builder
    {
        $user = $this->user();

        return SpaceContent::query()
            ->where('context_id', $this->context->id)
            ->where('id', '!=', $this->content->id)
            ->where('status', '!=', 'archived')
            ->getQuery()
            ->where(function (Builder $query) use ($user): void {
                $actorId = (int) $user->actor?->id;
                $query->where('author_actor_id', $actorId)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', 'published')
                            ->whereHas('activeRevision', fn (Builder $revision) => $revision->where('evidence_status', SpaceContentRevision::EVIDENCE_SEALED));
                    });
            });
    }

    private function reloadStructure(): void
    {
        $revision = $this->content->draftRevisionRecord() ?? $this->content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        $ids = $revision->relationships()
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->orderBy('position')
            ->pluck('child_content_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $this->childIds = $ids;
        $this->savedChildIds = $ids;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
