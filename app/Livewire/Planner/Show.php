<?php

namespace App\Livewire\Planner;

use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\RecordPlanOccurrenceExpense;
use App\Actions\Planner\ReplicateCompletedOccurrenceToDates;
use App\Actions\Planner\SetPlanOccurrencePrerequisite;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\ContentEvidenceReference;
use App\Models\Plan;
use App\Models\PlanExpenseEstimate;
use App\Models\PlanOccurrence;
use App\Models\PlanPrerequisite;
use App\Models\Relationship;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanStatus;
use App\Support\MonetaryUnitCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Plan')]
class Show extends Component
{
    public Plan $plan;

    public ?int $evidenceOccurrenceId = null;

    /** @var list<int> */
    public array $assetIds = [];

    /** @var list<int> */
    public array $evidenceReferenceIds = [];

    public ?int $expenseOccurrenceId = null;

    public string $expenseEstimateId = '';

    public string $expenseLabel = '';

    public string $expenseAmount = '';

    public string $expenseUnitCode = 'EUR';

    public string $expenseNote = '';

    public bool $repeatToolOpen = false;

    public ?int $repeatSourceOccurrenceId = null;

    public string $repeatMode = 'next_days';

    public int $repeatCount = 21;

    public string $repeatMonth = '';

    public string $repeatDate = '';

    /** @var list<string> */
    public array $repeatDates = [];

    public string $repeatMessage = '';

