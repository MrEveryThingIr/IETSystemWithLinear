<?php

namespace App\Livewire\Interactions;

use App\Models\Context;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Review submissions')]
class ReviewQueue extends Component
{
    public Context $context;

    public function mount(Context $context): void
    {
        Gate::forUser($this->user())->authorize('reviewInteractions', $context);
        $this->context = $context;
    }

    public function render(): View
    {
        $context = Context::query()->findOrFail($this->context->id);
        Gate::forUser($this->user())->authorize('reviewInteractions', $context);
        $this->context = $context;

        $submissions = Submission::query()
            ->where('context_id', $context->id)
            ->whereIn('status', [Submission::STATUS_SUBMITTED, Submission::STATUS_WITHDRAWN])
            ->with([
                'submitter.user',
                'definitionVersion.definition',
                'evaluations',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (Submission $submission): bool => Gate::forUser($this->user())->allows('view', $submission))
            ->values();

        return view('livewire.interactions.review-queue', compact('submissions'));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
