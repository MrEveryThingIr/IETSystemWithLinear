<?php

namespace App\Livewire\Relationships;

use App\Models\Relationship;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Relationships')]
class Index extends Component
{
    public string $status = 'all';

    public function render(): View
    {
        Gate::authorize('viewAny', Relationship::class);

        $user = request()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        $query = Relationship::query()
            ->whereHas('participants', fn ($participants) => $participants->where('actor_id', $user->actor->id))
            ->with([
                'purposeConcept.labels',
                'originatingIntent',
                'participants.actor.user',
                'contextBinding.context',
            ])
            ->latest('updated_at');

        if (in_array($this->status, ['proposed', 'active', 'ended', 'cancelled'], true)) {
            $query->where('status', $this->status);
        }

        $relationships = $query->limit(100)->get();

        return view('livewire.relationships.index', compact('relationships'));
    }
}
