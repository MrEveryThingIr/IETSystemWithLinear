<?php

namespace App\Livewire\Relationships;

use App\Actions\Relationships\CreateRelationship;
use App\ConceptStatus;
use App\DomainJourneyKind;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\DomainBlueprintVersion;
use App\Models\Relationship;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Support\DomainBlueprintCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Start relationship')]
class Create extends Component
{
    #[Url(as: 'intent')]
    public string $intentUuid = '';

    #[Url(as: 'blueprint')]
    public string $blueprintSlug = '';

    public string $title = '';

    public string $participantUsername = '';

    public string $creatorRole = 'participant';

    public string $participantRole = 'participant';

    public string $purposeSearch = '';

    public string $purposeConceptId = '';

    public string $purposeLabel = '';

    public string $originIntentTitle = '';

    public string $originParticipantLabel = '';

    public bool $purposeLocked = false;

    public bool $participantLocked = false;

    public function mount(): void
    {
        Gate::authorize('create', Relationship::class);

        $queryIntent = trim((string) request()->query('intent', ''));
        if ($queryIntent !== '') {
            $this->intentUuid = $queryIntent;
        }

        $queryBlueprint = trim((string) request()->query('blueprint', ''));
        if ($queryBlueprint !== '') {
            $this->blueprintSlug = $queryBlueprint;
        }

        if ($this->blueprintSlug !== '') {
            $this->applyBlueprintDefaults();
        }

        if ($this->intentUuid !== '') {
            $this->loadOriginIntent();
        }
    }

    public function selectPurpose(int $conceptId): void
    {
        abort_if($this->purposeLocked, 422);

        $concept = Concept::query()
            ->with('labels')
            ->where('status', ConceptStatus::Active->value)
            ->findOrFail($conceptId);

        $this->purposeConceptId = (string) $concept->id;
        $this->purposeLabel = $concept->displayLabel();
    }

    public function clearPurpose(): void
    {
        abort_if($this->purposeLocked, 422);

        $this->reset('purposeConceptId', 'purposeLabel');
    }

    public function save(CreateRelationship $create): mixed
    {
        Gate::authorize('create', Relationship::class);

        $data = $this->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'participantUsername' => ['required', 'string', 'max:120'],
            'creatorRole' => ['required', 'string', 'max:80'],
            'participantRole' => ['required', 'string', 'max:80'],
            'purposeConceptId' => ['required', 'integer'],
            'intentUuid' => ['nullable', 'uuid'],
            'blueprintSlug' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $this->user();
        $purpose = Concept::query()
            ->where('status', ConceptStatus::Active->value)
            ->findOrFail((int) $data['purposeConceptId']);

        $participant = Actor::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereNotNull('email_verified_at')
                ->where('username', $data['participantUsername']))
            ->with('user')
            ->first();

        $this->addErrorIf(
            ! $participant instanceof Actor,
            'participantUsername',
            __('relationships.create.participant_not_found'),
        );

        if (! $participant instanceof Actor) {
            return null;
        }

        abort_if((int) $participant->id === (int) $user->actor?->id, 422, 'A Relationship requires another Actor.');

        $originIntent = null;
        if ($data['intentUuid'] !== '') {
            $originIntent = ActorProfileIntent::query()
                ->with(['profile.actor.user', 'concept'])
                ->where('uuid', $data['intentUuid'])
                ->firstOrFail();

            Gate::forUser($user)->authorize('view', $originIntent);
            abort_unless($originIntent->status === ProfileIntentStatus::Active, 422);
        }

        $relationship = $create->execute(
            $user,
            $purpose,
            $data['creatorRole'],
            [[
                'actor' => $participant,
                'role' => $data['participantRole'],
            ]],
            $originIntent,
            $data['title'] !== '' ? $data['title'] : null,
            $this->blueprintVersion(),
        );

        return $this->redirectRoute('relationships.show', $relationship);
    }

    public function render(): View
    {
        Gate::authorize('create', Relationship::class);

        return view('livewire.relationships.create', [
            'purposeOptions' => $this->purposeOptions(),
            'blueprintVersion' => $this->blueprintVersion(),
        ]);
    }

    /** @return Collection<int, Concept> */
    private function purposeOptions(): Collection
    {
        if ($this->purposeLocked) {
            return collect();
        }

        $search = trim($this->purposeSearch);

        return Concept::query()
            ->where('status', ConceptStatus::Active->value)
            ->with('labels')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $scope) use ($search): void {
                    $scope->where('slug', 'like', '%'.$search.'%')
                        ->orWhereHas('labels', fn (Builder $labels) => $labels->where('label', 'like', '%'.$search.'%'));
                });
            })
            ->latest('id')
            ->limit(30)
            ->get()
            ->sortBy(fn (Concept $concept): string => mb_strtolower($concept->displayLabel()))
            ->values();
    }

    private function applyBlueprintDefaults(): void
    {
        $version = $this->blueprintVersion();
        abort_unless($version instanceof DomainBlueprintVersion, 422);

        $this->creatorRole = (string) ($version->terminology['creator_role'] ?? $this->creatorRole);
        $this->participantRole = (string) ($version->terminology['participant_role'] ?? $this->participantRole);

        if ($this->purposeSearch === '') {
            $this->purposeSearch = (string) ($version->guided_entry['purpose_hint'] ?? '');
        }
    }

    private function blueprintVersion(): ?DomainBlueprintVersion
    {
        if ($this->blueprintSlug === '') {
            return null;
        }

        return app(DomainBlueprintCatalog::class)->version(
            $this->blueprintSlug,
            DomainJourneyKind::Relationship,
        );
    }

    private function loadOriginIntent(): void
    {
        $user = $this->user();

        $intent = ActorProfileIntent::query()
            ->with(['profile.actor.user', 'concept.labels'])
            ->where('uuid', $this->intentUuid)
            ->firstOrFail();

        Gate::forUser($user)->authorize('view', $intent);
        abort_unless($intent->status === ProfileIntentStatus::Active, 422);

        $purpose = $intent->concept->canonical();
        $purpose->loadMissing('labels');

        $this->purposeConceptId = (string) $purpose->id;
        $this->purposeLabel = $purpose->displayLabel();
        $this->purposeLocked = true;
        $this->originIntentTitle = $intent->title ?: $intent->concept->displayLabel();

        $owner = $intent->profile->actor;
        if ((int) $owner->id !== (int) $user->actor?->id) {
            abort_unless($owner->user instanceof User, 422);

            $this->participantUsername = $owner->user->username;
            $this->originParticipantLabel = $owner->user->username;
            $this->participantLocked = true;
        }
    }

    private function addErrorIf(bool $condition, string $key, string $message): void
    {
        if ($condition) {
            $this->addError($key, $message);
        }
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
