<?php

namespace App\Actions\Messaging;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DirectContext;
use App\Models\User;
use App\Support\Messaging\MessagingPrivacy;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one private conversation between two people: opened if it exists,
 * created only when the other person's privacy setting allows it.
 */
class OpenDirectConversation
{
    public function __construct(private readonly MessagingPrivacy $privacy) {}

    public function existing(int $a, int $b): ?Conversation
    {
        [$one, $two] = DirectContext::pair($a, $b);
        $contextId = DirectContext::query()->where('actor_one_id', $one)->where('actor_two_id', $two)->value('context_id');

        return $contextId !== null ? Conversation::query()->where('context_id', $contextId)->where('key', 'main')->first() : null;
    }

    public function execute(User $user, Actor $other): Conversation
    {
        $me = RequestMemo::user($user)?->actor;
        abort_unless($me instanceof Actor, 403);
        abort_if((int) $me->id === (int) $other->id, 422, 'You cannot message yourself.');
        $other->loadMissing('user');

        $existing = $this->existing((int) $me->id, (int) $other->id);
        if ($existing instanceof Conversation) {
            $this->participant($existing, (int) $me->id);

            return $existing;
        }
        abort_unless($this->privacy->canStart($me, $other), 403, 'This person does not accept new conversations from you.');

        return DB::transaction(function () use ($me, $other): Conversation {
            [$one, $two] = DirectContext::pair((int) $me->id, (int) $other->id);
            // Lock both people so two simultaneous starts create one conversation.
            Actor::query()->whereIn('id', [$one, $two])->orderBy('id')->lockForUpdate()->get();
            $again = $this->existing($one, $two);
            if ($again instanceof Conversation) {
                return $again;
            }

            $context = Context::query()->create(['uuid' => (string) Str::uuid(), 'kind' => ContextKind::Direct]);
            DirectContext::query()->create([
                'context_id' => $context->id,
                'actor_one_id' => $one,
                'actor_two_id' => $two,
                'created_by_actor_id' => $me->id,
            ]);
            $conversation = Conversation::query()->create([
                'context_id' => $context->id,
                'key' => 'main',
                'status' => 'active',
                'created_by_actor_id' => $me->id,
            ]);
            foreach ([$one, $two] as $actorId) {
                $this->participant($conversation, $actorId);
            }

            return $conversation;
        }, 3);
    }

    private function participant(Conversation $conversation, int $actorId): ConversationParticipant
    {
        return ConversationParticipant::query()->firstOrCreate(['conversation_id' => $conversation->id, 'actor_id' => $actorId]);
    }
}
