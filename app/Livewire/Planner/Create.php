<?php

namespace App\Livewire\Planner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Relationship;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New plan')]
class Create extends Component
{
    #[Url(as: 'context')]
    public string $contextUuid = '';

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

    public int $windowBeforeMinutes = 0;

    public int $windowAfterMinutes = 0;

    public string $reminderOffsets = '15';

    public function mount(EnsurePersonalContext $personal): void
    {
        $user = $this->user();
        $this->timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($this->timezone);

        $this->startsOn = $now->format('Y-m-d');
        $this->startTime = $now->addHour()->format('H:00');
        $this->weekdays = [$now->isoWeekday()];

        if ($this->contextUuid === '') {
            $this->contextUuid = $personal->execute($user)->uuid;
        }

        $context = $this->context();
        Gate::forUser($user)->authorize('interactContent', $context);
    }

    public function save(CreatePlan $createPlan, CreatePlanScheduleRule $createRule): mixed
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'timezone' => ['required', 'timezone:all'],
            'frequency' => ['required', 'in:once,daily,weekly,selected_dates'],
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
        ]);

        $user = $this->user();
        $context = $this->context();
        Gate::forUser($user)->authorize('interactContent', $context);

        [$participants, $originType, $originUuid] = $this->provenance($context, $user);

        $plan = $createPlan->execute(
            $context,
            $user,
            $data['title'],
            $data['description'] !== '' ? $data['description'] : null,
            $data['timezone'],
            $participants,
            $originType,
            $originUuid,
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

        return $this->redirectRoute('planner.show', $plan);
    }

    public function render(): View
    {
        $context = $this->context();
        $context->loadMissing([
            'personalBinding.actor.user',
            'relationshipBinding.relationship.participants.actor.user',
            'relationshipBinding.relationship.purposeConcept.labels',
            'groupSpaceBinding.groupSpace.group',
        ]);

        return view('livewire.planner.create', [
            'context' => $context,
            'contextLabel' => $this->contextLabel($context),
            'weekdayOrder' => TemporalPreferences::weekdayOrder($this->user()->locale),
        ]);
    }

    /** @return array{list<array{actor: Actor, role: string}>, ?string, ?string} */
    private function provenance(Context $context, User $user): array
    {
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
            ContextKind::Relationship => (string) __('planner.context.relationship', [
                'title' => $context->relationshipBinding?->relationship?->title
                    ?: $context->relationshipBinding?->relationship?->purposeConcept?->displayLabel()
                    ?: $context->uuid,
            ]),
            ContextKind::GroupSpace => (string) __('planner.context.group_space', [
                'space' => $context->groupSpaceBinding?->groupSpace?->name ?? $context->uuid,
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

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
