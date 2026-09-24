<?php

namespace App\Actions\Groups;

use App\Actions\Conversations\PostContextMessage;
use App\Models\Context;
use App\Models\ConversationMessage;
use App\Models\GroupSpace;
use App\Models\User;

class PostGroupSpaceMessage
{
    public function __construct(private readonly PostContextMessage $messages) {}

    public function execute(
        GroupSpace $space,
        User $user,
        string $body,
        ?int $replyToMessageId = null,
    ): ConversationMessage {
        $currentSpace = GroupSpace::query()
            ->with('contextBinding.context')
            ->findOrFail($space->id);

        abort_unless($currentSpace->kind === 'chat', 422, 'This Space does not accept chat messages.');

        $context = $currentSpace->contextBinding?->context;
        abort_unless($context instanceof Context, 422, 'This Space has no Context binding.');

        return $this->messages->execute($context, $user, $body, $replyToMessageId);
    }
}