    public function mount(Plan $plan): void
    {
        Gate::forUser($this->user())->authorize('view', $plan);
        $this->plan = $plan;
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

    public function openRepeatTool(int $occurrenceId): void
    {
        $occurrence = $this->occurrence($occurrenceId);
        Gate::forUser($this->user())->authorize('manage', $occurrence->plan);

        abort_unless($occurrence->status === PlanOccurrenceStatus::Completed, 422);

        $this->repeatSourceOccurrenceId = $occurrence->id;
        $this->repeatMode = 'next_days';
        $this->repeatCount = 21;
        $this->repeatMonth = CarbonImmutable::now($this->plan->timezone)->addMonth()->format('Y-m');
        $this->repeatDate = '';
        $this->repeatDates = [];
        $this->repeatMessage = '';
        $this->repeatToolOpen = true;
        $this->resetValidation();
    }

    public function closeRepeatTool(): void
    {
        $this->repeatToolOpen = false;
        $this->repeatSourceOccurrenceId = null;
        $this->repeatDate = '';
        $this->repeatDates = [];
        $this->resetValidation();
    }

    public function addRepeatDate(): void
    {
        $data = $this->validate([
            'repeatDate' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $data['repeatDate'];

        if (! in_array($date, $this->repeatDates, true)) {
            $this->repeatDates[] = $date;
            sort($this->repeatDates);
        }

        $this->repeatDate = '';
        $this->resetValidation('repeatDate');
    }

    public function removeRepeatDate(string $date): void
    {
        $this->repeatDates = array_values(array_filter(
            $this->repeatDates,
            fn (string $candidate): bool => $candidate !== $date,
        ));
    }

    public function applyRepeatTool(ReplicateCompletedOccurrenceToDates $repeat): void
    {
        $source = $this->repeatSourceOccurrence();
        $dates = $this->repeatTargetDates($source);

        $copies = $repeat->execute($source, $this->user(), $dates);

        $this->repeatMessage = trans_choice(
            'planning_baseline.tools.repeat.created',
            $copies->count(),
            ['count' => $copies->count()],
        );
        $this->repeatToolOpen = false;
        $this->repeatSourceOccurrenceId = null;
        $this->repeatDate = '';
        $this->repeatDates = [];
        $this->resetValidation();
    }

    public function startOccurrence(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $transition->start($this->occurrence($occurrenceId), $this->user());
    }

    public function completeOccurrence(int $occurrenceId, TransitionPlanOccurrence $transition): void
    {
        $transition->complete($this->occurrence($occurrenceId), $this->user());
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
        $this->reset(['assetIds', 'evidenceReferenceIds']);
    }

    public function attachEvidence(AttachPlanOccurrenceEvidence $attach): void
    {
        abort_unless($this->evidenceOccurrenceId !== null, 422);

        $attach->execute(
            $this->occurrence($this->evidenceOccurrenceId),
            $this->user(),
            $this->assetIds,
            $this->evidenceReferenceIds,
        );

        $this->reset(['evidenceOccurrenceId', 'assetIds', 'evidenceReferenceIds']);
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
                'occurrences.events.actor.user',
                'occurrences.prerequisiteChecks.prerequisite',
                'occurrences.prerequisiteChecks.completedBy.user',
                'occurrences.expenses.estimate',
                'occurrences.expenses.monetaryUnit',
                'occurrences.expenses.creator.user',
                'occurrences.assets',
                'occurrences.evidenceReferences.content.activeRevision',
                'occurrences.evidenceReferences.revision',
                'events.actor.user',
            ])
            ->findOrFail($this->plan->id);

        Gate::forUser($user)->authorize('view', $plan);
        $this->plan = $plan;

        $canManage = Gate::forUser($user)->allows('manage', $plan);
        $canParticipate = Gate::forUser($user)->allows('participate', $plan);

        $references = $canParticipate
            ? ContentEvidenceReference::query()
                ->with(['content.activeRevision', 'revision'])
                ->where('context_id', $plan->context_id)
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();

        $originRelationship = $plan->origin_type === 'relationship' && $plan->origin_uuid !== null
            ? Relationship::query()->where('uuid', $plan->origin_uuid)->first()
            : null;
        $originCommitment = $plan->origin_type === 'commitment' && $plan->origin_uuid !== null
            ? Commitment::query()->where('uuid', $plan->origin_uuid)->first()
            : null;

        return view('livewire.planner.show', [
            'canManage' => $canManage,
            'canParticipate' => $canParticipate,
            'availableAssets' => $canParticipate ? $plan->context->assets : collect(),
            'availableEvidenceReferences' => $references,
            'originRelationship' => $originRelationship,
            'originCommitment' => $originCommitment,
            'unitCatalog' => MonetaryUnitCatalog::all(),
        ]);
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

    private function repeatSourceOccurrence(): PlanOccurrence
    {
        if ($this->repeatSourceOccurrenceId === null) {
            throw ValidationException::withMessages([
                'repeatMode' => __('planning_baseline.tools.repeat.choose_source'),
            ]);
        }

        return $this->occurrence($this->repeatSourceOccurrenceId);
    }

    /**
     * @return list<string>
     */
    private function repeatTargetDates(PlanOccurrence $source): array
    {
        $today = CarbonImmutable::now($this->plan->timezone)->startOfDay();

        if ($this->repeatMode === 'next_days') {
            $this->validate([
                'repeatCount' => ['required', 'integer', 'min:1', 'max:62'],
            ]);

            return collect(range(1, $this->repeatCount))
                ->map(fn (int $offset): string => $today->addDays($offset)->format('Y-m-d'))
                ->all();
        }

        if ($this->repeatMode === 'same_weekday') {
            $this->validate([
                'repeatCount' => ['required', 'integer', 'min:1', 'max:52'],
            ]);

            $weekday = $source->local_date->isoWeekday();
            $cursor = $today;
            $dates = [];

            while (count($dates) < $this->repeatCount) {
                $cursor = $cursor->addDay();

                if ($cursor->isoWeekday() === $weekday) {
                    $dates[] = $cursor->format('Y-m-d');
                }
            }

            return $dates;
        }

        if ($this->repeatMode === 'month') {
            $this->validate([
                'repeatMonth' => ['required', 'date_format:Y-m'],
            ]);

            $start = CarbonImmutable::createFromFormat('!Y-m', $this->repeatMonth, $this->plan->timezone);
            $end = $start->endOfMonth();

            $dates = [];
            for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
                if ($cursor->greaterThan($today)) {
                    $dates[] = $cursor->format('Y-m-d');
                }
            }

            if ($dates === []) {
                throw ValidationException::withMessages([
                    'repeatMonth' => __('planning_baseline.tools.repeat.future_month_required'),
                ]);
            }

            return $dates;
        }

        if ($this->repeatMode === 'selected_dates') {
            $this->validate([
                'repeatDates' => ['required', 'array', 'min:1', 'max:62'],
                'repeatDates.*' => ['required', 'date_format:Y-m-d'],
            ], [
                'repeatDates.required' => __('planning_baseline.tools.repeat.choose_dates'),
                'repeatDates.min' => __('planning_baseline.tools.repeat.choose_dates'),
            ]);

            foreach ($this->repeatDates as $date) {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->plan->timezone);

                if (! $parsed->greaterThan($today)) {
                    throw ValidationException::withMessages([
                        'repeatDate' => __('planning_baseline.tools.repeat.future_only'),
                    ]);
                }
            }

            return $this->repeatDates;
        }

        throw ValidationException::withMessages([
            'repeatMode' => __('planning_baseline.tools.repeat.invalid_mode'),
        ]);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
