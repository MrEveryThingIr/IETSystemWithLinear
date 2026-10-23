<?php

namespace App\Livewire\Planner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ComposePlan;
use App\Actions\Planner\ConfigureExecutionPolicy;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ExecuteLivingOccurrence;
use App\Actions\Planner\ManagePurposes;
use App\Actions\Planner\PersonalExperiment;
use App\Actions\Planner\ReuseRoutine;
use App\Actions\Planner\ReviseLivingSchedule;
use App\Models\Plan;
use App\Models\Planner\Experiment;
use App\Models\Planner\ExperimentAdjustment;
use App\Models\Planner\ExperimentObservation;
use App\Models\Planner\OccurrenceParticipation;
use App\Models\Planner\Purpose;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\PlanTemplate;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Support\Planner\CommitmentFactors;
use App\Support\Planner\LabAuthority;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
class Lab extends Component
{
    use WithPagination;

    #[Url]
    public string $occurrence = '';

    public string $finishOutcome = 'completed';

    public string $purposeTarget = '';

    public string $editingPurpose = '';

    public string $observationCorrection = '';

    public string $experimentPurpose = '';

    #[Url]
    public string $purposeFilter = '';

    public string $revisionScope = 'future';

    public string $revisionDate = '';

    public string $revisionTime = '09:00';

    public int $revisionMinutes = 30;

    public string $revisionOccurrence = '';

    public string $revisionAdjustment = '';

    public string $newTitle = '';

    public string $newFrequency = 'daily';

