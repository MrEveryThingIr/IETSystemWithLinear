<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Contracts\CreateDirectContract;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\ProposalStatus;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('New contract')]
class Create extends Component
{
    #[Url(as: 'proposal')]
    public string $proposalUuid = '';

    #[Url(as: 'relationship')]
    public string $relationshipUuid = '';

    public string $title = '';

    public string $creatorRole = 'party';

    public string $partyLines = '';

    public string $summary = '';

    public string $terms = '';

    public string $notes = '';

    public string $effectiveAt = '';

    public string $timezone = 'UTC';

    public function mount(): void
    {
        Gate::forUser($this->user())->authorize('create', Contract::class);

        $user = $this->user();
        $this->timezone = $user->timezone ?: (string) config('app.timezone', 'UTC');
        $this->effectiveAt = CarbonImmutable::now($this->timezone)->format('Y-m-d\TH:i');

        if ($this->proposalUuid !== '') {
            $proposal = $this->proposal();
            $this->title = $proposal->title;

            return;
        }

        if ($this->relationshipUuid !== '') {
            $relationship = $this->relationship();
            $this->title = $relationship->title ?? '';

            $currentParticipant = $relationship->participants()
                ->where('actor_id', $user->actor?->id)
                ->first();

            if ($currentParticipant instanceof RelationshipParticipant) {
                $this->creatorRole = $currentParticipant->role;
            }

            $this->partyLines = $relationship->participants()
                ->where('status', RelationshipParticipantStatus::Active->value)
                ->where('actor_id', '!=', $user->actor?->id)
                ->with('actor.user')
                ->get()
                ->map(function (RelationshipParticipant $participant): ?string {
                    $username = $participant->actor->user?->username;

                    return $username !== null
                        ? $username.' | '.$participant->role
                        : null;
                })
                ->filter()
                ->implode("\n");
        }
    }

    public function save(
        CreateDirectContract $createDirect,
        CreateContractFromProposal $createFromProposal,
    ): mixed {
        $this->validate([
            'effectiveAt' => ['required', 'string', 'max:40'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $effective = $this->effectiveInstant();

        if ($this->proposalUuid !== '') {
            $contract = $createFromProposal->execute(
                $this->proposal(),
                $this->user(),
                $effective,
                $this->timezone,
            );

            return $this->redirectRoute('contracts.show', $contract);
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'creatorRole' => ['required', 'string', 'max:80'],
            'partyLines' => ['required', 'string', 'max:4000'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'terms' => ['required', 'string', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $partySpecs = $this->partySpecs($data['partyLines']);

        $contract = $createDirect->execute(
            $this->user(),
            $data['title'],
            $partySpecs,
            $data['terms'],
            $effective,
            $this->timezone,
            $data['summary'] !== '' ? $data['summary'] : null,
            $data['notes'] !== '' ? $data['notes'] : null,
            $data['creatorRole'],
            $this->relationshipUuid !== '' ? $this->relationship() : null,
        );

        return $this->redirectRoute('contracts.show', $contract);
    }

    public function render(): View
    {
        return view('livewire.contracts.create', [
            'proposal' => $this->proposalUuid !== '' ? $this->proposal() : null,
            'relationship' => $this->relationshipUuid !== '' ? $this->relationship() : null,
        ]);
    }

    /**
     * @return list<array{actor: Actor, role: string, required: bool}>
     */
    private function partySpecs(string $raw): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($raw)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        abort_if($lines->isEmpty() || $lines->count() > 19, 422, 'Provide between one and nineteen other Contract parties.');

        $parsed = $lines->map(function (string $line): array {
            [$username, $role] = array_pad(array_map('trim', explode('|', $line, 2)), 2, 'party');

            return [
                'username' => $username,
                'role' => $role !== '' ? $role : 'party',
            ];
        });

        $usernames = $parsed->pluck('username')->filter()->unique()->values();
        abort_unless($usernames->count() === $parsed->count(), 422, 'Contract party usernames must be unique.');

        $actors = Actor::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereNotNull('email_verified_at')
                ->whereIn('username', $usernames->all()))
            ->with('user')
            ->get()
            ->keyBy(fn (Actor $actor): string => (string) $actor->user?->username);

        if ($actors->count() !== $usernames->count()) {
            $this->addError('partyLines', __('contracts.create.parties_not_found'));
            abort(422, __('contracts.create.parties_not_found'));
        }

        return $parsed
            ->map(function (array $spec) use ($actors): array {
                $actor = $actors->get($spec['username']);
                abort_unless($actor instanceof Actor, 422);

                return [
                    'actor' => $actor,
                    'role' => $spec['role'],
                    'required' => true,
                ];
            })
            ->all();
    }

    private function effectiveInstant(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->effectiveAt, $this->timezone)->utc();
        } catch (Throwable) {
            $this->addError('effectiveAt', __('validation.date', ['attribute' => __('contracts.create.effective_at')]));
            abort(422, 'Invalid Contract effective time.');
        }
    }

    private function proposal(): Proposal
    {
        $proposal = Proposal::query()
            ->where('uuid', $this->proposalUuid)
            ->firstOrFail();

        Gate::forUser($this->user())->authorize('view', $proposal);
        abort_unless($proposal->status === ProposalStatus::Accepted, 422);

        return $proposal;
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
