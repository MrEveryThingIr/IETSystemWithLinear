<?php

namespace App\Livewire\Planner;

use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Actor;
use App\Models\ContentEvidenceReference;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\Relationship;
use App\Models\User;
use App\PlanStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
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

    public function render(): View
    {
        $user = $this->user();

        $plan = Plan::query()
            ->with([
                'context.assets',
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

        return view('livewire.planner.show', [
            'canManage' => $canManage,
            'canParticipate' => $canParticipate,
            'availableAssets' => $canParticipate ? $plan->context->assets : collect(),
            'availableEvidenceReferences' => $references,
            'originRelationship' => $originRelationship,
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
