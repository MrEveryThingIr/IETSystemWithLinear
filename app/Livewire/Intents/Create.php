<?php

namespace App\Livewire\Intents;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\IntentJourneyPreset;
use App\Models\ActorProfile;
use App\Models\Capability;
use App\Models\User;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use App\Support\Categories\CategoryCatalog;
use App\Support\Categories\CategoryKind;
use App\Support\Market\RateBasis;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public int $step = 1;

    public string $journeyPreset = '';

    public string $kind = ProfileIntentKind::Need->value;

    public string $subjectKind = ProfileIntentSubjectKind::Property->value;

    public string $conceptLabel = '';

    public string $arrangementKind = ProfileIntentArrangementKind::OwnershipTransfer->value;

    public string $title = '';

    public string $description = '';

    public string $locationText = '';

    public ?string $cashMin = null;

    public ?string $cashMax = null;

    public string $currencyCode = '';

    public string $cashBasis = 'total';

    public string $exchangePreference = ProfileIntentExchangePreference::DiscussLater->value;

    public string $exchangeNotes = '';

    public string $scheduleKind = ProfileIntentScheduleKind::Ongoing->value;

    public string $visibility = ProfileItemVisibility::Authenticated->value;

    public string $timezone = 'UTC';

    /** Who publishes: "actor:1", "business:3" or "group:4" (only ones the user may act for). */
    #[Url(as: 'as')]
    public string $ownerKey = '';

    /** "need" or "offer" when opened from an intention ("I need…", "I can offer…"): shows only those starts. */
    #[Url(as: 'side', except: '')]
    public string $side = '';

    public ?int $conceptId = null;

    public bool $typeInstead = false;

    public string $capabilityUuid = '';

    public bool $negotiable = true;

    public ?string $quantity = null;

    public string $unit = '';

    public function mount(EnsureActorProfile $ensureProfile): void
    {
        $user = $this->user();
        $this->profile = $ensureProfile->execute($user);
        $this->timezone = TemporalPreferences::timezoneFor($user);
        if (! $this->parties()->contains(fn (array $party): bool => $party['party']->key() === $this->ownerKey)) {
            $this->ownerKey = $this->parties()->first()['party']->key();
        }
    }

    public function chooseJourney(string $preset): void
    {
        $journey = IntentJourneyPreset::from($preset);

        $this->journeyPreset = $journey->value;
        $this->kind = $journey->kind()->value;
        $this->subjectKind = $journey->subjectKind()->value;
        $this->arrangementKind = $journey->arrangementKind()->value;
        $this->resetValidation();
    }

    public function updatedOwnerKey(): void
    {
        abort_unless($this->parties()->contains(fn (array $party): bool => $party['party']->key() === $this->ownerKey), 403);
        $this->reset('conceptId', 'capabilityUuid');
        if ($this->visibility === ProfileItemVisibility::Members->value && $this->publisher()->kind === Party::ACTOR) {
            $this->visibility = ProfileItemVisibility::Authenticated->value;
        }
    }

    /** Start an offer from one of the publisher's skills. */
    public function useCapability(string $uuid): void
    {
        $capability = Capability::query()->heldBy($this->publisher())->active()->where('uuid', $uuid)->firstOrFail();
        $this->capabilityUuid = $capability->uuid;
        $this->conceptId = (int) $capability->concept_id;
        $this->typeInstead = false;
        if (trim($this->title) === '' && filled($capability->title)) {
            $this->title = (string) $capability->title;
        }
    }

    public function next(): void
    {
        $this->validate($this->rulesForStep($this->step));

        if ($this->step === 1) {
            $this->applyJourneyPreset();
        }

        if ($this->step < 6) {
            $this->step++;
        }
    }

    public function previous(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function save(CreateActorProfileIntent $create): void
    {
        $this->validate($this->allRules());

        $publisher = $this->publisher();
        $capabilityId = $this->capabilityUuid !== ''
            ? Capability::query()->heldBy($publisher)->where('uuid', $this->capabilityUuid)->value('id')
            : null;
        $intent = $create->execute(
            $this->user(),
            $this->profile,
            ProfileIntentKind::from($this->kind),
            $this->conceptLabel,
            [
                'subject_kind' => $this->subjectKind,
                'arrangement_kind' => $this->arrangementKind,
                'exchange_preference' => $this->exchangePreference,
                'title' => $this->title,
                'description' => $this->description,
                'location_text' => $this->locationText,
                'cash_min' => $this->cashMin,
                'cash_max' => $this->cashMax,
                'currency_code' => $this->currencyCode,
                'cash_basis' => $this->cashBasis,
                'exchange_notes' => $this->exchangeNotes,
                'schedule_kind' => $this->scheduleKind,
                'timezone' => $this->timezone,
                'round_trip' => false,
                'visibility' => $this->visibility,
                'negotiable' => $this->negotiable,
                'quantity' => $this->quantity,
                'unit' => $this->unit,
            ],
            [
                'publisher' => $publisher,
                'concept_id' => $this->typeInstead ? null : $this->conceptId,
                'capability_id' => $capabilityId !== null ? (int) $capabilityId : null,
            ],
        );

        session()->flash('status', __('intents.created'));

        $this->redirectRoute('intents.index', ['highlight' => $intent->uuid]);
    }

    public function render(): View
    {
        $journey = $this->selectedJourney();

        $publisher = $this->publisher();
        $catalog = app(CategoryCatalog::class);

        return view('livewire.intents.create', [
            'parties' => $this->parties(),
            'publisher' => $publisher,
            'capabilities' => $this->kind === ProfileIntentKind::Offer->value
                ? Capability::query()->heldBy($publisher)->active()->with('concept.labels')->orderByDesc('is_primary')->orderBy('sort_order')->limit(12)->get()
                : collect(),
            'pickerKinds' => $this->pickerKinds(),
            'conceptPath' => $this->conceptId !== null && ! $this->typeInstead ? implode(' › ', array_column($catalog->path($this->conceptId, $publisher), 'label')) : '',
            'rateBases' => RateBasis::cases(),
            'journeyGroups' => $this->journeyGroups(),
            'journey' => $journey,
            'journeySubjectKinds' => $journey?->subjectKinds() ?? ProfileIntentSubjectKind::cases(),
            'subjectKinds' => ProfileIntentSubjectKind::cases(),
            'arrangementKinds' => ProfileIntentArrangementKind::cases(),
            'exchangePreferences' => ProfileIntentExchangePreference::cases(),
            'scheduleKinds' => [
                ProfileIntentScheduleKind::Once,
                ProfileIntentScheduleKind::Ongoing,
            ],
        ])->title(__('intents.create.title'));
    }

    /** @return array<string,mixed> */
    private function allRules(): array
    {
        return [
            'journeyPreset' => ['required', Rule::enum(IntentJourneyPreset::class)],
            'kind' => ['required', Rule::enum(ProfileIntentKind::class)],
            'subjectKind' => ['required', Rule::enum(ProfileIntentSubjectKind::class)],
            'conceptLabel' => [Rule::requiredIf(fn (): bool => $this->typeInstead || $this->conceptId === null), 'nullable', 'string', 'max:120'],
            'conceptId' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999999999'],
            'unit' => [Rule::requiredIf(fn (): bool => filled($this->quantity)), 'nullable', 'string', 'max:64'],
            'negotiable' => ['boolean'],
            'arrangementKind' => ['required', Rule::enum(ProfileIntentArrangementKind::class)],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'locationText' => ['nullable', 'string', 'max:255'],
            'cashMin' => ['nullable', 'numeric', 'min:0'],
            'cashMax' => ['nullable', 'numeric', 'min:0', 'gte:cashMin'],
            'currencyCode' => [
                Rule::requiredIf(fn (): bool => filled($this->cashMin) || filled($this->cashMax)),
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Za-z]{3}$/',
            ],
            'cashBasis' => ['nullable', Rule::in(RateBasis::values())],
            'exchangePreference' => ['required', Rule::enum(ProfileIntentExchangePreference::class)],
            'exchangeNotes' => ['nullable', 'string', 'max:2000'],
            'scheduleKind' => ['required', Rule::in([
                ProfileIntentScheduleKind::Once->value,
                ProfileIntentScheduleKind::Ongoing->value,
            ])],
            'visibility' => ['required', Rule::in(array_filter([
                ProfileItemVisibility::Private->value,
                ProfileItemVisibility::Authenticated->value,
                ProfileItemVisibility::Public->value,
                $this->publisher()->kind !== Party::ACTOR ? ProfileItemVisibility::Members->value : null,
            ]))],
        ];
    }

    /** @return array<string,mixed> */
    private function rulesForStep(int $step): array
    {
        $all = $this->allRules();

        return match ($step) {
            1 => array_intersect_key($all, array_flip(['journeyPreset'])),
            2 => array_intersect_key($all, array_flip(['kind', 'subjectKind', 'conceptLabel', 'conceptId', 'arrangementKind'])),
            3 => array_intersect_key($all, array_flip(['locationText', 'cashMin', 'cashMax', 'currencyCode', 'cashBasis', 'quantity', 'unit', 'negotiable'])),
            4 => array_intersect_key($all, array_flip(['exchangePreference', 'exchangeNotes'])),
            5 => array_intersect_key($all, array_flip(['title', 'description', 'scheduleKind', 'visibility'])),
            default => $all,
        };
    }

    private function applyJourneyPreset(): void
    {
        $journey = $this->selectedJourney();
        abort_unless($journey instanceof IntentJourneyPreset, 422);

        $this->kind = $journey->kind()->value;
        $this->arrangementKind = $journey->arrangementKind()->value;

        if (in_array(ProfileIntentSubjectKind::from($this->subjectKind), $journey->subjectKinds(), true) === false) {
            $this->subjectKind = $journey->subjectKind()->value;
        }
    }

    private function selectedJourney(): ?IntentJourneyPreset
    {
        return IntentJourneyPreset::tryFrom($this->journeyPreset);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** @return list<string> category kinds that fit what is needed or offered */
    private function pickerKinds(): array
    {
        return match ($this->subjectKind) {
            ProfileIntentSubjectKind::Service->value => [CategoryKind::Skill->value, CategoryKind::Service->value],
            ProfileIntentSubjectKind::Good->value => [CategoryKind::Product->value],
            ProfileIntentSubjectKind::Property->value => [CategoryKind::Property->value],
            default => [],
        };
    }

    /** @return Collection<int, array{party: Party, label: string}> */
    private function parties(): Collection
    {
        return RequestMemo::remember('intent-create-parties:'.$this->user()->id, fn () => app(PartyAuthority::class)->partiesFor($this->user()));
    }

    private function publisher(): Party
    {
        $entry = $this->parties()->first(fn (array $party): bool => $party['party']->key() === $this->ownerKey);
        abort_unless(is_array($entry), 403);

        return $entry['party'];
    }

    /** @return array<string, list<IntentJourneyPreset>> */
    private function journeyGroups(): array
    {
        $groups = [
            'exchange' => [
                IntentJourneyPreset::Buy,
                IntentJourneyPreset::Sell,
                IntentJourneyPreset::Rent,
                IntentJourneyPreset::RentOut,
            ],
            'services_work' => [
                IntentJourneyPreset::NeedService,
                IntentJourneyPreset::OfferService,
                IntentJourneyPreset::Hire,
                IntentJourneyPreset::FindWork,
            ],
            'capital_collaboration' => [
                IntentJourneyPreset::SeekCapital,
                IntentJourneyPreset::OfferCapital,
                IntentJourneyPreset::SeekCollaboration,
                IntentJourneyPreset::OfferCollaboration,
            ],
            'other' => [
                IntentJourneyPreset::Other,
            ],
        ];
        $side = in_array($this->side, [ProfileIntentKind::Need->value, ProfileIntentKind::Offer->value], true) ? $this->side : null;
        if ($side === null) {
            return $groups;
        }

        return array_filter(
            array_map(fn (array $presets): array => array_values(array_filter($presets, fn (IntentJourneyPreset $preset): bool => $preset->kind()->value === $side)), $groups),
            fn (array $presets): bool => $presets !== [],
        );
    }
}
