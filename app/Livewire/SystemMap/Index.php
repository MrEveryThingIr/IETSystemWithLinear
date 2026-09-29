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
    public function render(): View
    {
        return view('livewire.system-map.index', [
            'map' => app(SystemMapBuilder::class)->build(),
            'initialLens' => request()->query('lens'),
        ]);
    }
}
