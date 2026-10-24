<?php

namespace App\Livewire\Groups;

use App\Actions\Conversations\ReviseConversationMessage;
use App\Actions\Groups\PostGroupSpaceMessage;
use App\Actions\Messaging\ManageConversation;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
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

    public ?int $replyToMessageId = null;

    /** Shown inside the group home instead of as its own page. */
    public bool $embedded = false;

    public ?int $editingMessageId = null;

    public string $editingBody = '';

    public function replyTo(int $messageId): void
    {
        $user = $this->user();
        $context = $this->context();

        Gate::forUser($user)->authorize('view', $context);

        abort_unless(
            ConversationMessage::query()
                ->whereKey($messageId)
                ->whereHas('conversation', fn ($query) => $query
                    ->where('context_id', $context->id)
                    ->where('key', 'main'))
                ->exists(),
            404,
        );

        $this->replyToMessageId = $messageId;
    }

    public function cancelReply(): void
    {
        $this->replyToMessageId = null;
    }

    public function mount(Group $group, GroupSpace $space, bool $embedded = false): void
    {
        $this->embedded = $embedded;
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless($space->kind === 'chat', 404);

        $user = $this->user();
        Gate::forUser($user)->authorize('view', $space);

        $this->group = $group;
        $this->space = $space;
        $conversation = Conversation::query()->where('context_id', $this->context()->id)->where('key', 'main')->first();
        if ($conversation instanceof Conversation) {
            app(ManageConversation::class)->markRead($user, $conversation);
        }
    }

    public function send(PostGroupSpaceMessage $postMessage): void
    {
        $data = $this->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $postMessage->execute($this->space, $this->user(), $data['message'], $this->replyToMessageId);

        $this->reset('message');
        $this->replyToMessageId = null;
    }

    public function startEditing(int $messageId): void
    {
        $message = $this->ownMessage($messageId);
        $this->editingMessageId = $message->id;
        $this->editingBody = $message->currentBody();
    }

    public function cancelEditing(): void
    {
        $this->reset('editingMessageId', 'editingBody');
    }

    public function saveEdit(ReviseConversationMessage $revise): void
    {
        abort_unless($this->editingMessageId !== null, 422);
        $data = $this->validate(['editingBody' => ['required', 'string', 'max:4000']]);
        $revise->edit($this->ownMessage($this->editingMessageId), $this->user(), $data['editingBody']);
        $this->cancelEditing();
    }

    public function deleteMessage(int $messageId, ReviseConversationMessage $revise): void
    {
        $revise->delete($this->ownMessage($messageId), $this->user());

        if ($this->editingMessageId === $messageId) {
            $this->cancelEditing();
        }
    }

    public function render(): View
    {
        $user = $this->user();

        $currentSpace = GroupSpace::query()
            ->with(['group', 'contextBinding.context'])
            ->findOrFail($this->space->id);

        abort_unless((int) $currentSpace->group_id === (int) $this->group->id, 404);
        Gate::forUser($user)->authorize('view', $currentSpace);

        $this->space = $currentSpace;
        $context = $this->context();

        $conversation = Conversation::query()
            ->where('context_id', $context->id)
            ->where('key', 'main')
            ->first();

        $messages = $conversation instanceof Conversation
            ? $conversation->messages()
                ->with(['author.user', 'latestRevision', 'replyTo.author.user', 'replyTo.latestRevision'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values()
            : collect();

        $replyToMessage = $this->replyToMessageId !== null && $conversation instanceof Conversation
            ? $conversation->messages()
                ->with('author.user')
                ->whereKey($this->replyToMessageId)
                ->first()
            : null;

        if (! $replyToMessage instanceof ConversationMessage) {
            $this->replyToMessageId = null;
        }

        return view('livewire.groups.space-chat', compact('messages', 'replyToMessage'));
    }

    private function ownMessage(int $messageId): ConversationMessage
    {
        $user = $this->user();
        $context = $this->context();
        Gate::forUser($user)->authorize('view', $context);

        /** @var ConversationMessage $message */
        $message = ConversationMessage::query()
            ->with('latestRevision')
            ->whereKey($messageId)
            ->whereHas('conversation', fn ($query) => $query->where('context_id', $context->id)->where('key', 'main'))
            ->firstOrFail();
        abort_unless((int) $message->author_actor_id === (int) $user->actor?->id, 403);

        return $message;
    }

    private function context(): Context
    {
        $space = GroupSpace::query()
            ->with('contextBinding.context')
            ->findOrFail($this->space->id);

        $context = $space->contextBinding?->context;
        abort_unless($context instanceof Context, 404);

        return $context;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
