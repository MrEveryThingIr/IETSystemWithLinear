<?php

namespace App\Livewire\Intents;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileVisibility;
use App\ProfileIntentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Needs, offers & services')]
class Directory extends Component
{
    use WithPagination;

    public string $quick = 'all';

    public string $kind = 'all';

    public string $subject = 'all';

    public string $arrangement = 'all';

    public string $exchange = 'all';

    public string $search = '';

    public string $location = '';

    public ?string $highlight = null;

    protected array $queryString = [
        'quick' => ['except' => 'all'],
        'kind' => ['except' => 'all'],
        'subject' => ['except' => 'all'],
        'arrangement' => ['except' => 'all'],
        'exchange' => ['except' => 'all'],
        'search' => ['except' => ''],
        'location' => ['except' => ''],
        'highlight' => ['except' => null],
    ];

    public function updated(string $property): void
    {
        if (in_array($property, ['quick', 'kind', 'subject', 'arrangement', 'exchange', 'search', 'location'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['quick', 'kind', 'subject', 'arrangement', 'exchange', 'search', 'location']);
        $this->quick = 'all';
        $this->kind = 'all';
        $this->subject = 'all';
        $this->arrangement = 'all';
        $this->exchange = 'all';
        $this->resetPage();
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

        $actorId = $user->actor?->id;
        $isActiveVerified = $user->status === 'active' && $user->email_verified_at !== null;

        $query->where(function (Builder $visibility) use ($actorId, $isActiveVerified): void {
            if ($actorId !== null) {
                $visibility->whereHas('profile', fn (Builder $profile) => $profile->where('actor_id', $actorId));
            } else {
                $visibility->whereRaw('0 = 1');
            }

            $visibility->orWhere(function (Builder $discoverable) use ($isActiveVerified): void {
                $discoverable
                    ->whereHas('profile.actor', fn (Builder $actor) => $actor->where('status', 'active'))
                    ->where(function (Builder $intentVisibility) use ($isActiveVerified): void {
                        $intentVisibility->where('visibility', 'public');

                        if ($isActiveVerified) {
                            $intentVisibility->orWhere('visibility', 'authenticated');
                        }

                        $intentVisibility->orWhere(function (Builder $inherited) use ($isActiveVerified): void {
                            $inherited
                                ->where('visibility', 'inherited')
                                ->whereHas('profile', function (Builder $profile) use ($isActiveVerified): void {
                                    $profile->where('visibility', ProfileVisibility::Public->value);

                                    if ($isActiveVerified) {
                                        $profile->orWhere('visibility', ProfileVisibility::Authenticated->value);
                                    }
                                });
                        });
                    });
            });
        });

        $intents = $query
            ->latest('updated_at')
            ->latest('id')
            ->paginate(24);

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
