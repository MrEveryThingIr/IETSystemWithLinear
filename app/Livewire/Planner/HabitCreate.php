<?php

namespace App\Livewire\Planner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\ManageHabit;
use App\Models\Actor;
use App\Models\Planner\PlanPeriod;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Replace an old habit: name it, choose what to do instead when the urge
 * comes, and how long to try it. One short form; everything else (other
 * replacements, comparing them) happens later on the plan page.
 */
#[Layout('layouts.app')]
class HabitCreate extends Component
{
    public string $replaces = '';

    public string $routine = '';

    public string $cue = '';

    public string $reward = '';

    public string $why = '';

    public int $days = 21;

    public function mount(): void
    {
        $this->user();
    }

    public function save(EnsurePersonalContext $personal, CreatePlan $createPlan, ManageHabit $habits): mixed
    {
        $this->validate([
            'replaces' => ['required', 'string', 'max:300'],
            'routine' => ['required', 'string', 'max:300'],
            'cue' => ['nullable', 'string', 'max:300'],
            'reward' => ['nullable', 'string', 'max:300'],
            'why' => ['nullable', 'string', 'max:500'],
            'days' => ['required', 'in:'.implode(',', PlanPeriod::LENGTHS)],
        ], [], [
            'replaces' => __('habits.loop.replaces'),
            'routine' => __('habits.create.routine'),
        ]);

        $user = $this->user();
        $context = $personal->execute($user);
        Gate::forUser($user)->authorize('interactContent', $context);

        $plan = DB::transaction(function () use ($user, $context, $createPlan, $habits) {
            $plan = $createPlan->execute(
                $context,
                $user,
                mb_substr((string) __('habits.create.plan_title', ['habit' => trim($this->replaces)]), 0, 180),
                null,
                metadata: ['kind' => 'routine', 'habit' => 'replace'],
            );
            $habits->saveLoop($plan, $user, ['mode' => 'replace', 'replaces' => $this->replaces, 'cue' => $this->cue, 'reward' => $this->reward, 'why' => $this->why]);
            $habits->startPeriod($plan, $user, $this->routine, null, (int) $this->days);

            return $plan;
        });

        return $this->redirectRoute('planner.show', $plan, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.planner.habit-create')->title(__('habits.create.title'));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
