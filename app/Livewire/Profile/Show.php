<?php

namespace App\Livewire\Profile;

use App\Models\ActorProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Profile')]
class Show extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public function mount(ActorProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function render(): View
    {
        Gate::authorize('view', $this->profile);

        return view('livewire.profile.show', [
            'profile' => $this->profile->load([
                'actor.user',
                'displayImage.asset',
            ]),
        ]);
    }
}
