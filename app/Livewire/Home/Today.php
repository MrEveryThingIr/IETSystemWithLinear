<?php

namespace App\Livewire\Home;

use App\Models\User;
use App\Support\HomeTodayProjection;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Today extends Component
{
    public function render(HomeTodayProjection $projection): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.home.today', $projection->build($user))
            ->title(__('home.title'));
    }
}
