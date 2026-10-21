<?php

namespace App\Livewire\Profile;

use App\Actions\Capabilities\CopyLegacyProfessions;
use App\Actions\Capabilities\SaveCapability;
use App\Models\Actor;
use App\Models\BusinessMembership;
use App\Models\Capability;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "What I can do": capabilities of the person, or of a business or group they
 * manage. Ability only — prices and availability are set on Offers.
 */
#[Layout('layouts.app')]
class Capabilities extends Component
{
    #[Url(as: 'for')]
    public string $ownerKey = '';

    public bool $showArchived = false;

    public bool $formOpen = false;

    public ?string $editing = null;

    public ?int $conceptId = null;

    public string $title = '';

    public string $description = '';

    public string $level = '';

    public string $yearsExperience = '';

    public ?int $confidence = null;

    public string $visibility = 'members';

    public bool $isPrimary = false;

    public string $membershipId = '';

    public function mount(CopyLegacyProfessions $legacy): void
    {
        $legacy->ensureCopied();
        $parties = $this->parties();
        if ($this->ownerKey === '' || ! $parties->contains(fn (array $party): bool => $party['party']->key() === $this->ownerKey)) {
            $this->ownerKey = $parties->first()['party']->key();
        }
    }

    public function updatedOwnerKey(): void
    {
        abort_unless($this->parties()->contains(fn (array $party): bool => $party['party']->key() === $this->ownerKey), 403);
        $this->cancel();
    }

    public function add(): void
    {
        $this->cancel();
        $this->formOpen = true;
    }

    public function edit(string $uuid): void
    {
        $capability = $this->find($uuid);
        $this->formOpen = true;
        $this->editing = $capability->uuid;
        $this->conceptId = (int) $capability->concept_id;
        $this->title = (string) $capability->title;
        $this->description = (string) $capability->description;
        $this->level = (string) $capability->level;
        $this->yearsExperience = $capability->years_experience !== null ? (string) $capability->years_experience : '';
        $this->confidence = $capability->self_confidence_percent;
        $this->visibility = $capability->visibility;
        $this->isPrimary = $capability->is_primary;
        $this->membershipId = $capability->business_membership_id !== null ? (string) $capability->business_membership_id : '';
    }

    public function cancel(): void
    {
        $this->reset('formOpen', 'editing', 'conceptId', 'title', 'description', 'level', 'yearsExperience', 'confidence', 'isPrimary', 'membershipId');
        $this->visibility = 'members';
        $this->resetErrorBag();
    }

    public function save(SaveCapability $save): void
    {
        $input = [
            'concept_id' => $this->conceptId,
            'title' => $this->title,
            'description' => $this->description,
            'level' => $this->level !== '' ? $this->level : null,
            'years_experience' => $this->yearsExperience !== '' ? $this->yearsExperience : null,
            'self_confidence_percent' => $this->confidence,
            'visibility' => $this->visibility,
            'is_primary' => $this->isPrimary,
            'business_membership_id' => $this->membershipId !== '' ? (int) $this->membershipId : null,
        ];

        if ($this->editing !== null) {
            $save->update($this->user(), $this->find($this->editing), $input);
        } else {
            $save->create($this->user(), $this->ownerParty(), $input);
        }

        $this->cancel();
        session()->flash('status', __('capabilities.saved'));
    }

    public function archive(string $uuid, SaveCapability $save): void
    {
        $save->archive($this->user(), $this->find($uuid));
        $this->cancel();
    }

    public function restore(string $uuid, SaveCapability $save): void
    {
        $save->restore($this->user(), $this->find($uuid));
    }

    public function render(CategoryCatalog $catalog): View
    {
        $owner = $this->ownerParty();
        $capabilities = Capability::query()
            ->heldBy($owner)
            ->when(! $this->showArchived, fn ($query) => $query->active())
            ->with('businessMembership.business')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get();

        return view('livewire.profile.capabilities', [
            'parties' => $this->parties(),
            'owner' => $owner,
            'capabilities' => $capabilities,
            'labels' => $catalog->labels($capabilities->pluck('concept_id')->map(fn ($id): int => (int) $id)->all()),
            'areas' => $this->areas($capabilities->pluck('concept_id')->all(), $catalog),
            'memberships' => $owner->kind === Party::ACTOR
                ? BusinessMembership::query()->where('actor_id', $owner->id)->where('status', 'active')->with('business')->get()
                : collect(),
            'archivedCount' => Capability::query()->heldBy($owner)->where('status', Capability::STATUS_ARCHIVED)->count(),
        ])->title(__('capabilities.title'));
    }

    /** Area (top category) of each shared category, in one query. */
    private function areas(array $conceptIds, CategoryCatalog $catalog): array
    {
        $schemeId = $catalog->sharedSchemeId();
        if ($conceptIds === [] || $schemeId === null) {
            return [];
        }

        $roots = DB::table('concept_closure as c')
            ->where('c.scheme_id', $schemeId)
            ->whereIn('c.descendant_concept_id', $conceptIds)
            ->whereNotIn('c.ancestor_concept_id', DB::table('concept_hierarchy_edges')->where('scheme_id', $schemeId)->select('child_concept_id'))
            ->get(['c.descendant_concept_id', 'c.ancestor_concept_id', 'c.min_depth'])
            ->filter(fn ($row): bool => (int) $row->min_depth > 0);
        $labels = $catalog->labels($roots->pluck('ancestor_concept_id')->map(fn ($id): int => (int) $id)->unique()->values()->all());

        return $roots->mapWithKeys(fn ($row): array => [(int) $row->descendant_concept_id => $labels[(int) $row->ancestor_concept_id] ?? ''])->all();
    }

    /** @return Collection<int, array{party: Party, label: string}> */
    private function parties(): Collection
    {
        return RequestMemo::remember('capabilities:parties:'.$this->user()->id, fn () => app(PartyAuthority::class)->partiesFor($this->user()));
    }

    private function ownerParty(): Party
    {
        $entry = $this->parties()->first(fn (array $party): bool => $party['party']->key() === $this->ownerKey);
        abort_unless(is_array($entry), 403);

        return $entry['party'];
    }

    private function find(string $uuid): Capability
    {
        return Capability::query()->heldBy($this->ownerParty())->where('uuid', $uuid)->firstOrFail();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && RequestMemo::user($user)?->actor instanceof Actor, 403);

        return $user;
    }
}
