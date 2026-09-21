<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\SetActorProfileIntentStatus;
use App\Actions\Profile\UpdateActorProfileIntent;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Intents extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public ?int $editingIntentId = null;

    public string $kind = ProfileIntentKind::Need->value;

    public string $conceptLabel = '';

    public ?string $title = null;

    public ?string $description = null;

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

    public function mount(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->timezone = $user->timezone ?: config('app.timezone');
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

        $this->editingIntentId = $intent->id;
        $this->kind = $intent->kind->value;
        $this->conceptLabel = $intent->concept->displayLabel();
        $this->title = $intent->title;
        $this->description = $intent->description;
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

        $this->editingIntentId = null;
        $this->kind = ProfileIntentKind::Need->value;
        $this->conceptLabel = '';
        $this->title = null;
        $this->description = null;
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
        $this->timezone = $user instanceof User && $user->timezone
            ? $user->timezone
            : config('app.timezone');
        $this->recurrenceInterval = 1;
        $this->recurrenceWeekdays = [];
        $this->recurrenceDayOfMonth = null;
        $this->timeWindowStart = null;
        $this->timeWindowEnd = null;
        $this->itemVisibility = ProfileItemVisibility::Inherited->value;
        $this->resetValidation();
    }

    private function editableIntents()
    {
        return ActorProfileIntent::query()
            ->where('actor_profile_id', $this->profile->id);
    }
}
