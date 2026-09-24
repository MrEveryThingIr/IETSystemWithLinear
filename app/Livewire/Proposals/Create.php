<?php

namespace App\Livewire\Proposals;

use App\Actions\Proposals\CreateProposal;
use App\Models\Actor;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New proposal')]
class Create extends Component
{
    #[Url(as: 'relationship')]
    public string $relationshipUuid = '';

    public string $title = '';

    public string $partyUsernames = '';

    public string $summary = '';

    public string $terms = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::forUser($this->user())->authorize('create', Proposal::class);

        if ($this->relationshipUuid !== '') {
            $relationship = $this->relationship();
            $user = $this->user();

            $this->title = $relationship->title ?? '';

            $this->partyUsernames = $relationship->participants()
                ->where('status', RelationshipParticipantStatus::Active->value)
                ->where('actor_id', '!=', $user->actor?->id)
                ->with('actor.user')
                ->get()
                ->map(fn (RelationshipParticipant $participant): ?string => $participant->actor->user?->username)
                ->filter()
                ->implode(', ');
        }
    }

    public function save(CreateProposal $create): mixed
    {
        Gate::forUser($this->user())->authorize('create', Proposal::class);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'partyUsernames' => ['required', 'string', 'max:3000'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'terms' => ['required', 'string', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'relationshipUuid' => ['nullable', 'uuid'],
        ]);

        $user = $this->user();
        $usernames = collect(preg_split('/[\s,;]+/', trim($data['partyUsernames'])) ?: [])
            ->map(fn (string $username): string => trim($username))
            ->filter()
            ->unique()
            ->values();

        abort_if($usernames->isEmpty() || $usernames->count() > 20, 422, 'Provide between one and twenty Proposal parties.');

        $actors = Actor::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereNotNull('email_verified_at')
                ->whereIn('username', $usernames->all()))
            ->with('user')
            ->get();

        abort_if(
            $actors->contains(fn (Actor $actor): bool => (int) $actor->id === (int) $user->actor?->id),
            422,
            'Do not include yourself in the party list.',
        );

        $found = $actors
            ->map(fn (Actor $actor): ?string => $actor->user?->username)
            ->filter()
            ->sort()
            ->values();

        $expected = $usernames->sort()->values();

        if ($found->all() !== $expected->all()) {
            $this->addError('partyUsernames', __('proposals.create.parties_not_found'));

            return null;
        }

        $relationship = $data['relationshipUuid'] !== ''
            ? $this->relationship()
            : null;

        $proposal = $create->execute(
            $user,
            $data['title'],
            $actors->map(fn (Actor $actor): array => [
                'actor' => $actor,
                'role' => 'party',
                'required' => true,
            ])->values()->all(),
            $data['terms'],
            $data['summary'] !== '' ? $data['summary'] : null,
            $data['notes'] !== '' ? $data['notes'] : null,
            relationship: $relationship,
        );

        return $this->redirectRoute('proposals.show', $proposal);
    }

    public function render(): View
    {
        return view('livewire.proposals.create', [
            'relationship' => $this->relationshipUuid !== '' ? $this->relationship() : null,
        ]);
    }

    private function relationship(): Relationship
    {
        $relationship = Relationship::query()
            ->where('uuid', $this->relationshipUuid)
            ->firstOrFail();

        Gate::forUser($this->user())->authorize('participate', $relationship);
        abort_unless($relationship->status === RelationshipStatus::Active, 422);

        return $relationship;
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
