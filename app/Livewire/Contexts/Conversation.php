<?php

namespace App\Livewire\Contexts;

use App\Actions\Conversations\PostContextMessage;
use App\ContextKind;
use App\Models\ContentEvidenceReference;
use App\Models\Context;
use App\Models\Conversation as ConversationModel;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Conversation')]
class Conversation extends Component
{
    public Context $context;

    public string $message = '';

    public ?int $replyToMessageId = null;

    /** @var list<int> */
    public array $assetIds = [];

    /** @var list<int> */
    public array $evidenceReferenceIds = [];

    public function mount(Context $context): void
    {
        Gate::forUser($this->user())->authorize('view', $context);
        $this->context = $context;
    }

    public function replyTo(int $messageId): void
    {
        Gate::forUser($this->user())->authorize('view', $this->context);

        abort_unless(
            ConversationMessage::query()
                ->whereKey($messageId)
                ->whereHas('conversation', fn ($query) => $query
                    ->where('context_id', $this->context->id)
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

    public function send(PostContextMessage $post): void
    {
        abort_unless($this->canPost(), 403);

        $this->validate([
            'message' => ['required', 'string', 'max:4000'],
            'assetIds' => ['array', 'max:10'],
            'assetIds.*' => ['integer'],
            'evidenceReferenceIds' => ['array', 'max:10'],
            'evidenceReferenceIds.*' => ['integer'],
        ]);

        $post->execute(
            $this->context,
            $this->user(),
            $this->message,
            $this->replyToMessageId,
            $this->assetIds,
            $this->evidenceReferenceIds,
        );

        $this->reset(['message', 'replyToMessageId', 'assetIds', 'evidenceReferenceIds']);
    }

    public function render(): View
    {
        $user = $this->user();
        Gate::forUser($user)->authorize('view', $this->context);

        $conversation = ConversationModel::query()
            ->where('context_id', $this->context->id)
            ->where('key', 'main')
            ->first();

        $messages = $conversation instanceof ConversationModel
            ? $conversation->messages()
                ->with([
                    'author.user',
                    'replyTo.author.user',
                    'assets',
                    'evidenceReferences.content.activeRevision',
                    'evidenceReferences.revision',
                ])
                ->latest('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values()
            : collect();

        $replyToMessage = $this->replyToMessageId !== null && $conversation instanceof ConversationModel
            ? $conversation->messages()
                ->with('author.user')
                ->whereKey($this->replyToMessageId)
                ->first()
            : null;

        if (! $replyToMessage instanceof ConversationMessage) {
            $this->replyToMessageId = null;
        }

        $canPost = $this->canPost();

        $assets = $canPost
            ? $this->context->assets()->latest('id')->limit(30)->get()
            : collect();

        $evidenceReferences = $canPost
            ? ContentEvidenceReference::query()
                ->with(['content.activeRevision', 'revision'])
                ->where('context_id', $this->context->id)
                ->latest('id')
                ->limit(30)
                ->get()
            : collect();

        return view('livewire.contexts.conversation', compact(
            'messages',
            'replyToMessage',
            'canPost',
            'assets',
            'evidenceReferences',
        ));
    }

    private function canPost(): bool
    {
        return in_array($this->context->kind, [
            ContextKind::GroupSpace,
            ContextKind::Admission,
            ContextKind::Relationship,
            ContextKind::Negotiation,
        ], true)
            && Gate::forUser($this->user())->allows('interactContent', $this->context);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
