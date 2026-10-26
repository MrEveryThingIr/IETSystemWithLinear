<?php

namespace App\Livewire\Relationships;

use App\Actions\Conversations\PostContextMessage;
use App\Actions\Relationships\CreateRelationship;
use App\ConceptStatus;
use App\DomainJourneyKind;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\DomainBlueprintVersion;
use App\Models\Relationship;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\RelationshipStatus;
use App\Support\Deals\DealOrigin;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use Illuminate\Support\Str;
use App\Support\DomainBlueprintCatalog;
use App\Support\IntentMatchFinder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Respond to need or offer')]
class Create extends Component
{
    #[Url(as: 'intent')]
    public string $intentUuid = '';

    #[Url(as: 'blueprint')]
    public string $blueprintSlug = '';

    #[Url(as: 'match')]
    public string $matchIntentUuid = '';

    public string $title = '';

    public string $participantUsername = '';

    public string $creatorRole = 'participant';

    public string $participantRole = 'participant';

    public string $purposeSearch = '';

    public string $purposeConceptId = '';

    public string $purposeLabel = '';

    public string $originIntentTitle = '';

    public string $originParticipantLabel = '';

    /** The opening message: it introduces the person and carries the first question. */
    public string $message = '';

    /** Who is speaking: '' for the person, or business:ID / group:ID they are authorized for. */
    public string $actingAs = '';

    public string $sendUuid = '';

    public bool $purposeLocked = false;

    public bool $participantLocked = false;

    public function mount(): mixed
    {
        Gate::authorize('create', Relationship::class);

        $queryIntent = trim((string) request()->query('intent', ''));
        if ($queryIntent !== '') {
            $this->intentUuid = $queryIntent;
        }

        if ($this->intentUuid === '') {
            session()->flash('status', __('deals.start_from_market'));

            return $this->redirectRoute('intents.index');
        }

        $queryBlueprint = trim((string) request()->query('blueprint', ''));
        if ($queryBlueprint !== '') {
            $this->blueprintSlug = $queryBlueprint;
        }

        if ($this->blueprintSlug !== '') {
            $this->applyBlueprintDefaults();
        }

        $this->loadOriginIntent();
        $this->sendUuid = (string) Str::uuid();
        $this->inferRoles();

        if ($this->matchIntentUuid !== '') {
            $this->loadMatchedIntent();
        } else {
            $origin = ActorProfileIntent::query()
                ->where('uuid', $this->intentUuid)
                ->firstOrFail();

            if ((int) $origin->profile->actor_id === (int) $this->user()->actor?->id) {
                return $this->redirectRoute('intents.matches', $origin);
            }
        }

        return null;
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
            'participantUsername' => ['nullable', 'string', 'max:120'],
            'creatorRole' => ['required', 'string', 'max:80'],
            'participantRole' => ['required', 'string', 'max:80'],
            'purposeConceptId' => ['required', 'integer'],
            'intentUuid' => ['nullable', 'uuid'],
            'matchIntentUuid' => ['nullable', 'uuid'],
            'blueprintSlug' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:4000'],
            'actingAs' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $this->user();
        $purpose = Concept::query()
            ->where('status', ConceptStatus::Active->value)
            ->findOrFail((int) $data['purposeConceptId']);

        $originIntent = null;
        if ($data['intentUuid'] !== '') {
            $originIntent = ActorProfileIntent::query()
                ->with(['profile.actor.user', 'concept'])
                ->where('uuid', $data['intentUuid'])
                ->firstOrFail();

            Gate::forUser($user)->authorize('view', $originIntent);
            abort_unless($originIntent->status === ProfileIntentStatus::Active, 422);
        }

        $matchedIntent = null;
        $participant = null;

        if ($data['matchIntentUuid'] !== '') {
            abort_unless($originIntent instanceof ActorProfileIntent, 422, 'A matched Intent requires an originating Intent.');
            Gate::forUser($user)->authorize('update', $originIntent);

            $matchedIntent = ActorProfileIntent::query()
                ->with(['profile.actor.user', 'concept'])
                ->where('uuid', $data['matchIntentUuid'])
                ->firstOrFail();

            abort_unless(
                app(IntentMatchFinder::class)->match($user, $originIntent, $matchedIntent) !== null,
                422,
                'The selected match is no longer compatible.',
            );

            $participant = $matchedIntent->profile->actor;
        } elseif ($originIntent instanceof ActorProfileIntent
            && (int) $originIntent->profile->actor_id !== (int) $user->actor?->id) {
            $participant = $originIntent->profile->actor;
        } else {
            $participantUsername = trim((string) $data['participantUsername']);

            if ($participantUsername !== '') {
                $participant = Actor::query()
                    ->where('status', 'active')
                    ->whereHas('user', fn (Builder $query) => $query
                        ->where('status', 'active')
                        ->whereNotNull('email_verified_at')
                        ->where('username', $participantUsername))
                    ->with('user')
                    ->first();
            }

            $this->addErrorIf(
                ! $participant instanceof Actor,
                'participantUsername',
                __('relationships.create.participant_not_found'),
            );

            if (! $participant instanceof Actor) {
                return null;
            }
        }

        abort_if((int) $participant->id === (int) $user->actor?->id, 422, 'A Relationship requires another Actor.');

        $speaker = $this->speaker($user);
        if ($existing = $this->existingDeal($user, $originIntent, $participant)) {
            $this->say($existing, $user);

            return $this->redirectRoute('relationships.show', $existing);
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
            matchedIntent: $matchedIntent,
        );

        $meta = $relationship->metadata ?? [];
        if ($speaker !== null) {
            $meta['responder_party'] = $speaker->key();
        }
        if (trim($this->message) !== '') {
            // Nothing is posted into the conversation before the other person consents; the introduction travels with the invitation.
            $meta['intro'] = ['text' => trim($this->message), 'at' => now()->toIso8601String()];
        }
        if ($meta !== ($relationship->metadata ?? [])) {
            $relationship->update(['metadata' => $meta]);
        }

        return $this->redirectRoute('relationships.show', $relationship);
    }

