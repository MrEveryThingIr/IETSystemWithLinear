<?php

namespace App\Livewire\Platform;

use App\Actions\Categories\ReviewCategorySuggestions;
use App\Models\ConceptSuggestion;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Review categories people suggested for everyone: approve, merge or reject. */
#[Layout('layouts.app')]
class CategorySuggestions extends Component
{
    #[Url]
    public string $status = ConceptSuggestion::PENDING;

    public ?string $reviewing = null;

    public string $decision = 'approve';

    public ?int $targetId = null;

    public string $reviewNote = '';

    public function mount(ReviewCategorySuggestions $review): void
    {
        abort_unless($review->canReview($this->user()), 403);
        if (! in_array($this->status, [ConceptSuggestion::PENDING, ConceptSuggestion::APPROVED, ConceptSuggestion::MERGED, ConceptSuggestion::REJECTED], true)) {
            $this->status = ConceptSuggestion::PENDING;
        }
    }

    public function open(string $uuid): void
    {
        $this->reviewing = $uuid;
        $this->decision = 'approve';
        $this->targetId = null;
        $this->reviewNote = '';
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->reviewing = null;
    }

    public function decide(ReviewCategorySuggestions $review): void
    {
        $suggestion = ConceptSuggestion::query()->where('uuid', $this->reviewing)->firstOrFail();
        match ($this->decision) {
            'approve' => $review->approve($this->user(), $suggestion, $this->targetId),
            'merge' => $this->targetId !== null
                ? $review->merge($this->user(), $suggestion, $this->targetId, $this->reviewNote)
                : $this->addError('targetId', __('categories.review.choose_target')),
            'reject' => $review->reject($this->user(), $suggestion, $this->reviewNote),
            default => abort(422),
        };

        if ($this->getErrorBag()->isEmpty()) {
            $this->reviewing = null;
            session()->flash('status', __('categories.review.done'));
        }
    }

    public function render(CategoryCatalog $catalog, ReviewCategorySuggestions $review): View
    {
        abort_unless($review->canReview($this->user()), 403);
        $suggestions = ConceptSuggestion::query()
            ->where('status', $this->status)
            ->with(['suggestedBy.user', 'concept.labels'])
            ->latest('id')
            ->limit(100)
            ->get();

        $paths = [];
        foreach ($suggestions as $suggestion) {
            $paths[$suggestion->uuid] = $suggestion->parent_concept_id !== null
                ? implode(' › ', array_column($catalog->path((int) $suggestion->parent_concept_id, null), 'label'))
                : '';
        }

        return view('livewire.platform.category-suggestions', [
            'suggestions' => $suggestions,
            'paths' => $paths,
            'counts' => ConceptSuggestion::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ])->title(__('categories.review.title'));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
