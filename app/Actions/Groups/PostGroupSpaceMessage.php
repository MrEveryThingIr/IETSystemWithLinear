<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\GroupSpaceMessage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PostGroupSpaceMessage
{
    public function execute(GroupSpace $space, User $user, string $body, ?int $replyToMessageId = null): GroupSpaceMessage
    {
        $normalizedBody = trim($body);
        abort_if($normalizedBody === '', 422, 'A message cannot be empty.');
        abort_if(mb_strlen($normalizedBody) > 4000, 422, 'A message may not be longer than 4000 characters.');

        /** @var GroupSpace $currentSpace */
        $currentSpace = GroupSpace::query()->with('group')->findOrFail($space->id);
        abort_unless($currentSpace->kind === 'chat', 422, 'This Space does not accept chat messages.');

        Gate::forUser($user)->authorize('post', $currentSpace);

        $currentUser = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($currentUser->actor instanceof Actor, 403);

        $replyTo = null;
        if ($replyToMessageId !== null) {
            $replyTo = $currentSpace->messages()->whereKey($replyToMessageId)->first();
            abort_unless($replyTo instanceof GroupSpaceMessage, 422, 'The message being replied to is not in this Space.');
        }

        return $currentSpace->messages()->create([
            'author_actor_id' => $currentUser->actor->id,
            'reply_to_message_id' => $replyTo?->id,
            'body' => $normalizedBody,
        ]);
    }
}
