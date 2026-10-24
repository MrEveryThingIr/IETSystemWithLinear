<?php

namespace App\Actions\Messaging;

use App\Models\Actor;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageReaction;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** One person's handling of a conversation: read state, mute, archive, pin, reactions. */
class ManageConversation
{
    public function participant(User $user, Conversation $conversation): ConversationParticipant
    {
        $actor = $this->viewer($user, $conversation);

        return ConversationParticipant::query()->firstOrCreate(['conversation_id' => $conversation->id, 'actor_id' => $actor->id]);
    }

    /** Everything up to the newest message (or the given one) is read; also records that the person is looking now. */
    public function markRead(User $user, Conversation $conversation, ?int $upToMessageId = null): void
    {
        $participant = $this->participant($user, $conversation);
        $latest = (int) ConversationMessage::query()->where('conversation_id', $conversation->id)
            ->when($upToMessageId !== null, fn ($q) => $q->where('id', '<=', $upToMessageId))
            ->max('id');
        $participant->forceFill([
            'last_read_message_id' => max((int) $participant->last_read_message_id, $latest) ?: null,
            'last_seen_at' => now(),
        ])->save();
    }

    public function mute(User $user, Conversation $conversation, ?int $hours): void
    {
        $this->participant($user, $conversation)->forceFill([
            'muted_until' => $hours === null ? null : ($hours === 0 ? now()->addYears(50) : now()->addHours(max(1, min($hours, 24 * 30)))),
        ])->save();
    }

    public function archive(User $user, Conversation $conversation, bool $archived): void
    {
        $this->participant($user, $conversation)->forceFill(['archived_at' => $archived ? now() : null])->save();
    }

    public function pin(User $user, Conversation $conversation, bool $pinned): void
    {
        $this->participant($user, $conversation)->forceFill(['pinned_at' => $pinned ? now() : null])->save();
    }

    /** Adds the reaction, or removes it if the person already chose it. */
    public function react(User $user, ConversationMessage $message, string $emoji): void
    {
        abort_unless(in_array($emoji, ConversationMessageReaction::EMOJI, true), 422);
        $conversation = $message->conversation()->firstOrFail();
        $actor = $this->viewer($user, $conversation);
        abort_unless(Gate::forUser($user)->allows('interactContent', Context::query()->findOrFail($conversation->context_id)), 403);
        abort_if($message->isDeleted(), 422);

        DB::transaction(function () use ($message, $actor, $emoji): void {
            $existing = ConversationMessageReaction::query()
                ->where('conversation_message_id', $message->id)->where('actor_id', $actor->id)->where('emoji', $emoji)->first();
            if ($existing instanceof ConversationMessageReaction) {
                $existing->delete();

                return;
            }
            ConversationMessageReaction::query()->insertOrIgnore([
                'conversation_message_id' => $message->id,
                'actor_id' => $actor->id,
                'emoji' => $emoji,
                'created_at' => now(),
            ]);
        });
    }

    private function viewer(User $user, Conversation $conversation): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor && Gate::forUser($user)->allows('view', Context::query()->findOrFail($conversation->context_id)), 403);

        return $actor;
    }
}
