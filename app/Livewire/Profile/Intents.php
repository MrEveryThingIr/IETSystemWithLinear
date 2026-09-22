<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\SetActorProfileIntentStatus;
use App\Actions\Profile\UpdateActorProfileIntent;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\ConceptLabel;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use App\Support\Localization;
use App\Support\TemporalPreferences;
use App\TimezoneMode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Intents extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public ?int $editingIntentId = null;

    public bool $editorOpen = false;

    public string $kind = ProfileIntentKind::Need->value;

    public string $conceptLabel = '';

    public ?string $title = null;

    public ?string $description = null;

    public ?string $importancePercent = null;

    public ?string $quantity = null;

    public ?string $unit = null;

    public ?string $locationText = null;

    public ?string $originText = null;

    public ?string $destinationText = null;

    public bool $roundTrip = false;

    public ?int $returnAfterDays = null;

    public string $scheduleKind = ProfileIntentScheduleKind::Once->value;

    public ?string $startsOn = null;

    public ?string $endsOn = null;

    public string $timezone = 'UTC';

    public int $recurrenceInterval = 1;

    /** @var list<int|string> */
    public array $recurrenceWeekdays = [];

    public ?int $recurrenceDayOfMonth = null;

    public ?string $timeWindowStart = null;

    public ?string $timeWindowEnd = null;

    public string $itemVisibility = ProfileItemVisibility::Inherited->value;

    public bool $timezoneAutomatic = true;

    /** @var list<string> */
    public array $activeFacets = [];

    public function mount(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->timezoneAutomatic = $user->timezone_mode === TimezoneMode::Auto;
        $this->timezone = TemporalPreferences::timezoneFor($user);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editorOpen = true;
    }

    public function toggleFacet(string $facet): void
    {
        $allowed = ['title', 'description', 'importance', 'quantity', 'location', 'route', 'timing', 'visibility'];
        abort_unless(in_array($facet, $allowed, true), 422);

        if (in_array($facet, $this->activeFacets, true)) {
            $this->activeFacets = array_values(array_filter(
                $this->activeFacets,
                fn (string $active): bool => $active !== $facet,
            ));
            $this->clearFacet($facet);

            return;
        }

        $this->activeFacets[] = $facet;
        $this->activeFacets = array_values(array_unique($this->activeFacets));
    }

    public function selectConceptSuggestion(string $label): void
    {
        $this->conceptLabel = trim($label);
    }

    private function clearFacet(string $facet): void
    {
        match ($facet) {
            'title' => $this->title = null,
            'description' => $this->description = null,
            'importance' => $this->importancePercent = null,
            'quantity' => [$this->quantity, $this->unit] = [null, null],
            'location' => $this->locationText = null,
            'route' => [
                $this->originText,
                $this->destinationText,
                $this->roundTrip,
                $this->returnAfterDays,
            ] = [null, null, false, null],
            'timing' => $this->clearTimingFacet(),
            'visibility' => $this->itemVisibility = ProfileItemVisibility::Inherited->value,
            default => null,
        };
    }

    private function clearTimingFacet(): void
    {
        $this->scheduleKind = ProfileIntentScheduleKind::Once->value;
        $this->startsOn = null;
        $this->endsOn = null;
        $this->recurrenceInterval = 1;
        $this->recurrenceWeekdays = [];
        $this->recurrenceDayOfMonth = null;
        $this->timeWindowStart = null;
        $this->timeWindowEnd = null;
    }

    public function useBrowserTimezone(string $timezone): void
    {
        if ($this->editingIntentId !== null || ! $this->timezoneAutomatic) {
            return;
        }

        if (TemporalPreferences::validTimezone($timezone)) {
            $this->timezone = $timezone;
        }
    }

    #[On('temporal-preferences-updated')]
    public function refreshTemporalPreferences(): void
    {
        if ($this->editingIntentId !== null) {
            return;
        }

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $fresh = User::query()->findOrFail($user->id);
        $this->timezoneAutomatic = $fresh->timezone_mode === TimezoneMode::Auto;
        $this->timezone = TemporalPreferences::timezoneFor($fresh);
    }

    public function save(
        CreateActorProfileIntent $createIntent,
        UpdateActorProfileIntent $updateIntent,
    ): void {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate($this->rules());
        $payload = [
            'title' => $data['title'],
            'description' => $data['description'],
            'importance_percent' => $data['importancePercent'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'location_text' => $data['locationText'],
            'origin_text' => $data['originText'],
            'destination_text' => $data['destinationText'],
            'round_trip' => $data['roundTrip'],
            'return_after_days' => $data['returnAfterDays'],
            'schedule_kind' => $data['scheduleKind'],
            'starts_on' => $data['startsOn'],
            'ends_on' => $data['endsOn'],
            'timezone' => $data['timezone'],
            'recurrence_interval' => $data['recurrenceInterval'],
            'recurrence_weekdays' => $data['recurrenceWeekdays'],
            'recurrence_day_of_month' => $data['recurrenceDayOfMonth'],
            'time_window_start' => $data['timeWindowStart'],
            'time_window_end' => $data['timeWindowEnd'],
            'visibility' => $data['itemVisibility'],
        ];

        if ($this->editingIntentId !== null) {
            $intent = $this->editableIntents()->findOrFail($this->editingIntentId);
            $updateIntent->execute($user, $intent, $payload);
            session()->flash('status', __('ui.profile.intent_updated'));
        } else {
            $createIntent->execute(
                $user,
                $this->profile,
                ProfileIntentKind::from($data['kind']),
                $data['conceptLabel'],
                $payload,
            );
            session()->flash('status', __('ui.profile.intent_created'));
        }

        $this->resetForm();
    }

    public function edit(int $intentId): void
    {
        $intent = $this->editableIntents()->with('concept.labels')->findOrFail($intentId);

        $this->editorOpen = true;
        $this->editingIntentId = $intent->id;
        $this->kind = $intent->kind->value;
        $this->conceptLabel = $intent->concept->displayLabel();
        $this->title = $intent->title;
        $this->description = $intent->description;
        $this->importancePercent = $intent->importance_percent === null
            ? null
            : (string) $intent->importance_percent;
        $this->quantity = $intent->quantity;
        $this->unit = $intent->unit;
        $this->locationText = $intent->location_text;
        $this->originText = $intent->origin_text;
        $this->destinationText = $intent->destination_text;
        $this->roundTrip = $intent->round_trip;
        $this->returnAfterDays = $intent->return_after_days;
        $this->scheduleKind = $intent->schedule_kind->value;
        $this->startsOn = $intent->starts_on?->toDateString();
        $this->endsOn = $intent->ends_on?->toDateString();
        $this->timezone = $intent->timezone ?: config('app.timezone');
        $this->recurrenceInterval = $intent->recurrence_interval;
        $this->recurrenceWeekdays = $intent->recurrence_weekdays ?? [];
        $this->recurrenceDayOfMonth = $intent->recurrence_day_of_month;
        $this->timeWindowStart = $intent->time_window_start !== null
            ? substr((string) $intent->time_window_start, 0, 5)
            : null;
        $this->timeWindowEnd = $intent->time_window_end !== null
            ? substr((string) $intent->time_window_end, 0, 5)
            : null;
        $this->itemVisibility = $intent->visibility->value;

        $this->activeFacets = array_values(array_filter([
            $intent->title !== null ? 'title' : null,
            $intent->description !== null ? 'description' : null,
            $intent->importance_percent !== null ? 'importance' : null,
            $intent->quantity !== null || $intent->unit !== null ? 'quantity' : null,
            $intent->location_text !== null ? 'location' : null,
            $intent->origin_text !== null || $intent->destination_text !== null || $intent->round_trip ? 'route' : null,
            $intent->schedule_kind !== ProfileIntentScheduleKind::Once
                || $intent->starts_on !== null
                || $intent->ends_on !== null
                || $intent->time_window_start !== null
                || $intent->time_window_end !== null
                ? 'timing'
                : null,
            $intent->visibility !== ProfileItemVisibility::Inherited ? 'visibility' : null,
        ]));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function setStatus(
        int $intentId,
        string $status,
        SetActorProfileIntentStatus $setStatus,
    ): void {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $validated = validator(
            ['status' => $status],
            ['status' => [Rule::in([
                ProfileIntentStatus::Active->value,
                ProfileIntentStatus::Paused->value,
                ProfileIntentStatus::Closed->value,
            ])]],
        )->validate();

        $intent = $this->editableIntents()->findOrFail($intentId);
        $setStatus->execute($user, $intent, ProfileIntentStatus::from($validated['status']));

        if ($this->editingIntentId === $intentId) {
            $this->resetForm();
        }

        session()->flash('status', __('ui.profile.intent_status_updated'));
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);

        return view('livewire.profile.intents', [
            'intents' => $this->editableIntents()
                ->with('concept.labels')
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
                ->latest('updated_at')
                ->get(),
            'scheduleKinds' => ProfileIntentScheduleKind::cases(),
            'visibilityOptions' => ProfileItemVisibility::cases(),
            'calendar' => TemporalPreferences::calendarFor(request()->user())->value,
            'intlLocale' => Localization::intlLocale(),
            'firstDay' => Localization::firstDayOfWeek(),
            'weekdayOrder' => TemporalPreferences::weekdayOrder(),
            'conceptSuggestions' => $this->conceptSuggestions(),
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ProfileIntentKind::class)],
            'conceptLabel' => [
                $this->editingIntentId === null ? 'required' : 'nullable',
                'string',
                'max:120',
            ],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'importancePercent' => ['nullable', 'integer', 'between:0,100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:64'],
            'locationText' => ['nullable', 'string', 'max:255'],
            'originText' => ['nullable', 'string', 'max:255'],
            'destinationText' => ['nullable', 'string', 'max:255'],
            'roundTrip' => ['boolean'],
            'returnAfterDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'scheduleKind' => ['required', Rule::enum(ProfileIntentScheduleKind::class)],
            'startsOn' => ['nullable', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'timezone' => ['required', 'timezone'],
            'recurrenceInterval' => ['required', 'integer', 'min:1', 'max:365'],
            'recurrenceWeekdays' => ['array', 'max:7'],
            'recurrenceWeekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrenceDayOfMonth' => ['nullable', 'integer', 'between:1,31'],
            'timeWindowStart' => ['nullable', 'date_format:H:i'],
            'timeWindowEnd' => ['nullable', 'date_format:H:i'],
            'itemVisibility' => ['required', Rule::enum(ProfileItemVisibility::class)],
        ];
    }

    private function resetForm(): void
    {
        $user = request()->user();

        $this->editorOpen = false;
        $this->editingIntentId = null;
        $this->kind = ProfileIntentKind::Need->value;
        $this->conceptLabel = '';
        $this->title = null;
        $this->description = null;
        $this->importancePercent = null;
        $this->quantity = null;
        $this->unit = null;
        $this->locationText = null;
        $this->originText = null;
        $this->destinationText = null;
        $this->roundTrip = false;
        $this->returnAfterDays = null;
        $this->scheduleKind = ProfileIntentScheduleKind::Once->value;
        $this->startsOn = null;
        $this->endsOn = null;
        $this->timezone = $user instanceof User
            ? TemporalPreferences::timezoneFor($user)
            : (string) config('app.timezone', 'UTC');
        $this->recurrenceInterval = 1;
        $this->recurrenceWeekdays = [];
        $this->recurrenceDayOfMonth = null;
        $this->timeWindowStart = null;
        $this->timeWindowEnd = null;
        $this->itemVisibility = ProfileItemVisibility::Inherited->value;
        $this->activeFacets = [];
        $this->resetValidation();
    }

    /** @return list<string> */
    private function conceptSuggestions(): array
    {
        $term = trim($this->conceptLabel);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $normalized = mb_strtolower($term);

        return ConceptLabel::query()
            ->where('normalized_label', 'like', '%'.$normalized.'%')
            ->whereHas('concept.vocabulary', function ($query): void {
                $query->where(function ($scope): void {
                    $scope->where('scope_type', 'platform')->where('scope_id', 0);
                })->orWhere(function ($scope): void {
                    $scope->where('scope_type', 'actor')->where('scope_id', $this->profile->actor_id);
                });
            })
            ->with('concept')
            ->limit(8)
            ->get()
            ->map(fn (ConceptLabel $label): string => $label->concept->displayLabel())
            ->unique()
            ->values()
            ->all();
    }

    /** @return Builder<ActorProfileIntent> */
    private function editableIntents(): Builder
    {
        return ActorProfileIntent::query()
            ->where('actor_profile_id', $this->profile->id);
    }
}
