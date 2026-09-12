<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\PostGroupSpaceMessage;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Group chat')]
class SpaceChat extends Component
{
    public Group $group;

    public GroupSpace $space;

    public string $message = '';

    public function mount(Group $group, GroupSpace $space): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless($space->status === 'active' && $space->kind === 'chat', 404);
        Gate::authorize('view', $group);

        $this->group = $group;
        $this->space = $space;
    }

    public function send(PostGroupSpaceMessage $postMessage): void
    {
        $data = $this->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $postMessage->execute($this->space, $user, $data['message']);
        $this->reset('message');
    }

    public function render(): View
    {
        Gate::authorize('view', $this->group);

        $spaces = $this->group->spaces()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $messages = $this->space->messages()
            ->with('author.user')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return view('livewire.groups.space-chat', compact('spaces', 'messages'));
    }
}
