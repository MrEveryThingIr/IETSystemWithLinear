<?php

namespace App\Livewire\Planner;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\ManageHabit;
use App\Actions\Planner\ManagePlannerLabels;
use App\Actions\Planner\RecordPlanOccurrenceExpense;
use App\Actions\Planner\SetPlanOccurrencePrerequisite;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Livewire\Planner\Concerns\ReportsOccurrences;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Commitment;
use App\Models\ContentEvidenceReference;
use App\Models\Plan;
use App\Models\PlanExpenseEstimate;
use App\Models\Planner\PlanPeriod;
use App\Models\Planner\PlanUrge;
use App\Models\PlanOccurrence;
use App\Models\PlanPrerequisite;
use App\Models\Relationship;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanStatus;
use App\Support\MediaLimits;
use App\Support\MonetaryUnitCatalog;
use App\Support\Planner\PlanAppearance;
use App\Support\Planner\PlanSeries;
use App\Support\Planner\RoutineProgress;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
class Show extends Component
{
    use ReportsOccurrences;
    use WithFileUploads;

    /** Habit loop form. */
    public bool $habitEditing = false;

    public string $habitMode = 'build';

    public string $habitCue = '';

    public string $habitReward = '';

    public string $habitWhy = '';

    public string $habitReplaces = '';

    /** Starting a period or changing one thing. */
    public bool $periodForm = false;

    public string $periodVerdict = 'adjust';

    public string $periodReflection = '';

    /** @var array<int, int|string|null> */
    public array $periodAutomaticity = [null, null, null, null];

    public string $nextFocus = '';

    public string $nextTarget = '';

    public int $nextDays = 21;

    /** Which period's board is shown (uuid); empty: the current one. */
    public string $boardUuid = '';

    public ?int $urgeStrength = null;

    public Plan $plan;

    public ?int $evidenceOccurrenceId = null;

    /** @var list<int> */
    public array $assetIds = [];

    /** @var list<int> */
    public array $evidenceReferenceIds = [];

    public mixed $evidenceUpload = null;

    public string $evidenceUploadRightsStatus = 'private_study_only';

    public ?int $expenseOccurrenceId = null;

    public string $expenseEstimateId = '';

    public string $expenseLabel = '';

    public string $expenseAmount = '';

    public string $expenseUnitCode = 'EUR';

    public string $expenseNote = '';

    public function mount(Plan $plan): void
    {
        $user = $this->user();
        Gate::forUser($user)->authorize('view', $plan);
        $this->plan = $plan;
        $metadata = is_array($plan->metadata) ? $plan->metadata : [];
        $this->appearanceKind = (string) ($metadata['kind'] ?? '');
        $this->appearanceLabel = (string) ($metadata['label_uuid'] ?? '');
        $this->appearanceColor = (string) ($metadata['color'] ?? '');

        $code = strtoupper((string) ($user->default_monetary_unit_code ?: 'USD'));
        $this->expenseUnitCode = array_key_exists($code, MonetaryUnitCatalog::all())
            ? $code
            : (string) array_key_first(MonetaryUnitCatalog::all());
    }

    public function pause(TransitionPlan $transition): void
    {
        $this->plan = $transition->execute($this->plan, $this->user(), PlanStatus::Paused);
    }

    public function resume(TransitionPlan $transition): void
    {
        $this->plan = $transition->execute($this->plan, $this->user(), PlanStatus::Active);
    }

    public function completePlan(TransitionPlan $transition): void
    {
        $this->plan = $transition->execute($this->plan, $this->user(), PlanStatus::Completed);
    }

    public function cancelPlan(TransitionPlan $transition): void
    {
        $this->plan = $transition->execute($this->plan, $this->user(), PlanStatus::Cancelled);
    }

