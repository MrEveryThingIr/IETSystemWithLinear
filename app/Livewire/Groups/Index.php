<?php

namespace App\Livewire\Groups;

use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Groups')]
class Index extends Component
{
    public function render(): View
    {
        $actor = auth()->user()->actor;

        return view('livewire.groups.index', [
            'memberships' => GroupMembership::query()
                ->with('group')
                ->where('actor_id', $actor->id)
                ->where('status', 'active')
                ->latest()
                ->get(),
        ]);
    }
}
