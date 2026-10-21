<?php

namespace App\Livewire\Planner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ConfigurePlanReadiness;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ManagePlannerLabels;
use App\Actions\Planner\Meetings\MeetingScheduler;
use App\ContextKind;
use App\DomainJourneyKind;
use App\Models\Actor;
use App\Models\Business;
use App\Models\Context;
use App\Models\DomainBlueprintVersion;
use App\Models\Planner\PlannerLabel;
use App\Models\Relationship;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use App\Support\DomainBlueprintCatalog;
use App\Support\MonetaryUnitCatalog;
use App\Support\Planner\PlanKinds;
use App\Support\Planner\PlannerHorizons;
use App\Support\TemporalCalendar;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * New plan, as a short wizard: what → when → (who, for meetings) → details
 * → review. Every field keeps working without the wizard (save() validates
 * everything), so links from the calendar can prefill date and time.
 */
#[Layout('layouts.app')]
#[Title('New plan')]
class Create extends Component
{
    /** @var list<string> */
    public const STEPS = ['what', 'when', 'who', 'details', 'review'];

    public string $step = 'what';

    public string $kind = 'event';

    /** 'visual' (timeline) or 'exact' (date and time fields). */
    public string $timeMode = 'visual';

    /** @var list<string> days chosen in the visual picker (Y-m-d) */
    public array $selectedDays = [];

    public string $labelUuid = '';

    /** Never let me forget: reminders also by email and on this person's devices, kept on Today until done. */
    public bool $important = false;

    public string $color = '';

    public string $newLabelName = '';

    public string $location = '';

    /** @var list<string> meeting options as local "Y-m-d H:i" */
    public array $meetingSlots = [];

    /** @var list<array{username?: string, name?: string, email?: string}> */
    public array $invitees = [];

    public string $inviteUsername = '';

    public string $guestName = '';

    public string $guestEmail = '';

    #[Url(as: 'context')]
    public string $contextUuid = '';

    #[Url(as: 'blueprint')]
    public string $blueprintSlug = '';

    public string $title = '';

    public string $description = '';

    public string $timezone = '';

    public string $frequency = 'once';

    public string $startsOn = '';

    public string $startTime = '09:00';

    public int $durationMinutes = 60;

    public int $interval = 1;

    /** @var list<int> */
    public array $weekdays = [];

    public string $selectedDates = '';

    public string $endsOn = '';

    public string $occurrenceLimit = '';

    public int $windowBeforeMinutes = 15;

    public int $windowAfterMinutes = 15;

    public string $reminderOffsets = '15';

    /** @var list<array{title: string, required: bool}> */
    public array $prerequisites = [];

    /** @var list<array{label: string, amount: string, unit_code: string}> */
    public array $expenseEstimates = [];

