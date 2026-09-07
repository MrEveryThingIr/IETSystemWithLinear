<?php

namespace App\Livewire\Actors;

use App\Models\Actor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Actors')]
class Index extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('livewire.actors.index', [
            'actors' => Actor::with('user')->latest('id')->paginate(15),
        ]);
    }
}
