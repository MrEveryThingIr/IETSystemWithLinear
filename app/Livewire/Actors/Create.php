<?php

namespace App\Livewire\Actors;

use App\Models\Actor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Actor')]
class Create extends Component
{
    public function save(): void
    {
        Gate::authorize('create', Actor::class);

        $actor = new Actor;
        $actor->save();

        session()->flash('status', __('ui.messages.actor_created'));
        $this->redirectRoute('actors.show', ['actor' => $actor->id]);
    }

    public function render(): View
    {
        Gate::authorize('create', Actor::class);

        return view('livewire.actors.create');
    }
}
