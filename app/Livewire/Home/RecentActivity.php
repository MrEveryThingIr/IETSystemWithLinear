<?php

namespace App\Livewire\Home;

use App\Models\User;
use App\Support\HomeTodayProjection;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Recent activity on Today. It reads several timelines, so it loads after the
 * page has appeared and is not rebuilt when other parts of Today change.
 */
#[Lazy]
class RecentActivity extends Component
{
    public function placeholder(): View
    {
        return view('livewire.home.partials.recent-activity-placeholder');
    }

    public function render(HomeTodayProjection $projection): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.home.recent-activity', [
            'recentActivity' => $projection->recentActivity($user),
        ]);
    }
}
