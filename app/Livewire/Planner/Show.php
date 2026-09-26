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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    /** @var array<int, mixed> */
    public array $evidenceUploads = [];

    public string $evidenceRightsStatus = 'owned';

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

    public function chooseEvidenceOccurrence(int $occurrenceId): void
    {
        Gate::forUser($this->user())->authorize('participate', $this->plan);
        $this->occurrence($occurrenceId);
        $this->evidenceOccurrenceId = $occurrenceId;
        $this->reset(['assetIds', 'evidenceReferenceIds', 'evidenceUploads']);
        $this->evidenceRightsStatus = 'owned';
    }

    public function attachEvidence(
        AttachPlanOccurrenceEvidence $attach,
        CreateContextAsset $createAsset,
    ): void {
        abort_unless($this->evidenceOccurrenceId !== null, 422);

        $data = $this->validate([
            'assetIds' => ['array', 'max:20'],
            'assetIds.*' => ['integer'],
            'evidenceReferenceIds' => ['array', 'max:20'],
            'evidenceReferenceIds.*' => ['integer'],
            'evidenceUploads' => ['array', 'max:10'],
            'evidenceUploads.*' => ['file', 'max:12288'],
            'evidenceRightsStatus' => ['required', Rule::in(Asset::RIGHTS_STATUSES)],
        ]);

        $user = $this->user();
        $occurrence = $this->occurrence($this->evidenceOccurrenceId);
        $uploadedAssetIds = [];

        foreach ($data['evidenceUploads'] ?? [] as $upload) {
            $uploadedAssetIds[] = $createAsset->execute(
                $this->plan->context,
                $user,
                $upload,
                $data['evidenceRightsStatus'],
            )->id;
        }

        $assetIds = collect($data['assetIds'] ?? [])
            ->merge($uploadedAssetIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $referenceIds = collect($data['evidenceReferenceIds'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($assetIds === [] && $referenceIds === []) {
            $this->addError('evidence', __('planner.validation.evidence_required'));

            return;
        }

        $attach->execute(
            $occurrence,
            $user,
            $assetIds,
            $referenceIds,
        );

        session()->flash('status', __('planner.messages.evidence_attached'));
        $this->reset(['evidenceOccurrenceId', 'assetIds', 'evidenceReferenceIds', 'evidenceUploads']);
        $this->evidenceRightsStatus = 'owned';
    }

    public function render(): View
    {
        $user = $this->user();

        $plan = Plan::query()
            ->with([
                'context.assets',
                'domainBlueprintVersion.blueprint',
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
            'originRelationship' => $originRelationship,
            'originCommitment' => $originCommitment,
        ]);
    }

    private function occurrence(int $occurrenceId): PlanOccurrence
    {
        return PlanOccurrence::query()
            ->where('plan_id', $this->plan->id)
            ->findOrFail($occurrenceId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
