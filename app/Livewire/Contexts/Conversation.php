<?php

namespace App\Livewire\Contexts;

use App\Models\Context;
use App\Models\Conversation as ConversationModel;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Messaging\ThreadFor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Legacy destination of every "Conversation" link. It no longer has a composer of its own: a Deal's conversation
 * lives in the Deal workspace, and every other context shows the same shared Thread the Messages inbox uses.
 */
#[Layout('layouts.app')]
#[Title('Conversation')]
class Conversation extends Component
{
    public Context $context;

    public function mount(Context $context): mixed
    {
        Gate::forUser($this->user())->authorize('view', $context);
        $this->context = $context;

        $context->loadMissing('relationshipBinding.relationship');
        $deal = $context->relationshipBinding?->relationship;
        if ($deal instanceof Relationship) {
            return $this->redirectRoute('relationships.show', $deal);
        }

        return null;
    }

    public function render(): View
    {
        $conversation = ThreadFor::conversation($this->context, $this->user());
        assert($conversation instanceof ConversationModel);

        return view('livewire.contexts.conversation', ['conversation' => $conversation]);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
