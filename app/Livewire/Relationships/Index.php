<?php

namespace App\Livewire\Relationships;

use App\Models\Relationship;
use App\Models\User;
use App\RelationshipStatus;
use App\Support\DealPipeline;
use App\Support\Deals\AgreementDrafter;
use App\Support\Work\DealsOverview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Deals')]
class Index extends Component
{
    public string $status = 'all';

    public function render(DealPipeline $pipeline): View
    {
        Gate::authorize('viewAny', Relationship::class);

        $user = request()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        $query = Relationship::query()
            ->whereHas('participants', fn ($participants) => $participants->where('actor_id', $user->actor->id))
            ->with([
                'purposeConcept.labels',
                'originatingIntent',
                'matchedIntent',
                'participants.actor.user',
                'contextBinding.context',
                'proposals',
                'contracts.versions.commitments.fulfillments',
            ])
            ->latest('updated_at');

        if (in_array($this->status, ['proposed', 'active', 'ended', 'cancelled'], true)) {
            $query->where('status', $this->status);
        }

        $relationships = $query->limit(100)->get();
        $overview = app(DealsOverview::class);

        // Agreements I started writing and have not sent: a working copy only I (and anyone allowed to draft) can see.
        $drafts = $relationships
            ->filter(fn (Relationship $deal): bool => $deal->status === RelationshipStatus::Active
                && isset($deal->metadata['agreement_draft'])
                && AgreementDrafter::canDraft($deal->loadMissing('originatingIntent.profile'), $user))
            ->values();

        return view('livewire.relationships.index', [
            'drafts' => $drafts,
            'relationships' => $relationships,
            'pipeline' => $pipeline,
            'attention' => $overview->attention($user),
            'due' => $overview->due($user),
        ]);
    }
}
