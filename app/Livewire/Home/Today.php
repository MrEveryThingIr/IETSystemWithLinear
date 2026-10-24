<?php

namespace App\Livewire\Home;

use App\Actions\Planner\ManageHabit;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Livewire\Planner\Concerns\ReportsOccurrences;
use App\Models\Plan;
use App\Models\User;
use App\Support\HomeGuidanceService;
use App\Support\HomeTodayProjection;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Today extends Component
{
    use ReportsOccurrences;

    /** Today renders a bounded preview; the full day is only rendered on request. */
    public bool $showFullSchedule = false;

    public function toggleFullSchedule(): void
    {
        $this->showFullSchedule = ! $this->showFullSchedule;
    }

    public function markDone(string $occurrenceUuid, TransitionPlanOccurrence $transition): void
    {
        $this->quickDone($occurrenceUuid, $transition);
    }

    /** The urge buttons of a habit being replaced, right on Today. */
    public function logUrge(string $planUuid, string $outcome, ManageHabit $habits): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $plan = Plan::query()->where('uuid', $planUuid)->firstOrFail();
        $habits->logUrge($plan, $user, $outcome, null, null);
        session()->now('planner_status', __('habits.urges.logged_'.($outcome === 'replaced' ? 'replaced' : 'gave_in')));
    }

    public function render(
        HomeTodayProjection $projection,
        HomeGuidanceService $guidance,
    ): View {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $today = $projection->build($user);

        return view('livewire.home.today', [
            ...$today,
            ...$guidance->build($user, $today),
        ])->title(__('home.title'));
    }
}
