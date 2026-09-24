<?php

namespace App\Livewire\Proposals;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Proposals')]
class Index extends Component
{
    public function mount(): void
    {
        Gate::forUser($this->user())->authorize('viewAny', Proposal::class);
    }

    public function render(): View
    {
        $user = $this->user();
        Gate::forUser($user)->authorize('viewAny', Proposal::class);

        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $proposals = Proposal::query()
            ->whereHas('parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with([
                'creator.user',
                'relationship',
                'parties.actor.user',
                'contextBinding.context',
                'versions' => fn ($query) => $query->latest('version')->limit(1),
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.proposals.index', compact('proposals'));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
