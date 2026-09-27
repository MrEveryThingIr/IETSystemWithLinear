<?php

namespace App\Livewire\SystemMap;

use App\Support\SystemMap\SystemMapBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('System Map')]
class Index extends Component
{
    public function render(SystemMapBuilder $builder): View
    {
        return view('livewire.system-map.index', [
            'map' => $builder->build(),
            'initialLens' => request()->query('lens'),
        ]);
    }
}
