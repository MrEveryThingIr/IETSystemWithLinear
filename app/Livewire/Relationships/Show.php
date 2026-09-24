<?php

namespace App\Livewire\Relationships;

use App\Actions\Relationships\CancelRelationship;
use App\Actions\Relationships\EndRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Models\Actor;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Relationship')]
class Show extends Component
{
    public Relationship $relationship;

    public function mount(Relationship $relationship): void
    {
        Gate::forUser($this->user())->authorize('view', $relationship);
        $this->relationship = $relationship;
    }

    public function accept(RespondToRelationship $respond): void
    {
        $this->relationship = $respond->execute($this->relationship, $this->user(), true);
        session()->flash('status', __('relationships.messages.accepted'));
    }

    public function decline(RespondToRelationship $respond): void
    {
        $this->relationship = $respond->execute($this->relationship, $this->user(), false);
        session()->flash('status', __('relationships.messages.declined'));
    }

    public function cancel(CancelRelationship $cancel): void
    {
        $this->relationship = $cancel->execute($this->relationship, $this->user());
        session()->flash('status', __('relationships.messages.cancelled'));
    }

    public function end(EndRelationship $end): void
    {
        $this->relationship = $end->execute($this->relationship, $this->user());
        session()->flash('status', __('relationships.messages.ended'));
    }

    public function render(): View
    {
        $user = $this->user();

        $relationship = Relationship::query()
            ->with([
                'purposeConcept.labels',
                'originatingIntent.profile.actor.user',
                'participants.actor.user',
                'events.actor.user',
                'contextBinding.context',
            ])
            ->findOrFail($this->relationship->id);

        Gate::forUser($user)->authorize('view', $relationship);
        $this->relationship = $relationship;

        $participant = RelationshipParticipant::query()
            ->where('relationship_id', $relationship->id)
            ->where('actor_id', $user->actor->id)
            ->firstOrFail();

        return view('livewire.relationships.show', [
            'participant' => $participant,
            'canRespond' => Gate::forUser($user)->allows('respond', $relationship),
            'canCancel' => Gate::forUser($user)->allows('cancel', $relationship),
            'canEnd' => Gate::forUser($user)->allows('end', $relationship),
            'context' => $relationship->contextBinding?->context,
        ]);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless(
            $user instanceof User
            && $user->actor instanceof Actor
            && $user->status === 'active'
            && $user->email_verified_at !== null
            && $user->actor->status === 'active',
            403,
        );

        return $user;
    }
}
