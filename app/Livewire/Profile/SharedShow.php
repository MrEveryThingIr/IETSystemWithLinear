<?php

namespace App\Livewire\Profile;

use App\Models\ActorProfileDisclosureGrant;
use App\Models\User;
use App\Support\Profile\ProfileDisclosureResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Shared profile')]
class SharedShow extends Component
{
    #[Locked]
    public ActorProfileDisclosureGrant $grant;

    public function mount(ActorProfileDisclosureGrant $grant): void
    {
        $this->grant = $grant;
    }

    public function render(ProfileDisclosureResolver $resolver): View
    {
        Gate::authorize('view', $this->grant);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.profile.shared-show', [
            'grant' => $this->grant->load(['profile.actor.user', 'grantee']),
            'disclosure' => $resolver->resolve($user, $this->grant),
        ]);
    }
}
