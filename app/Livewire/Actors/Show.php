<?php

namespace App\Livewire\Actors;

use App\Models\Actor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Actor')]
class Show extends Component
{
    #[Locked]
    public Actor $actor;

    public function delete(): void
    {
        $this->actor->delete();
        session()->flash('status', __('ui.messages.actor_deleted'));
        $this->redirectRoute('actors.index');
    }

    public function render(): View
    {
        return view('livewire.actors.show', ['actor' => $this->actor->load('user')]);
    }
}
