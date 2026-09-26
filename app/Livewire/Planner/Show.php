<?php

namespace App\Livewire\Planner;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Commitment;
use App\Models\ContentEvidenceReference;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\Relationship;
use App\Models\User;
use App\PlanStatus;
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
    use WithFileUploads;

    public Plan $plan;

    public ?int $evidenceOccurrenceId = null;

    /** @var list<int> */
    public array $assetIds = [];

    /** @var list<int> */
    public array $evidenceReferenceIds = [];

    public mixed $evidenceUpload = null;

    public string $evidenceUploadRightsStatus = 'private_study_only';

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
        Gate::forUser($this->user())->authorize('participate', $this->plan);

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
                $this->user(),
                $this->evidenceUpload,
                $this->evidenceUploadRightsStatus,
            );
            $assetIds[] = $asset->id;
        }

        $attach->execute($occurrence, $this->user(), $assetIds, $this->evidenceReferenceIds);
        $this->reset(['evidenceOccurrenceId', 'assetIds', 'evidenceReferenceIds', 'evidenceUpload']);
        $this->evidenceUploadRightsStatus = 'private_study_only';
        session()->flash('status', __('planner.messages.evidence_attached'));
    }

    public function render(): View
    {
        $user = $this->user();
        $plan = Plan::query()
            ->with([
                'domainBlueprintVersion.blueprint',
                'context',
                'creator.user',
                'participants.actor.user',
                'scheduleRules.reminders',
                'occurrences.events.actor.user',
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
        $canUploadEvidence = $canParticipate && Gate::forUser($user)->allows('submitInteractions', $plan->context);

        $assets = $canParticipate
            ? Asset::query()->where('context_id', $plan->context_id)->latest('id')->limit(50)->get()
            : collect();
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
            'availableAssets' => $assets,
            'availableEvidenceReferences' => $references,
            'assetRightsStatuses' => Asset::RIGHTS_STATUSES,
            'originRelationship' => $originRelationship,
            'originCommitment' => $originCommitment,
        ])->title($plan->title);
    }

    private function occurrence(int $occurrenceId): PlanOccurrence
    {
        return PlanOccurrence::query()->where('plan_id', $this->plan->id)->findOrFail($occurrenceId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
