<?php

namespace App\Livewire\Contexts;

use App\Models\Context;
use App\Models\User;
use App\Support\ContextTimeline;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Timeline')]
class Timeline extends Component
{
    public Context $context;

    public function mount(Context $context): void
    {
        Gate::forUser($this->user())->authorize('view', $context);
        $this->context = $context;
    }

    public function render(ContextTimeline $timeline): View
    {
        $user = $this->user();
        Gate::forUser($user)->authorize('view', $this->context);

        return view('livewire.contexts.timeline', [
            'entries' => $timeline->entries($this->context, $user),
        ]);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
