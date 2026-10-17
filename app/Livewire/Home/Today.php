<?php

namespace App\Livewire\Home;

use App\Models\User;
use App\Support\HomeGuidanceService;
use App\Support\HomeTodayProjection;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Today')]
class Today extends Component
{
    /** Today renders a bounded preview; the full day is only rendered on request. */
    public bool $showFullSchedule = false;

    public function toggleFullSchedule(): void
    {
        $this->showFullSchedule = ! $this->showFullSchedule;
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
        ]);
    }
}
