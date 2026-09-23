<?php

namespace App\Livewire\Intents;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\Policies\ActorProfileIntentPolicy;
use App\ProfileIntentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Needs, offers & services')]
class Directory extends Component
{
    public string $quick = 'all';

    public string $kind = 'all';

    public string $subject = 'all';

    public string $arrangement = 'all';

    public string $exchange = 'all';

    public string $search = '';

    public string $location = '';

    public function clearFilters(): void
    {
        $this->reset(['quick', 'kind', 'subject', 'arrangement', 'exchange', 'search', 'location']);
        $this->quick = 'all';
        $this->kind = 'all';
        $this->subject = 'all';
        $this->arrangement = 'all';
        $this->exchange = 'all';
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $query = ActorProfileIntent::query()
            ->where('status', ProfileIntentStatus::Active->value)
            ->with(['concept.labels', 'profile.actor.user', 'profile.displayImage.asset']);

        $this->applyQuickFilter($query);

        if (in_array($this->kind, ['need', 'offer'], true)) {
            $query->where('kind', $this->kind);
        }

        if ($this->subject !== 'all') {
            $query->where('subject_kind', $this->subject);
        }

        if ($this->arrangement !== 'all') {
            $query->where('arrangement_kind', $this->arrangement);
        }

        if ($this->exchange !== 'all') {
            $query->where('exchange_preference', $this->exchange);
        }

        $location = trim($this->location);
        if ($location !== '') {
            $query->where('location_text', 'like', '%'.$location.'%');
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $scope) use ($search): void {
                $scope->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location_text', 'like', '%'.$search.'%')
                    ->orWhereHas('concept.labels', fn (Builder $labels) => $labels->where('label', 'like', '%'.$search.'%'));
            });
        }

        $policy = app(ActorProfileIntentPolicy::class);
        $intents = $query
            ->latest('updated_at')
            ->limit(200)
            ->get()
            ->filter(fn (ActorProfileIntent $intent): bool => $policy->view($user, $intent))
            ->values();

        return view('livewire.intents.directory', compact('intents'));
    }

    /** @param Builder<ActorProfileIntent> $query */
    private function applyQuickFilter(Builder $query): void
    {
        match ($this->quick) {
            'needs' => $query->where('kind', 'need'),
            'offers' => $query->where('kind', 'offer'),
            'services' => $query->where(function (Builder $scope): void {
                $scope->where('subject_kind', 'service')->orWhere('arrangement_kind', 'service');
            }),
            'property' => $query->where('subject_kind', 'property'),
            'capital' => $query->where(function (Builder $scope): void {
                $scope->where('subject_kind', 'capital')->orWhere('arrangement_kind', 'financing');
            }),
            'collaboration' => $query->where(function (Builder $scope): void {
                $scope->where('subject_kind', 'collaboration')->orWhere('arrangement_kind', 'collaboration');
            }),
            default => null,
        };
    }
}
