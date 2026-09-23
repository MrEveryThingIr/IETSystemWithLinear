<?php

namespace App\Livewire\Intents;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Models\ActorProfile;
use App\IntentJourneyPreset;
use App\Models\User;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Record an intent')]
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

    public function mount(EnsureActorProfile $ensureProfile): void
    {
        $user = $this->user();
        $this->profile = $ensureProfile->execute($user);
        $this->timezone = TemporalPreferences::timezoneFor($user);
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
            ],
        );

        session()->flash('status', __('intents.created'));

        $this->redirectRoute('intents.index', ['highlight' => $intent->uuid]);
    }

    public function render(): View
    {
        $journey = $this->selectedJourney();

        return view('livewire.intents.create', [
            'journeyGroups' => [
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
            ],
            'journey' => $journey,
            'journeySubjectKinds' => $journey?->subjectKinds() ?? ProfileIntentSubjectKind::cases(),
            'subjectKinds' => ProfileIntentSubjectKind::cases(),
            'arrangementKinds' => ProfileIntentArrangementKind::cases(),
            'exchangePreferences' => ProfileIntentExchangePreference::cases(),
            'scheduleKinds' => [
                ProfileIntentScheduleKind::Once,
                ProfileIntentScheduleKind::Ongoing,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function allRules(): array
    {
        return [
            'journeyPreset' => ['required', Rule::enum(IntentJourneyPreset::class)],
            'kind' => ['required', Rule::enum(ProfileIntentKind::class)],
            'subjectKind' => ['required', Rule::enum(ProfileIntentSubjectKind::class)],
            'conceptLabel' => ['required', 'string', 'max:120'],
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
            'cashBasis' => ['nullable', Rule::in(['total', 'hour', 'day', 'week', 'month', 'year'])],
            'exchangePreference' => ['required', Rule::enum(ProfileIntentExchangePreference::class)],
            'exchangeNotes' => ['nullable', 'string', 'max:2000'],
            'scheduleKind' => ['required', Rule::in([
                ProfileIntentScheduleKind::Once->value,
                ProfileIntentScheduleKind::Ongoing->value,
            ])],
            'visibility' => ['required', Rule::in([
                ProfileItemVisibility::Private->value,
                ProfileItemVisibility::Authenticated->value,
                ProfileItemVisibility::Public->value,
            ])],
        ];
    }

    /** @return array<string,mixed> */
    private function rulesForStep(int $step): array
    {
        $all = $this->allRules();

        return match ($step) {
            1 => array_intersect_key($all, array_flip(['journeyPreset'])),
            2 => array_intersect_key($all, array_flip(['kind', 'subjectKind', 'conceptLabel', 'arrangementKind'])),
            3 => array_intersect_key($all, array_flip(['locationText', 'cashMin', 'cashMax', 'currencyCode', 'cashBasis'])),
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
}