    public function startOccurrence(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $this->resetErrorBag('execution.'.$occurrenceId);

        try {
            $transition->start($this->occurrence($occurrenceId), $this->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            $this->addError('execution.'.$occurrenceId, $exception->getMessage());
        }
    }

    public function completeOccurrence(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $this->resetErrorBag('execution.'.$occurrenceId);

        try {
            $transition->complete($this->occurrence($occurrenceId), $this->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            $this->addError('execution.'.$occurrenceId, $exception->getMessage());
        }
    }

    public function skipOccurrence(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $transition->skip($this->occurrence($occurrenceId), $this->user());
    }

    public function cancelOccurrence(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $transition->cancel($this->occurrence($occurrenceId), $this->user());
    }

    public function togglePrerequisite(
        int $occurrenceId,
        int $prerequisiteId,
        SetPlanOccurrencePrerequisite $setPrerequisite,
    ): void {
        $occurrence = $this->occurrence($occurrenceId);
        $prerequisite = $this->prerequisite($prerequisiteId);

        $completed = $occurrence->prerequisiteChecks()
            ->where('plan_prerequisite_id', $prerequisite->id)
            ->whereNotNull('completed_at')
            ->exists();

        $setPrerequisite->execute($occurrence, $prerequisite, $this->user(), ! $completed);
    }

    public function chooseEvidenceOccurrence(int $occurrenceId): void
    {
        Gate::forUser($this->user())->authorize('participate', $this->plan);
        $this->occurrence($occurrenceId);
        $this->evidenceOccurrenceId = $occurrenceId;
        $this->reset(['assetIds', 'evidenceReferenceIds', 'evidenceUpload']);
        $this->resetErrorBag('evidence');
    }

    public function attachEvidence(CreateContextAsset $createAsset, AttachPlanOccurrenceEvidence $attach): void
    {
        abort_unless($this->evidenceOccurrenceId !== null, 422);

        $occurrence = $this->occurrence($this->evidenceOccurrenceId);
        $user = $this->user();
        Gate::forUser($user)->authorize('participate', $this->plan);

        $this->validate([
            'assetIds' => ['array', 'max:20'],
            'assetIds.*' => ['integer'],
            'evidenceReferenceIds' => ['array', 'max:20'],
            'evidenceReferenceIds.*' => ['integer'],
            'evidenceUpload' => ['nullable', 'file', MediaLimits::rule('asset')],
            'evidenceUploadRightsStatus' => ['required', 'string', Rule::in(Asset::RIGHTS_STATUSES)],
        ]);

        if ($this->assetIds === [] && $this->evidenceReferenceIds === [] && ! ($this->evidenceUpload instanceof UploadedFile)) {
            $this->addError('evidence', __('planner.validation.evidence_required'));

            return;
        }

        $assetIds = $this->assetIds;

        if ($this->evidenceUpload instanceof UploadedFile) {
            $asset = $createAsset->execute(
                $this->plan->context()->firstOrFail(),
                $user,
                $this->evidenceUpload,
                $this->evidenceUploadRightsStatus,
            );
            $assetIds[] = $asset->id;
        }

        $attach->execute($occurrence, $user, $assetIds, $this->evidenceReferenceIds);

        $this->reset(['evidenceOccurrenceId', 'assetIds', 'evidenceReferenceIds', 'evidenceUpload']);
        $this->evidenceUploadRightsStatus = 'private_study_only';

        session()->flash('status', __('planner.messages.evidence_attached'));
    }

    public function chooseExpenseOccurrence(int $occurrenceId): void
    {
        Gate::forUser($this->user())->authorize('participate', $this->plan);
        $occurrence = $this->occurrence($occurrenceId);

        abort_unless(in_array($occurrence->status, [
            PlanOccurrenceStatus::InProgress,
            PlanOccurrenceStatus::Completed,
        ], true), 422);

        $this->expenseOccurrenceId = $occurrenceId;
        $this->expenseEstimateId = '';
        $this->expenseLabel = '';
        $this->expenseAmount = '';
        $this->expenseNote = '';

        $firstEstimate = $this->plan->expenseEstimates()->with('monetaryUnit')->first();
        $this->expenseUnitCode = $firstEstimate?->monetaryUnit->code
            ?? (string) array_key_first(MonetaryUnitCatalog::all());
    }

    public function updatedExpenseEstimateId(string $value): void
    {
        if ($value === '') {
            return;
        }

        $estimate = PlanExpenseEstimate::query()
            ->with('monetaryUnit')
            ->where('plan_id', $this->plan->id)
            ->findOrFail((int) $value);

        $this->expenseLabel = $estimate->label;
        $this->expenseUnitCode = $estimate->monetaryUnit->code;
    }

    public function recordExpense(RecordPlanOccurrenceExpense $record): void
    {
        abort_unless($this->expenseOccurrenceId !== null, 422);

        $data = $this->validate([
            'expenseLabel' => ['required', 'string', 'max:180'],
            'expenseAmount' => ['required', 'string', 'max:40'],
            'expenseUnitCode' => ['required', 'string', Rule::in(array_keys(MonetaryUnitCatalog::all()))],
            'expenseEstimateId' => ['nullable'],
            'expenseNote' => ['nullable', 'string', 'max:500'],
        ]);

        $estimate = $data['expenseEstimateId'] !== ''
            ? $this->plan->expenseEstimates()->findOrFail((int) $data['expenseEstimateId'])
            : null;

        $record->execute(
            $this->occurrence($this->expenseOccurrenceId),
            $this->user(),
            $data['expenseLabel'],
            $data['expenseAmount'],
            $data['expenseUnitCode'],
            $estimate,
            $data['expenseNote'] !== '' ? $data['expenseNote'] : null,
        );

        $this->reset([
            'expenseOccurrenceId',
            'expenseEstimateId',
            'expenseLabel',
            'expenseAmount',
            'expenseNote',
        ]);

        session()->flash('status', __('planner.messages.expense_recorded'));
    }

    public ?int $focusedOccurrenceId = null;

    public string $appearanceKind = '';

    public string $appearanceLabel = '';

    public string $appearanceColor = '';

    public function markOccurrenceDone(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $occurrence = $this->plan->occurrences()->whereKey($occurrenceId)->firstOrFail();
        $transition->markDone($occurrence, $this->user());
    }

    /** Open one occurrence from the compact list. */
    public function focusOccurrence(int $occurrenceId): void
    {
        abort_unless($this->plan->occurrences()->whereKey($occurrenceId)->exists(), 404);
        $this->focusedOccurrenceId = $occurrenceId;
    }

    public function saveAppearance(ManagePlannerLabels $labels): void
    {
        $this->plan = $labels->setAppearance($this->plan, $this->user(), $this->appearanceKind, $this->appearanceLabel, $this->appearanceColor);
        session()->flash('status', __('planner.labels.saved'));
    }

    public function editHabit(string $mode = ''): void
    {
        $this->assertOwner();
        $habit = $this->plan->habit;
        $this->habitMode = in_array($mode, ['build', 'replace'], true) ? $mode : ($habit->mode ?? 'build');
        $this->habitCue = (string) $habit?->cue;
        $this->habitReward = (string) $habit?->reward;
        $this->habitWhy = (string) $habit?->why;
        $this->habitReplaces = (string) $habit?->replaces;
        $this->habitEditing = true;
    }

    public function saveHabit(ManageHabit $habits): void
    {
        $this->validate([
            'habitMode' => ['required', 'in:build,replace'],
            'habitCue' => ['nullable', 'string', 'max:300'],
            'habitReward' => ['nullable', 'string', 'max:300'],
            'habitWhy' => ['nullable', 'string', 'max:500'],
            'habitReplaces' => ['nullable', 'required_if:habitMode,replace', 'string', 'max:300'],
        ], [], ['habitReplaces' => __('habits.loop.replaces')]);
        $first = $this->plan->habit === null;
        $habits->saveLoop($this->plan, $this->user(), [
            'mode' => $this->habitMode, 'cue' => $this->habitCue, 'reward' => $this->habitReward,
            'why' => $this->habitWhy, 'replaces' => $this->habitReplaces,
        ]);
        $this->habitEditing = false;
        $this->plan->unsetRelation('habit');
        // A new habit starts its first period right away, so the days begin to count.
        if ($first && $this->plan->periods()->doesntExist()) {
            $this->openPeriodForm();
        }
    }

    public function openPeriodForm(): void
    {
        $this->assertOwner();
        $this->periodForm = true;
        $this->periodVerdict = 'adjust';
        $this->periodReflection = '';
        $this->periodAutomaticity = [null, null, null, null];
        $current = $this->currentPeriod();
        $this->nextFocus = $current === null ? (string) __('habits.periods.first_focus') : '';
        $this->nextTarget = (string) ($current->target_time ?? '');
        $this->nextDays = 21;
    }

    public function closePeriodForm(): void
    {
        $this->periodForm = false;
        $this->resetErrorBag();
    }

    /** End the current period with the person's verdict and start the next one with one thing changed. */
    public function savePeriod(ManageHabit $habits): void
    {
        $this->validate([
            'nextFocus' => ['required', 'string', 'max:300'],
            'nextTarget' => ['nullable', 'date_format:H:i'],
            'nextDays' => ['required', 'in:'.implode(',', PlanPeriod::LENGTHS)],
            'periodVerdict' => ['required', 'in:'.implode(',', PlanPeriod::VERDICTS)],
            'periodReflection' => ['nullable', 'string', 'max:2000'],
            'periodAutomaticity.*' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], [], ['nextFocus' => __('habits.periods.focus')]);

        $user = $this->user();
        $current = $this->currentPeriod();
        $today = CarbonImmutable::now(TemporalPreferences::timezoneFor($user))->toDateString();
        if ($current !== null && $current->starts_on->toDateString() < $today) {
            $habits->finishPeriod($current, $user, $this->periodVerdict, $this->periodReflection, $this->answeredAutomaticity(), endYesterday: true);
        }
        $habits->startPeriod($this->plan, $user, $this->nextFocus, $this->nextTarget, (int) $this->nextDays);
        $this->periodForm = false;
        $this->boardUuid = '';
        $this->plan->unsetRelation('periods');
        session()->now('planner_status', __('habits.periods.started'));
    }

    /** Finish the current (or an ended, unanswered) period without starting another. */
    public function finishPeriod(ManageHabit $habits): void
    {
        $this->validate([
            'periodVerdict' => ['required', 'in:'.implode(',', PlanPeriod::VERDICTS)],
            'periodReflection' => ['nullable', 'string', 'max:2000'],
            'periodAutomaticity.*' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);
        $current = $this->currentPeriod();
        abort_unless($current instanceof PlanPeriod, 422);
        $habits->finishPeriod($current, $this->user(), $this->periodVerdict, $this->periodReflection, $this->answeredAutomaticity());
        $this->periodForm = false;
        $this->plan->unsetRelation('periods');
        session()->now('planner_status', __('habits.periods.finished'));
    }

    public function logUrge(string $outcome, ManageHabit $habits): void
    {
        $habits->logUrge($this->plan, $this->user(), $outcome, $this->urgeStrength, null);
        $this->urgeStrength = null;
        session()->now('planner_status', __('habits.urges.logged_'.($outcome === 'replaced' ? 'replaced' : 'gave_in')));
    }

    public function undoUrge(string $uuid, ManageHabit $habits): void
    {
        $habits->undoUrge(PlanUrge::query()->where('plan_id', $this->plan->id)->where('uuid', $uuid)->firstOrFail(), $this->user());
    }

    public function showBoard(string $periodUuid): void
    {
        abort_unless($periodUuid === '' || $this->plan->periods()->where('uuid', $periodUuid)->exists(), 404);
        $this->boardUuid = $periodUuid;
    }

    /** @return ?list<int> */
    private function answeredAutomaticity(): ?array
    {
        $answers = array_map(fn ($value) => $value === null || $value === '' ? null : (int) $value, array_values($this->periodAutomaticity));

        return in_array(null, $answers, true) ? null : $answers;
    }

    private function currentPeriod(): ?PlanPeriod
    {
        return PlanPeriod::query()->where('plan_id', $this->plan->id)->whereNull('verdict')->orderByDesc('number')->first();
    }

    private function isOwner(): bool
    {
        return (int) $this->plan->created_by_actor_id === (int) $this->user()->actor?->id;
    }

    private function assertOwner(): void
    {
        abort_unless($this->isOwner(), 403);
    }

    /**
     * The habit part of the page: loop, periods, the expected-versus-actual
     * board and, for a habit being replaced, the urge figures.
     *
     * @return array<string, mixed>
     */
    private function habitData(Plan $plan, User $user): array
    {
        $owner = $this->isOwner();
        $repeats = PlanSeries::repeats($plan);
        $habit = $owner ? $plan->habit : null;
        $replaces = $habit?->replaces() ?? false;
        if (! $owner || (! $repeats && $habit === null)) {
            return ['showHabit' => false, 'habit' => null, 'replaces' => false, 'repeats' => $repeats, 'isOwner' => $owner];
        }

        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now('UTC');
        $today = $now->setTimezone($timezone)->toDateString();
        $periods = $plan->periods()->get();
        $plan->setRelation('periods', $periods);
        $current = $periods->first(fn (PlanPeriod $period): bool => $period->verdict === null);
        $board = $this->boardUuid !== '' ? $periods->firstWhere('uuid', $this->boardUuid) : $current;
        $board ??= $periods->last();

        if ($board instanceof PlanPeriod) {
            $from = $board->starts_on->toDateString();
            $to = $board->lastDay()->toDateString();
        } else {
            $from = CarbonImmutable::parse($today)->subDays(20)->toDateString();
            $to = $today;
        }

        $progress = $repeats ? RoutineProgress::between($plan, $from, $to, $board, $now) : ['rows' => [], 'summary' => []];

        return [
            'showHabit' => true,
            'isOwner' => $owner,
            'repeats' => $repeats,
            'habit' => $habit,
            'replaces' => $replaces,
            'periods' => $periods,
            'currentPeriod' => $current,
            'boardPeriod' => $board,
            'boardFrom' => $from,
            'boardTo' => $to,
            'progress' => $progress,
            'comparison' => $periods->count() >= 2 ? RoutineProgress::periods($plan, $replaces, $now) : [],
            'periodEnded' => $current instanceof PlanPeriod && $current->ends_on->toDateString() < $today,
            'periodDay' => $current instanceof PlanPeriod ? min($current->days(), (int) $current->starts_on->diffInDays(CarbonImmutable::parse($today)) + 1) : null,
            'urgesToday' => $replaces ? RoutineProgress::urges($plan, $today, $today) : null,
            'urgesBoard' => $replaces ? RoutineProgress::urges($plan, $from, $to) : null,
            'lastUrge' => $replaces ? PlanUrge::query()->where('plan_id', $plan->id)->where('created_at', '>', now()->subMinutes(10))->latest('id')->first() : null,
            'today' => $today,
        ];
    }

    public function render(): View
    {
        $user = $this->user();

        if ((string) config('release.profile') === 'planning_baseline') {
            return $this->renderBaseline($user);
        }

        $plan = Plan::query()
            ->with([
                'context.assets',
                'domainBlueprintVersion.blueprint',
                'creator.user',
                'participants.actor.user',
                'scheduleRules.reminders',
                'prerequisites',
                'expenseEstimates.monetaryUnit',
                'events.actor.user',
                'habit',
            ])
            ->findOrFail($this->plan->id);

        // A long series is shown around now (recent past and the next weeks), not all at once.
        $nowInstant = CarbonImmutable::now('UTC');
        $occurrences = $plan->occurrences()
            ->with([
                'plan', 'scheduleRule',
                'events.actor.user',
                'prerequisiteChecks.prerequisite',
                'prerequisiteChecks.completedBy.user',
                'expenses.estimate',
                'expenses.monetaryUnit',
                'expenses.creator.user',
                'assets',
                'evidenceReferences.content.activeRevision',
                'evidenceReferences.revision',
            ])
            ->where(function ($query) use ($nowInstant): void {
                $query->whereBetween('scheduled_start_at', [$nowInstant->subDays(30), $nowInstant->addDays(45)])
                    ->orWhereIn('status', [PlanOccurrenceStatus::InProgress->value]);
                if ($this->focusedOccurrenceId !== null) {
                    $query->orWhere('id', $this->focusedOccurrenceId);
                }
            })
            ->orderBy('scheduled_start_at')
            ->limit(160)
            ->get();
        if ($occurrences->isEmpty()) {
            $occurrences = $plan->occurrences()->with(['plan', 'scheduleRule', 'events.actor.user', 'prerequisiteChecks', 'expenses.monetaryUnit', 'assets', 'evidenceReferences'])
                ->orderByDesc('scheduled_start_at')->limit(10)->get()->sortBy('scheduled_start_at')->values();
        }
        $plan->setRelation('occurrences', $occurrences);

        Gate::forUser($user)->authorize('view', $plan);
        $this->plan = $plan;

        $canManage = Gate::forUser($user)->allows('manage', $plan);
        $canParticipate = Gate::forUser($user)->allows('participate', $plan);
        $canUploadEvidence = $canParticipate
            && Gate::forUser($user)->allows('submitInteractions', $plan->context);

        $references = $canParticipate
            ? ContentEvidenceReference::query()
                ->with(['content.activeRevision', 'revision'])
                ->where('context_id', $plan->context_id)
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();

        $evidenceActorIds = $plan->occurrences
            ->flatMap(fn (PlanOccurrence $occurrence) => $occurrence->assets->pluck('pivot.added_by_actor_id'))
            ->filter()
            ->unique()
            ->values();
        $evidenceActors = $evidenceActorIds->isEmpty()
            ? collect()
            : Actor::query()->with(['user', 'profile'])->whereIn('id', $evidenceActorIds)->get()->keyBy('id');

        $originRelationship = $plan->origin_type === 'relationship' && $plan->origin_uuid !== null
            ? Relationship::query()->where('uuid', $plan->origin_uuid)->first()
            : null;
        $originCommitment = $plan->origin_type === 'commitment' && $plan->origin_uuid !== null
            ? Commitment::query()->where('uuid', $plan->origin_uuid)->first()
            : null;

        $appearance = PlanAppearance::forActor($user->actor?->id);

        return view('livewire.planner.show', [
            ...$this->habitData($plan, $user),
            'look' => $appearance->of($plan),
            'labels' => $appearance->labels(),
            'canManage' => $canManage,
            'canParticipate' => $canParticipate,
            'canUploadEvidence' => $canUploadEvidence,
            'availableAssets' => $canParticipate ? $plan->context->assets : collect(),
            'availableEvidenceReferences' => $references,
            'evidenceActors' => $evidenceActors,
            'assetRightsStatuses' => Asset::RIGHTS_STATUSES,
            'originRelationship' => $originRelationship,
            'originCommitment' => $originCommitment,
            'unitCatalog' => MonetaryUnitCatalog::all(),
        ])->title($plan->title);
    }

    private function renderBaseline(User $user): View
    {
        $plan = Plan::query()
            ->with([
                'context',
                'creator.user',
                'scheduleRules',
                'occurrences.scheduleRule',
            ])
            ->findOrFail($this->plan->id);

        Gate::forUser($user)->authorize('view', $plan);
        abort_unless(data_get($plan->metadata, 'planning_studio') === 'baseline', 404);
        $this->plan = $plan;

        return view('livewire.planner.basic-show', [
            'canManage' => Gate::forUser($user)->allows('manage', $plan),
            'canParticipate' => Gate::forUser($user)->allows('participate', $plan),
        ])->title($plan->title);
    }

    private function occurrence(int $occurrenceId): PlanOccurrence
    {
        return PlanOccurrence::query()
            ->where('plan_id', $this->plan->id)
            ->findOrFail($occurrenceId);
    }

    private function prerequisite(int $prerequisiteId): PlanPrerequisite
    {
        return PlanPrerequisite::query()
            ->where('plan_id', $this->plan->id)
            ->findOrFail($prerequisiteId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
