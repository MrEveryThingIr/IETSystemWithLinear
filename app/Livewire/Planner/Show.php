<?php

namespace App\Livewire\Planner;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\RecordPlanOccurrenceExpense;
use App\Actions\Planner\SetPlanOccurrencePrerequisite;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Actor;
use App\Models\Asset;
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
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
#[Title('Plan')]
class Show extends Component
{
    use WithFileUploads;

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
            'evidenceUpload' => ['nullable', 'file', 'max:12288'],
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

        $originRelationship = $plan->origin_type === 'relationship' && $plan->origin_uuid !== null
            ? Relationship::query()->where('uuid', $plan->origin_uuid)->first()
            : null;
        $originCommitment = $plan->origin_type === 'commitment' && $plan->origin_uuid !== null
            ? Commitment::query()->where('uuid', $plan->origin_uuid)->first()
            : null;

        return view('livewire.planner.show', [
            'canManage' => $canManage,
            'canParticipate' => $canParticipate,
            'canUploadEvidence' => $canUploadEvidence,
            'availableAssets' => $canParticipate ? $plan->context->assets : collect(),
            'availableEvidenceReferences' => $references,
            'assetRightsStatuses' => Asset::RIGHTS_STATUSES,
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

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
