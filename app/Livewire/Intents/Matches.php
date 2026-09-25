<?php

namespace App\Livewire\Intents;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\Support\IntentMatchFinder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Intent matches')]
class Matches extends Component
{
    #[Locked]
    public ActorProfileIntent $intent;

    public function mount(ActorProfileIntent $intent): void
    {
        $user = $this->user();
        abort_unless($user->can('update', $intent), 403);
        $this->intent = $intent;
    }

    public function render(IntentMatchFinder $matches): View
    {
        $this->intent->loadMissing(['concept.labels', 'profile.actor.user']);

        return view('livewire.intents.matches', [
            'matches' => $matches->find($this->user(), $this->intent),
        ]);
    }

    private function user(): User
    {
        $user = request()->user();

        abort_unless(
            $user instanceof User
            && $user->actor instanceof Actor
            && $user->status === 'active'
            && $user->email_verified_at !== null
            && $user->actor->status === 'active',
            403,
        );

        return $user;
    }
}
