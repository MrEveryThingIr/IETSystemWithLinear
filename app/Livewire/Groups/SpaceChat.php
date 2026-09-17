<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\PostGroupSpaceMessage;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\GroupSpaceMessage;
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

    public ?int $replyToMessageId = null;

    public function replyTo(int $messageId): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        Gate::forUser($user)->authorize('view', $this->space);
        abort_unless($this->space->messages()->whereKey($messageId)->exists(), 404);

        $this->replyToMessageId = $messageId;
    }

    public function cancelReply(): void
    {
        $this->replyToMessageId = null;
    }

    public function mount(Group $group, GroupSpace $space): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless($space->kind === 'chat', 404);

        $user = request()->user();
        abort_unless($user instanceof User, 403);
        Gate::forUser($user)->authorize('view', $space);

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

        $postMessage->execute($this->space, $user, $data['message'], $this->replyToMessageId);
        $this->reset('message');
        $this->replyToMessageId = null;
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        /** @var GroupSpace $currentSpace */
        $currentSpace = GroupSpace::query()->with('group')->findOrFail($this->space->id);
        abort_unless((int) $currentSpace->group_id === (int) $this->group->id, 404);
        Gate::forUser($user)->authorize('view', $currentSpace);

        $this->space = $currentSpace;

        $messages = $currentSpace->messages()
            ->with(['author.user', 'replyTo.author.user'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        $replyToMessage = $this->replyToMessageId !== null
            ? $currentSpace->messages()->with('author.user')->whereKey($this->replyToMessageId)->first()
            : null;
        if (! $replyToMessage instanceof GroupSpaceMessage) {
            $this->replyToMessageId = null;
        }

        return view('livewire.groups.space-chat', compact('messages', 'replyToMessage'));
    }
}