    public function render(): View
    {
        Gate::authorize('create', Relationship::class);

        return view('livewire.relationships.create', [
            'originCard' => $this->originCard(),
            'parties' => app(PartyAuthority::class)->partiesFor($this->user()),
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

    /** Descriptive labels only; they never decide who may sign. */
    private function inferRoles(): void
    {
        if ($this->blueprintSlug !== '' || ! $this->participantLocked) {
            return;
        }

        $origin = ActorProfileIntent::query()->where('uuid', $this->intentUuid)->first();
        if (! $origin instanceof ActorProfileIntent) {
            return;
        }

        $mine = (int) $origin->profile->actor_id === (int) $this->user()->actor?->id;
        $publisherRole = $origin->kind === ProfileIntentKind::Offer ? 'provider' : 'client';
        $otherRole = $publisherRole === 'provider' ? 'client' : 'provider';
        $this->creatorRole = (string) __('deals.roles.'.($mine ? $publisherRole : $otherRole));
        $this->participantRole = (string) __('deals.roles.'.($mine ? $otherRole : $publisherRole));
    }

    /** @return array<string, mixed>|null */
    private function originCard(): ?array
    {
        $intent = ActorProfileIntent::query()->with('publishedVersion')->where('uuid', $this->intentUuid)->first();

        return $intent instanceof ActorProfileIntent ? DealOrigin::card($intent, null, $this->user()) : null;
    }

    private function speaker(User $user): ?Party
    {
        if ($this->actingAs === '') {
            return null;
        }

        $match = app(PartyAuthority::class)->partiesFor($user)->first(fn (array $row): bool => $row['party']->key() === $this->actingAs);
        abort_unless($match !== null, 403);

        return $match['party']->kind === Party::ACTOR ? null : $match['party'];
    }

    private function existingDeal(User $user, ?ActorProfileIntent $origin, Actor $other): ?Relationship
    {
        if (! $origin instanceof ActorProfileIntent) {
            return null;
        }

        return Relationship::query()
            ->where('originating_intent_id', $origin->id)
            ->whereIn('status', [RelationshipStatus::Proposed->value, RelationshipStatus::Active->value])
            ->whereHas('participants', fn (Builder $q) => $q->where('actor_id', $user->actor->id)->where('status', '!=', 'declined'))
            ->whereHas('participants', fn (Builder $q) => $q->where('actor_id', $other->id)->where('status', '!=', 'declined'))
            ->first();
    }

    /** Into an already open conversation a message is ordinary talk; while the invitation is pending the first introduction stands. */
    private function say(Relationship $relationship, User $user): void
    {
        $text = trim($this->message);
        $context = $relationship->contextBinding?->context ?? $relationship->load('contextBinding.context')->contextBinding?->context;
        if ($text === '' || $context === null || $relationship->status !== RelationshipStatus::Active) {
            return;
        }

        try {
            app(PostContextMessage::class)->execute($context, $user, $text, clientUuid: $this->sendUuid);
        } catch (\Throwable $e) {
            report($e);
        }
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
            $this->originParticipantLabel = $this->participantLabel($intent);
            $this->participantLocked = true;
        }
    }

    private function loadMatchedIntent(): void
    {
        abort_unless($this->intentUuid !== '', 422, 'A match requires a source Intent.');

        $user = $this->user();
        $source = ActorProfileIntent::query()
            ->with(['profile.actor.user', 'concept'])
            ->where('uuid', $this->intentUuid)
            ->firstOrFail();

        Gate::forUser($user)->authorize('update', $source);
        abort_unless($source->status === ProfileIntentStatus::Active, 422);

        $candidate = ActorProfileIntent::query()
            ->with(['profile.actor.user', 'concept'])
            ->where('uuid', $this->matchIntentUuid)
            ->firstOrFail();

        abort_unless(
            app(IntentMatchFinder::class)->match($user, $source, $candidate) !== null,
            422,
            'The selected match is no longer compatible.',
        );

        $this->originParticipantLabel = $this->participantLabel($candidate);
        $this->participantLocked = true;
    }

    private function participantLabel(ActorProfileIntent $intent): string
    {
        $user = $this->user();

        if (Gate::forUser($user)->allows('view', $intent->profile)) {
            return $intent->profile->display_name
                ?: ($intent->profile->actor->user->username ?? __('intents.matches.participant'));
        }

        return __('intents.matches.private_participant');
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
