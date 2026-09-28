<?php

namespace App\Livewire\Home;

use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Workspace')]
class PlanningWorkspace extends Component
{
    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($timezone);

        $base = PlanOccurrence::query()
            ->with(['plan', 'scheduleRule'])
            ->whereHas('plan', fn ($plans) => $plans
                ->where('created_by_actor_id', $user->actor->id)
                ->whereNull('origin_type'));

        $today = (clone $base)
            ->whereBetween('scheduled_start_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])
            ->orderBy('scheduled_start_at')
            ->limit(12)
            ->get();

        $upcoming = (clone $base)
            ->where('scheduled_start_at', '>', $now->endOfDay()->utc())
            ->orderBy('scheduled_start_at')
            ->limit(8)
            ->get();

        $running = (clone $base)
            ->where('status', 'in_progress')
            ->orderBy('actual_start_at')
            ->limit(8)
            ->get();

        return view('livewire.home.planning-workspace', [
            'todayOccurrences' => $today,
            'upcomingOccurrences' => $upcoming,
            'runningOccurrences' => $running,
            'timezone' => $timezone,
        ]);
    }
}
