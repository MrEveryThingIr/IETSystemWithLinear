<?php

namespace App\Actions\Conversations;

use App\Models\ConversationMessage;
use App\Models\ConversationMessageRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Authors may edit or delete their own messages. The original message row is
 * never changed; a revision is appended and readers show the latest one.
 */
class ReviseConversationMessage
{
    public function edit(ConversationMessage $message, User $user, string $body): ConversationMessageRevision
    {
        $body = trim($body);
        abort_if($body === '' || mb_strlen($body) > 4000, 422, 'A message must contain between 1 and 4000 characters.');

        return $this->append($message, $user, ConversationMessageRevision::KIND_EDIT, $body);
    }

    public function delete(ConversationMessage $message, User $user): ConversationMessageRevision
    {
        return $this->append($message, $user, ConversationMessageRevision::KIND_DELETE, null);
    }

    private function append(ConversationMessage $message, User $user, string $kind, ?string $body): ConversationMessageRevision
    {
        return DB::transaction(function () use ($message, $user, $kind, $body): ConversationMessageRevision {
            /** @var ConversationMessage $current */
            $current = ConversationMessage::query()->with(['author', 'latestRevision'])->lockForUpdate()->findOrFail($message->id);
            $actorId = $user->actor?->id;

            abort_unless($actorId !== null && (int) $current->author_actor_id === (int) $actorId, 403);
            abort_if($current->isDeleted(), 422, 'This message has been deleted.');

            return ConversationMessageRevision::query()->create([
                'conversation_message_id' => $current->id,
                'actor_id' => $actorId,
                'kind' => $kind,
                'body' => $body,
                'created_at' => now(),
            ]);
        });
    }
}