    public function createActivity(): void
    {
        $this->validate(['newTitle' => ['required', 'string', 'max:180'], 'newFrequency' => ['required', 'in:once,daily,weekly,monthly']]);
        $plan = $this->domain(fn () => DB::transaction(function () {
            $user = $this->user();
            $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, $this->newTitle,
                timezone: TemporalPreferences::timezoneFor($user));
            app(ConfigureExecutionPolicy::class)->execute($user, $plan, $this->policyStyle, $this->reportingDays);
            app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::from($this->newFrequency),
                $this->revisionDate, $this->revisionTime, $this->revisionMinutes);

            return $plan;
        }));
        $this->planUuid = $plan->uuid;
        $this->newTitle = '';
    }

    #[Url]
    public string $tab = 'today';

    #[Url]
    public string $planUuid = '';

    public string $from = '';

    public string $through = '';

    public string $purposeTitle = '';

    public string $purposeDescription = '';

    public string $purposeUuid = '';

    public string $parentUuid = '';

    public string $routineName = '';

    public string $routineStart = '';

    public string $policyStyle = 'flexible';

    public int $reportingDays = 30;

    public string $reportOccurrence = '';

    public string $reportOutcome = 'completed';

    public string $actualStart = '';

    public string $actualEnd = '';

    public string $correctionReason = '';

    public string $supersedes = '';

    public string $experimentUuid = '';

    public array $experimentForm = [];

    public array $observationForm = [];

    public string $proposal = '';

    public string $notice = '';

    public function mount(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->toDateString();
        $this->from = CarbonImmutable::parse($today)->subDays(29)->toDateString();
        $this->through = $this->routineStart = $today;
        $this->revisionDate = $today;
        $this->experimentForm = ['starts_on' => $today, 'ends_on' => CarbonImmutable::parse($today)->addDays(13)->toDateString(), 'metric' => 'active_minutes'];
        $this->observationForm = ['observed_at' => CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->format('Y-m-d\TH:i')];
        if ($this->occurrence !== '') {
            $this->selectReport($this->occurrence);
        }
    }

    public function act(string $uuid, string $action, string $retryKey): void
    {
        $user = $this->user();
        $occurrence = PlanOccurrence::query()->where('uuid', $uuid)->firstOrFail();
        $this->domain(fn () => app(ExecuteLivingOccurrence::class)->execute($user, $occurrence, $action, $retryKey,
            $action === 'finish' ? ['outcome' => $this->finishOutcome] : []));
    }

    public function selectReport(string $uuid): void
    {
        $user = $this->user();
        $occurrence = PlanOccurrence::query()->where('uuid', $uuid)->firstOrFail();
        Gate::forUser($user)->authorize('participate', $occurrence->plan);
        $this->reportOccurrence = $uuid;
        $latest = $occurrence->events()->where('actor_id', $user->actor->id)
            ->whereIn('event_type', ['completed', 'outcome_reported', 'outcome_corrected'])->reorder()->latest('id')->first();
        $this->supersedes = $latest->uuid ?? '';
        $participation = OccurrenceParticipation::query()->where('plan_occurrence_id', $occurrence->id)->where('actor_id', $user->actor->id)->first();
        $timezone = TemporalPreferences::timezoneFor($user);
        $this->reportOutcome = $participation->outcome ?? 'completed';
        $this->actualStart = $participation?->actual_start_at?->setTimezone($timezone)->format('Y-m-d\TH:i') ?? '';
        $this->actualEnd = $participation?->actual_end_at?->setTimezone($timezone)->format('Y-m-d\TH:i') ?? '';
        $this->correctionReason = '';
    }

    public function report(): void
    {
        $user = $this->user();
        $occurrence = PlanOccurrence::query()->where('uuid', $this->reportOccurrence)->firstOrFail();
        $payload = ['outcome' => $this->reportOutcome, 'actual_start_at' => $this->actualStart ?: null,
            'actual_end_at' => $this->actualEnd ?: null, 'reason' => $this->correctionReason ?: null, 'supersedes' => $this->supersedes ?: null];
        // Stable for an unchanged form; a different report receives a different key.
        $key = 'report-'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->domain(fn () => app(ExecuteLivingOccurrence::class)->execute($user, $occurrence, $this->supersedes === '' ? 'report' : 'correct', $key, $payload));
        $this->selectReport($occurrence->uuid);
    }

    public function createPurpose(): void
    {
        $this->domain(fn () => $this->editingPurpose === ''
            ? app(ManagePurposes::class)->create($this->user(), $this->purposeTitle, $this->purposeDescription, $this->purposeTarget ?: null)
            : app(ManagePurposes::class)->update($this->user(), Purpose::query()->where('uuid', $this->editingPurpose)->firstOrFail(), $this->purposeTitle, $this->purposeDescription, $this->purposeTarget ?: null));
        $this->purposeTitle = $this->purposeDescription = '';
        $this->purposeTarget = $this->editingPurpose = '';
    }

    public function editPurpose(string $uuid): void
    {
        $purpose = Purpose::query()->where('uuid', $uuid)->firstOrFail();
        LabAuthority::owns($this->user(), $purpose->owner_actor_id);
        $this->editingPurpose = $uuid;
        $this->purposeTitle = $purpose->title;
        $this->purposeDescription = $purpose->description ?? '';
        $this->purposeTarget = $purpose->target_on?->toDateString() ?? '';
    }

    public function archivePurpose(string $uuid): void
    {
        $this->domain(fn () => app(ManagePurposes::class)->archive($this->user(), Purpose::query()->where('uuid', $uuid)->firstOrFail()));
    }

    public function unlinkPurpose(string $purposeUuid, string $planUuid): void
    {
        $this->domain(fn () => app(ManagePurposes::class)->unlink($this->user(), Purpose::query()->where('uuid', $purposeUuid)->firstOrFail(), Plan::query()->where('uuid', $planUuid)->firstOrFail()));
    }

    public function linkPurpose(): void
    {
        $purpose = Purpose::query()->where('uuid', $this->purposeUuid)->firstOrFail();
        $this->domain(fn () => app(ManagePurposes::class)->link($this->user(), $purpose, $this->selectedPlan()));
    }

    public function compose(): void
    {
        $parent = $this->parentUuid === '' ? null : Plan::query()->where('uuid', $this->parentUuid)->firstOrFail();
        $this->domain(fn () => app(ComposePlan::class)->execute($this->user(), $this->selectedPlan(), $parent));
    }

    public function capture(): void
    {
        $this->domain(fn () => app(ReuseRoutine::class)->capture($this->user(), $this->selectedPlan(), $this->routineName));
    }

    public function reuse(string $uuid): void
    {
        $user = $this->user();
        $this->validate(['routineStart' => ['required', 'date_format:Y-m-d']]);
        $template = PlanTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $this->domain(fn () => app(ReuseRoutine::class)->instantiate($user, $template, app(EnsurePersonalContext::class)->execute($user), $this->routineStart));
    }

    public function configurePolicy(): void
    {
        $this->domain(fn () => app(ConfigureExecutionPolicy::class)->execute($this->user(), $this->selectedPlan(), $this->policyStyle, $this->reportingDays));
    }

    public function createExperiment(): void
    {
        $experiment = $this->domain(fn () => app(PersonalExperiment::class)->create($this->user(), $this->experimentForm,
            plan: $this->experimentPurpose === '' ? $this->selectedPlan() : null,
            purpose: $this->experimentPurpose === '' ? null : Purpose::query()->where('uuid', $this->experimentPurpose)->firstOrFail()));
        $this->experimentUuid = $experiment->uuid;
    }

    public function observe(): void
    {
        $key = 'observation-'.hash('sha256', json_encode([$this->observationCorrection, $this->observationForm], JSON_THROW_ON_ERROR));
        $this->domain(fn () => app(PersonalExperiment::class)->observe($this->user(), $this->experiment(), $key, $this->observationForm,
            $this->observationCorrection === '' ? null : ExperimentObservation::query()->where('uuid', $this->observationCorrection)->firstOrFail()));
        $this->observationCorrection = '';
    }

    public function correctObservation(string $uuid): void
    {
        $row = ExperimentObservation::query()->where('uuid', $uuid)->firstOrFail();
        $experiment = $this->experiment();
        abort_unless($row->experiment_id === $experiment->id, 403);
        $this->observationCorrection = $uuid;
        $this->observationForm = $row->only(['value', 'difficulty', 'cue', 'obstacle', 'response', 'reflection']);
        $this->observationForm['observed_at'] = $row->observed_at->setTimezone($experiment->timezone)->format('Y-m-d\TH:i');
    }

    public function propose(): void
    {
        $this->domain(fn () => app(PersonalExperiment::class)->propose($this->user(), $this->experiment(), $this->proposal));
        $this->proposal = '';
    }

    public function decide(string $uuid, bool $approve): void
    {
        $this->domain(fn () => app(PersonalExperiment::class)->decide($this->user(), ExperimentAdjustment::query()->where('uuid', $uuid)->firstOrFail(), $approve));
    }

    public function chooseAdjustment(string $uuid): void
    {
        $adjustment = ExperimentAdjustment::query()->where('uuid', $uuid)->firstOrFail();
        $experiment = app(PersonalExperiment::class)->authorize($this->user(), Experiment::query()->findOrFail($adjustment->experiment_id));
        abort_unless($adjustment->decision === 'approved', 422);
        $this->revisionAdjustment = $uuid;
        if ($experiment->plan_id !== null) {
            $this->planUuid = Plan::query()->findOrFail($experiment->plan_id)->uuid;
        }
    }

    public function revise(): void
    {
        $this->domain(fn () => app(ReviseLivingSchedule::class)->execute($this->user(), $this->selectedPlan(),
            $this->revisionScope, $this->revisionDate, $this->revisionTime, $this->revisionMinutes,
            $this->revisionOccurrence === '' ? null : PlanOccurrence::query()->where('uuid', $this->revisionOccurrence)->firstOrFail(),
            $this->revisionAdjustment === '' ? null : ExperimentAdjustment::query()->where('uuid', $this->revisionAdjustment)->firstOrFail()));
        $this->revisionAdjustment = '';
    }

    public function horizon(int $days): void
    {
        abort_unless(in_array($days, [1, 7, 30, 90, 365], true), 422);
        $today = CarbonImmutable::now(TemporalPreferences::timezoneFor($this->user()));
        $this->through = $today->toDateString();
        $this->from = $today->subDays($days - 1)->toDateString();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && app(FeatureSurfaceAccess::class)->allows($user, 'planner'), 403);
        LabAuthority::actor($user);

        return $user;
    }

    private function selectedPlan(): Plan
    {
        $user = $this->user();
        $plan = Plan::query()->where('uuid', $this->planUuid)->firstOrFail();
        Gate::forUser($user)->authorize('view', $plan);

        return $plan;
    }

    private function experiment(): Experiment
    {
        return app(PersonalExperiment::class)->authorize($this->user(), Experiment::query()->where('uuid', $this->experimentUuid)->firstOrFail());
    }

    private function domain(callable $callback): mixed
    {
        $this->resetErrorBag();
        try {
            $result = $callback();
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 403) {
                throw $exception;
            }
            throw ValidationException::withMessages(['action' => $exception->getMessage() ?: __('living_planner.action_unavailable')]);
        }
        $this->notice = __('living_planner.saved');

        return $result;
    }

    public function render(): View
    {
        $user = $this->user();
        $actor = LabAuthority::actor($user);
        abort_unless(in_array($this->tab, ['today', 'routines', 'purposes', 'insights', 'history'], true), 404);
        $timezone = TemporalPreferences::timezoneFor($user);
        $plans = Plan::query()->where(fn ($q) => $q->where('created_by_actor_id', $actor->id)
            ->orWhereHas('participants', fn ($p) => $p->where('actor_id', $actor->id)->where('status', 'active')))
            ->with('parentPlan')->orderBy('title')->get()
            ->filter(fn (Plan $plan): bool => Gate::forUser($user)->allows('view', $plan))->values();
        $ids = $plans->modelKeys();
        $selected = $this->planUuid === '' ? null : $this->selectedPlan();
        $measureIds = $selected === null ? $ids : app(CommitmentFactors::class)->hierarchy($user, $selected);
        if ($this->purposeFilter !== '') {
            $purpose = Purpose::query()->where('uuid', $this->purposeFilter)->firstOrFail();
            LabAuthority::owns($user, $purpose->owner_actor_id);
            $measureIds = [];
            foreach ($purpose->plans as $linked) {
                if (Gate::forUser($user)->allows('view', $linked)) {
                    $measureIds = array_values(array_unique([...$measureIds, ...app(CommitmentFactors::class)->hierarchy($user, $linked)]));
                }
            }
        }
        $this->validate(['from' => ['required', 'date_format:Y-m-d'], 'through' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $factors = $this->tab === 'insights' ? app(CommitmentFactors::class)->measure($user, $measureIds,
            CarbonImmutable::parse($this->from, $timezone)->startOfDay(), CarbonImmutable::parse($this->through, $timezone)->endOfDay()) : null;
        $previousFactors = null;
        if ($factors !== null) {
            $start = CarbonImmutable::parse($this->from, $timezone)->startOfDay();
            $days = (int) $start->diffInDays(CarbonImmutable::parse($this->through, $timezone)->startOfDay()) + 1;
            $previousFactors = app(CommitmentFactors::class)->measure($user, $measureIds, $start->subDays($days), $start->subSecond());
        }
        $today = CarbonImmutable::now($timezone);
        $occurrences = PlanOccurrence::query()->with(['plan', 'scheduleRule'])->whereIn('plan_id', $ids)
            ->when($selected !== null, fn ($query) => $query->where('plan_id', $selected->id))
            ->where('scheduled_start_at', '<=', $today->addDays(7)->endOfDay()->utc())
            ->orderByRaw("CASE WHEN status IN ('scheduled', 'in_progress') AND scheduled_start_at <= ? THEN 0 WHEN scheduled_start_at > ? THEN 1 ELSE 2 END", [now()->utc(), now()->utc()])
            ->orderByDesc('scheduled_start_at')->paginate(20, pageName: 'activities');
        $participations = OccurrenceParticipation::query()->whereIn('plan_occurrence_id', $occurrences->getCollection()->modelKeys())->where('actor_id', $actor->id)->get()->keyBy('plan_occurrence_id');
        $reportSelection = $this->reportOccurrence === '' ? null : PlanOccurrence::query()->where('uuid', $this->reportOccurrence)->firstOrFail();
        if ($reportSelection !== null) {
            Gate::forUser($user)->authorize('participate', $reportSelection->plan);
        }
        $purposes = Purpose::query()->where('owner_actor_id', $actor->id)->with('plans')->where('status', 'active')->latest()->get();
        $templates = PlanTemplate::query()->where('owner_actor_id', $actor->id)->whereNotNull('composition')->where('status', 'active')->get();
        $experiments = Experiment::query()->where('owner_actor_id', $actor->id)->latest('id')->get();
        $experiment = $this->experimentUuid === '' ? null : $this->experiment();
        $experimentInsights = $experiment === null ? null : app(PersonalExperiment::class)->insights($user, $experiment);
        $observations = $experiment === null ? collect() : ExperimentObservation::query()->where('experiment_id', $experiment->id)->latest('id')->get();
        $adjustments = $experiment === null ? collect() : ExperimentAdjustment::query()->where('experiment_id', $experiment->id)->latest('id')->get();
        $events = PlanOccurrenceEvent::query()->with('occurrence.plan')
            ->whereHas('occurrence', fn ($q) => $q->whereIn('plan_id', $ids))->latest('id')->paginate(30, pageName: 'history');

        return view('livewire.planner.lab', compact('plans', 'occurrences', 'participations', 'purposes', 'templates', 'factors', 'events',
            'experiments', 'experiment', 'experimentInsights', 'observations', 'adjustments', 'timezone', 'reportSelection', 'previousFactors'));
    }
}
