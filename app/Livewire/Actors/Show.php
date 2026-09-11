<?php

namespace App\Livewire\Actors;

use App\Actions\Platform\ArchiveActor;
use App\Models\Actor;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Actor')]
class Show extends Component
{
    public string $archiveReason = '';

    #[Locked]
    public Actor $actor;

    public function archive(ArchiveActor $archiveActor): void
    {
        Gate::authorize('archive', $this->actor);

        $data = $this->validate([
            'archiveReason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $administrator = request()->user();
        abort_unless($administrator instanceof User, 403);

        $archiveActor->execute($this->actor, $administrator, $data['archiveReason']);

        session()->flash('status', __('ui.messages.actor_archived'));
        $this->redirectRoute('actors.index');
    }

    public function render(): View
    {
        Gate::authorize('view', $this->actor);

        return view('livewire.actors.show', ['actor' => $this->actor->load('user')]);
    }
}