    public function mount(EnsurePersonalContext $personal): void
    {
        $user = $this->user();
        $this->timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($this->timezone);

        $this->startsOn = $now->format('Y-m-d');
        $this->startTime = $now->addHour()->format('H:00');
        $this->weekdays = [$now->isoWeekday()];

        $requestedDate = trim((string) request()->query('date', ''));
        $requestedDateValue = $this->canonicalDate($requestedDate);

        if ($requestedDateValue instanceof CarbonImmutable) {
            $this->startsOn = $requestedDateValue->toDateString();
            $this->weekdays = [$requestedDateValue->isoWeekday()];
        }

        $queryBlueprint = trim((string) request()->query('blueprint', ''));
        if ($queryBlueprint !== '') {
            $this->blueprintSlug = $queryBlueprint;
        }

        if ($this->blueprintSlug !== '') {
            $this->applyBlueprintDefaults();
        }

        $requestedTime = trim((string) request()->query('time', ''));
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $requestedTime) === 1) {
            $this->startTime = $requestedTime;
        }

        $requestedDurationRaw = trim((string) request()->query('duration', ''));
        if (preg_match('/^\d+$/', $requestedDurationRaw) === 1) {
            $requestedDuration = (int) $requestedDurationRaw;

            if ($requestedDuration >= 1 && $requestedDuration <= 10080) {
                $this->durationMinutes = $requestedDuration;
            }
        }

        $requestedKind = trim((string) request()->query('kind', ''));
        if (PlanKinds::valid($requestedKind)) {
            $this->chooseKind($requestedKind);
        }
        $this->selectedDays = [$this->startsOn];

        if ($this->contextUuid === '') {
            $this->contextUuid = $personal->execute($user)->uuid;
        }

        $context = $this->context();
        Gate::forUser($user)->authorize('interactContent', $context);
    }

    public function chooseKind(string $kind): void
    {
        if (! PlanKinds::valid($kind)) {
            return;
        }
        $this->kind = $kind;
        $defaults = PlanKinds::KINDS[$kind];
        $this->durationMinutes = $defaults['duration'];
        $this->frequency = $defaults['frequency'];
        $this->reminderOffsets = $defaults['reminder'] > 0 ? (string) $defaults['reminder'] : '';
        $this->important = isset(PlanKinds::IMPORTANT_DEFAULTS[$kind]);
        if ($this->important) {
            $this->reminderOffsets = implode(', ', PlanKinds::IMPORTANT_DEFAULTS[$kind]);
        }
    }

    public function goTo(string $step): void
    {
        if (! in_array($step, $this->steps(), true)) {
            return;
        }
        $target = array_search($step, $this->steps(), true);
        $current = array_search($this->step, $this->steps(), true);
        if ($target > $current) {
            foreach (array_slice($this->steps(), (int) $current, (int) $target - (int) $current) as $passing) {
                $this->validateStep($passing);
            }
        }
        $this->step = $step;
    }

    public function next(): void
    {
        $steps = $this->steps();
        $index = (int) array_search($this->step, $steps, true);
        $this->validateStep($this->step);
        $this->step = $steps[min(count($steps) - 1, $index + 1)];
    }

    public function back(): void
    {
        $steps = $this->steps();
        $index = (int) array_search($this->step, $steps, true);
        $this->step = $steps[max(0, $index - 1)];
    }

    public function setRepeat(string $repeat): void
    {
        if (! in_array($repeat, ['none', 'daily', 'weekly', 'monthly'], true)) {
            return;
        }
        $this->frequency = match ($repeat) {
            'daily' => 'daily',
            'weekly' => 'weekly',
            'monthly' => 'monthly',
            default => count($this->selectedDays) > 1 ? 'selected_dates' : 'once',
        };
        if ($repeat === 'weekly' && $this->selectedDays !== []) {
            $this->weekdays = collect($this->selectedDays)
                ->map(fn (string $date): int => CarbonImmutable::parse($date)->isoWeekday())
                ->unique()->sort()->values()->all();
        }
    }

    public function toggleReminder(int $minutes): void
    {
        $minutes = max(0, min(43200, $minutes));
        $current = $this->integers($this->reminderOffsets);
        $current = in_array($minutes, $current, true)
            ? array_values(array_diff($current, [$minutes]))
            : [...$current, $minutes];
        rsort($current);
        $this->reminderOffsets = implode(', ', $current);
    }

    public function chooseLabel(string $uuid): void
    {
        $this->labelUuid = $this->labelUuid === $uuid ? '' : $uuid;
    }

    public function chooseColor(string $color): void
    {
        $this->color = PlanKinds::normalizeColor($color) ?? '';
    }

    public function createLabel(ManagePlannerLabels $labels): void
    {
        $this->validate(['newLabelName' => ['required', 'string', 'max:60']]);
        $label = $labels->create($this->user(), $this->newLabelName, $this->color !== '' ? $this->color : PlanKinds::color($this->kind));
        $this->labelUuid = $label->uuid;
        $this->newLabelName = '';
    }

    /** Meeting options from the visual picker: each chosen day at the chosen time. */
    public function addSlotsFromPicker(array $days, string $time, int $minutes): void
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return;
        }
        $this->durationMinutes = max(5, min(1440, $minutes));
        foreach (array_slice($days, 0, 14) as $day) {
            if (is_string($day) && $this->canonicalDate($day) instanceof CarbonImmutable) {
                $slot = $day.' '.$time;
                if (! in_array($slot, $this->meetingSlots, true) && count($this->meetingSlots) < MeetingScheduler::MAX_SLOTS) {
                    $this->meetingSlots[] = $slot;
                }
            }
        }
        sort($this->meetingSlots);
        $this->resetErrorBag('slots');
    }

    /** Meeting option from the exact date and time fields. */
    public function addSlotFromFields(): void
    {
        $this->validate(['startsOn' => ['required', 'date_format:Y-m-d'], 'startTime' => ['required', 'date_format:H:i']]);
        $this->addSlotsFromPicker([$this->startsOn], $this->startTime, $this->durationMinutes);
    }

    public function removeSlot(int $index): void
    {
        unset($this->meetingSlots[$index]);
        $this->meetingSlots = array_values($this->meetingSlots);
    }

    public function addUserInvitee(): void
    {
        $username = ltrim(trim($this->inviteUsername), '@');
        $this->validate(['inviteUsername' => ['required', 'string', 'max:80']]);
        $user = User::query()->where('username', $username)->where('status', 'active')->first();
        if (! $user instanceof User || (int) $user->id === (int) $this->user()->id) {
            throw ValidationException::withMessages(['inviteUsername' => __('planner.meetings.errors.unknown_user', ['username' => $username])]);
        }
        if (! collect($this->invitees)->contains(fn (array $invitee): bool => ($invitee['username'] ?? null) === $user->username)) {
            $this->invitees[] = ['username' => $user->username];
        }
        $this->inviteUsername = '';
    }

    public function addGuestInvitee(): void
    {
        $this->validate([
            'guestName' => ['required', 'string', 'max:120'],
            'guestEmail' => ['nullable', 'email', 'max:190'],
        ]);
        $this->invitees[] = ['name' => trim($this->guestName), 'email' => trim($this->guestEmail)];
        $this->guestName = '';
        $this->guestEmail = '';
    }

    public function removeInvitee(int $index): void
    {
        unset($this->invitees[$index]);
        $this->invitees = array_values($this->invitees);
    }

    public function addPrerequisite(): void
    {
        abort_if(count($this->prerequisites) >= 50, 422);
        $this->prerequisites[] = ['title' => '', 'required' => true];
    }

    public function removePrerequisite(int $index): void
    {
        abort_unless(array_key_exists($index, $this->prerequisites), 404);
        unset($this->prerequisites[$index]);
        $this->prerequisites = array_values($this->prerequisites);
    }

    public function addExpenseEstimate(): void
    {
        abort_if(count($this->expenseEstimates) >= 50, 422);
        $this->expenseEstimates[] = [
            'label' => '',
            'amount' => '',
            'unit_code' => $this->defaultMonetaryUnitCode(),
        ];
    }

    public function removeExpenseEstimate(int $index): void
    {
        abort_unless(array_key_exists($index, $this->expenseEstimates), 404);
        unset($this->expenseEstimates[$index]);
        $this->expenseEstimates = array_values($this->expenseEstimates);
    }

    /**
     * Create the plan (or send the meeting invitations). Any problem is shown
     * in the wizard: validation errors open the step they belong to, and a
     * refusal from the planner explains itself instead of an error page.
     */
    public function save(): mixed
    {
        $this->resetErrorBag('save');

        try {
            return $this->persist(app(CreatePlan::class), app(ConfigurePlanReadiness::class), app(CreatePlanScheduleRule::class));
        } catch (ValidationException $exception) {
            $this->openStepOf(array_keys($exception->errors()));

            throw $exception;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }
            report(new \RuntimeException('Plan wizard refused: '.$exception->getMessage(), previous: $exception));
            $this->addError('save', __('planner.wizard.save_failed', ['reason' => $exception->getMessage()]));

            return null;
        }
    }

    /** @param list<string> $fields */
    private function openStepOf(array $fields): void
    {
        $stepOf = function (string $field): string {
            $root = explode('.', $field)[0];

            return match (true) {
                in_array($root, ['title', 'kind', 'description'], true) => 'what',
                in_array($root, ['startsOn', 'startTime', 'durationMinutes', 'interval', 'weekdays', 'selectedDates', 'endsOn', 'occurrenceLimit', 'slots', 'frequency'], true) => 'when',
                in_array($root, ['invitees', 'inviteUsername', 'guestName', 'guestEmail', 'location'], true) => 'who',
                default => 'details',
            };
        };
        foreach ($this->steps() as $step) {
            foreach ($fields as $field) {
                if ($stepOf($field) === $step) {
                    $this->step = $step;

                    return;
                }
            }
        }
    }

    private function persist(
        CreatePlan $createPlan,
        ConfigurePlanReadiness $configureReadiness,
        CreatePlanScheduleRule $createRule,
    ): mixed {
        if ($this->kind === 'meeting') {
            return $this->saveMeeting(app(MeetingScheduler::class));
        }

        // Several days picked on the timeline without repeating: those dates.
        if ($this->frequency === 'once' && count($this->selectedDays) > 1) {
            $this->frequency = 'selected_dates';
        }
        if ($this->frequency === 'selected_dates' && trim($this->selectedDates) === '' && $this->selectedDays !== []) {
            $this->selectedDates = implode(', ', $this->selectedDays);
        }

        $unitCodes = array_keys(MonetaryUnitCatalog::all());

        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'timezone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! TemporalPreferences::validTimezone(is_string($value) ? $value : null)) {
                    $fail(__('planner.wizard.invalid_timezone'));
                }
            }],
            'frequency' => ['required', 'in:once,daily,weekly,selected_dates,monthly'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'interval' => ['required', 'integer', 'min:1', 'max:365'],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'selectedDates' => ['nullable', 'string', 'max:5000'],
            'endsOn' => ['nullable', 'date_format:Y-m-d'],
            'occurrenceLimit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'windowBeforeMinutes' => ['integer', 'min:0', 'max:10080'],
            'windowAfterMinutes' => ['integer', 'min:0', 'max:10080'],
            'reminderOffsets' => ['nullable', 'string', 'max:500'],
            'prerequisites' => ['array', 'max:50'],
            'prerequisites.*.title' => ['required', 'string', 'max:240'],
            'prerequisites.*.required' => ['boolean'],
            'expenseEstimates' => ['array', 'max:50'],
            'expenseEstimates.*.label' => ['required', 'string', 'max:180'],
            'expenseEstimates.*.amount' => ['required', 'string', 'max:40'],
            'expenseEstimates.*.unit_code' => ['required', 'string', Rule::in($unitCodes)],
        ]);

        $user = $this->user();
        $context = $this->context();
        Gate::forUser($user)->authorize('interactContent', $context);

        [$participants, $originType, $originUuid] = $this->provenance($context, $user);

        return DB::transaction(function () use (
            $createPlan,
            $configureReadiness,
            $createRule,
            $context,
            $user,
            $data,
            $participants,
            $originType,
            $originUuid,
        ): mixed {
            $plan = $createPlan->execute(
                $context,
                $user,
                $data['title'],
                $data['description'] !== '' ? $data['description'] : null,
                $data['timezone'],
                $participants,
                $originType,
                $originUuid,
                metadata: $this->appearanceMetadata(),
                domainBlueprintVersion: $this->blueprintVersion(),
            );

            $configureReadiness->execute(
                $plan,
                $user,
                $data['prerequisites'],
                $data['expenseEstimates'],
            );

            $createRule->execute(
                $plan,
                $user,
                PlanScheduleFrequency::from($data['frequency']),
                $data['startsOn'],
                $data['startTime'],
                $data['durationMinutes'],
                $data['interval'],
                $data['weekdays'],
                $this->dates($data['selectedDates']),
                $data['endsOn'] !== '' ? $data['endsOn'] : null,
                $data['occurrenceLimit'] !== '' ? (int) $data['occurrenceLimit'] : null,
                $data['windowBeforeMinutes'],
                $data['windowAfterMinutes'],
                $this->integers($data['reminderOffsets']),
            );

            return $this->redirectRoute('planner.show', $plan, navigate: true);
        });
    }

    public function render(): View
    {
        $context = $this->context();
        $context->loadMissing([
            'personalBinding.actor.user',
            'businessBinding.business',
            'relationshipBinding.relationship.participants.actor.user',
            'relationshipBinding.relationship.purposeConcept.labels',
            'groupSpaceBinding.groupSpace.group',
        ]);

        $user = $this->user();

        return view('livewire.planner.create', [
            'steps' => $this->steps(),
            'monthDayLabel' => $this->monthDayLabel($user),
            'kinds' => PlanKinds::KINDS,
            'palette' => PlanKinds::PALETTE,
            'labels' => PlannerLabel::query()->where('actor_id', $user->actor?->id)->orderBy('name')->get(),
            'picker' => PlannerHorizons::for($user),
            'busy' => $this->step === 'when' ? PlannerHorizons::busy($user) : [],
            'previewColor' => PlanKinds::normalizeColor($this->color)
                ?? PlannerLabel::query()->where('uuid', $this->labelUuid)->value('color')
                ?? PlanKinds::color($this->kind),
            'context' => $context,
            'contextLabel' => $this->contextLabel($context),
            'weekdayOrder' => TemporalPreferences::weekdayOrder($this->user()->locale),
            'blueprintVersion' => $this->blueprintVersion(),
            'unitCatalog' => MonetaryUnitCatalog::all(),
        ])->title(__('planner.create.title'));
    }

    private function monthDayLabel(User $user): string
    {
        $date = $this->canonicalDate($this->startsOn);

        return $date instanceof CarbonImmutable
            ? TemporalCalendar::format($date, $user, $this->timezone, 'd')
            : '';
    }

    /** @return list<string> */
    public function steps(): array
    {
        return $this->kind === 'meeting'
            ? self::STEPS
            : array_values(array_diff(self::STEPS, ['who']));
    }

    private function validateStep(string $step): void
    {
        match ($step) {
            'what' => $this->validate([
                'title' => ['required', 'string', 'max:180'],
                'kind' => ['required', Rule::in(PlanKinds::keys())],
            ]),
            'when' => $this->kind === 'meeting'
                ? ($this->meetingSlots === [] ? throw ValidationException::withMessages(['slots' => __('planner.meetings.errors.slots')]) : null)
                : $this->validate([
                    'startsOn' => ['required', 'date_format:Y-m-d'],
                    'startTime' => ['required', 'date_format:H:i'],
                    'durationMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
                ]),
            'who' => $this->invitees === []
                ? throw ValidationException::withMessages(['invitees' => __('planner.meetings.errors.invitees')])
                : null,
            default => null,
        };
    }

    /** @return array<string, string|true> */
    private function appearanceMetadata(): array
    {
        $label = $this->labelUuid !== ''
            ? PlannerLabel::query()->where('uuid', $this->labelUuid)->where('actor_id', $this->user()->actor?->id)->first()
            : null;

        return array_filter([
            'important' => $this->important ?: null,
            'kind' => PlanKinds::valid($this->kind) ? $this->kind : null,
            'label_uuid' => $label?->uuid,
            'color' => PlanKinds::normalizeColor($this->color),
        ]);
    }

    private function saveMeeting(MeetingScheduler $meetings): mixed
    {
        $this->validateStep('what');
        $this->validateStep('when');
        $this->validateStep('who');
        $this->validate([
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:300'],
            'timezone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! TemporalPreferences::validTimezone(is_string($value) ? $value : null)) {
                    $fail(__('planner.wizard.invalid_timezone'));
                }
            }],
        ]);

        $starts = collect($this->meetingSlots)
            ->map(fn (string $slot): CarbonImmutable => CarbonImmutable::createFromFormat('Y-m-d H:i', $slot, $this->timezone))
            ->values()
            ->all();

        $result = $meetings->create($this->user(), [
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'duration_minutes' => $this->durationMinutes,
            'timezone' => $this->timezone,
            'label_uuid' => $this->appearanceMetadata()['label_uuid'] ?? null,
        ], $starts, $this->invitees);

        session()->flash('meeting-links', $result['links']);

        return $this->redirectRoute('planner.meetings.show', $result['meeting'], navigate: true);
    }

    /** @return array{list<array{actor: Actor, role: string}>, ?string, ?string} */
    private function provenance(Context $context, User $user): array
    {
        if ($context->kind === ContextKind::Business) {
            $business = $context->businessBinding?->business;
            abort_unless($business instanceof Business, 422);

            return [[], 'business', $business->uuid];
        }

        if ($context->kind !== ContextKind::Relationship) {
            return [[], null, null];
        }

        $relationship = $context->relationshipBinding?->relationship;
        abort_unless(
            $relationship instanceof Relationship && $relationship->status === RelationshipStatus::Active,
            422,
        );

        $participants = $relationship->participants
            ->filter(fn ($participant): bool => $participant->status === RelationshipParticipantStatus::Active)
            ->filter(fn ($participant): bool => (int) $participant->actor_id !== (int) $user->actor?->id)
            ->map(fn ($participant): array => [
                'actor' => $participant->actor,
                'role' => $participant->role,
            ])
            ->values()
            ->all();

        return [$participants, 'relationship', $relationship->uuid];
    }

    private function applyBlueprintDefaults(): void
    {
        $version = $this->blueprintVersion();
        abort_unless($version instanceof DomainBlueprintVersion, 422);

        $frequency = (string) ($version->guided_entry['frequency'] ?? '');
        if (in_array($frequency, ['once', 'daily', 'weekly', 'selected_dates', 'monthly'], true)) {
            $this->frequency = $frequency;
        }

        $duration = (int) ($version->guided_entry['duration_minutes'] ?? 0);
        if ($duration >= 1 && $duration <= 10080) {
            $this->durationMinutes = $duration;
        }
    }

    private function blueprintVersion(): ?DomainBlueprintVersion
    {
        if ($this->blueprintSlug === '') {
            return null;
        }

        return app(DomainBlueprintCatalog::class)->version(
            $this->blueprintSlug,
            DomainJourneyKind::PersonalActivity,
        );
    }

    private function context(): Context
    {
        $context = Context::query()->where('uuid', $this->contextUuid)->firstOrFail();
        Gate::forUser($this->user())->authorize('view', $context);

        return $context;
    }

    private function contextLabel(Context $context): string
    {
        return match ($context->kind) {
            ContextKind::Personal => (string) __('planner.context.personal'),
            ContextKind::Business => $context->businessBinding?->business->name
                ?? $context->uuid,
            ContextKind::Relationship => (string) __('planner.context.relationship', [
                'title' => $context->relationshipBinding?->relationship?->title
                    ?: $context->relationshipBinding?->relationship?->purposeConcept?->displayLabel()
                    ?: $context->uuid,
            ]),
            ContextKind::GroupSpace => (string) __('planner.context.group_space', [
                'space' => $context->groupSpaceBinding === null
                    ? $context->uuid
                    : $context->groupSpaceBinding->groupSpace->name,
            ]),
            default => $context->kind->value,
        };
    }

    /** @return list<string> */
    private function dates(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', trim($value)) ?: [])
            ->map(fn (string $date): string => trim($date))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function integers(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', trim($value)) ?: [])
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): int => (int) $item)
            ->unique()
            ->values()
            ->all();
    }

    private function canonicalDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, $this->timezone);
    }

    private function defaultMonetaryUnitCode(): string
    {
        if ($this->contextUuid !== '') {
            $context = Context::query()
                ->with('businessBinding.business.defaultMonetaryUnit')
                ->where('uuid', $this->contextUuid)
                ->first();

            $businessCode = $context?->kind === ContextKind::Business
                ? strtoupper((string) $context->businessBinding?->business->defaultMonetaryUnit?->code)
                : '';

            if ($businessCode !== '' && array_key_exists($businessCode, MonetaryUnitCatalog::all())) {
                return $businessCode;
            }
        }

        $code = strtoupper((string) ($this->user()->default_monetary_unit_code ?: 'USD'));

        return array_key_exists($code, MonetaryUnitCatalog::all())
            ? $code
            : (string) array_key_first(MonetaryUnitCatalog::all());
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
