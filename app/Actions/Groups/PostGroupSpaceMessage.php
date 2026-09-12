<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\GroupSpaceMessage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PostGroupSpaceMessage
{
    public function execute(GroupSpace $space, User $user, string $body): GroupSpaceMessage
    {
        $body = trim($body);
        abort_if($body === '', 422, 'A message cannot be empty.');
        abort_if(mb_strlen($body) > 4000, 422, 'A message may not be longer than 4000 characters.');

        /** @var GroupSpace $currentSpace */
        $currentSpace = GroupSpace::query()->with('group')->findOrFail($space->id);
        abort_unless($currentSpace->status === 'active', 422, 'This space is not active.');
        abort_unless($currentSpace->kind === 'chat', 422, 'This space does not accept chat messages.');

        Gate::forUser($user)->authorize('view', $currentSpace->group);

        $currentUser = $user->fresh();
        abort_unless($currentUser instanceof User && $currentUser->actor instanceof Actor, 403);

        return $currentSpace->messages()->create([
            'author_actor_id' => $currentUser->actor->id,
            'body' => $body,
        ]);
    }
}
