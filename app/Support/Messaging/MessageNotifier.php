<?php

namespace App\Support\Messaging;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Support\ContextNotificationRecipients;
use App\Support\NotificationOutboxWriter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells people about new messages without flooding them: private messages
 * notify the other person; other conversations notify only people who have
 * taken part in them. Nobody is notified while they are looking at the
 * conversation, while it is muted, or more than once per conversation every
 * ten minutes. Notifications never include the message text.
 */
class MessageNotifier
{
    public function __construct(
        private readonly NotificationOutboxWriter $outbox,
        private readonly ContextNotificationRecipients $recipients,
    ) {}

    public function newMessage(ConversationMessage $message, Actor $author): void
    {
        try {
            $conversation = $message->conversation()->firstOrFail();
            $context = Context::query()->findOrFail($conversation->context_id);
            $participants = ConversationParticipant::query()->where('conversation_id', $conversation->id)->get()->keyBy('actor_id');

            $candidates = $context->kind === ContextKind::Direct
                ? $this->recipients->viewers($context, (int) $author->id)
                : User::query()->whereHas('actor', fn ($q) => $q->whereIn('id', $participants->keys()->reject(fn ($id): bool => (int) $id === (int) $author->id)))->get()
                    ->filter(fn (User $user): bool => $user->status === 'active' && $user->email_verified_at !== null && Gate::forUser($user)->allows('view', $context));

            $window = (int) floor(now()->timestamp / 600);
            foreach ($candidates as $user) {
                $participant = $participants->get((int) $user->actor?->id);
                if ($participant instanceof ConversationParticipant
                    && ($participant->isMuted() || ($participant->last_seen_at !== null && $participant->last_seen_at->gt(now()->subSeconds(45))))) {
                    continue;
                }
                $this->outbox->request(
                    $user,
                    'message:'.$conversation->uuid.':'.$user->id.':'.$window,
                    'messages.new',
                    [
                        'title_key' => 'messages.notifications.new_title',
                        'title_params' => ['name' => (string) ($author->user->username ?? '')],
                        'body_key' => 'messages.notifications.new_body',
                        'body_params' => [],
                        'url' => route('messages.show', $conversation),
                    ],
                    $context,
                    'conversation',
                    $conversation->uuid,
                );
            }
        } catch (Throwable $exception) {
            // A notification problem never loses the message.
            Log::warning('Message notification could not be queued.', ['message' => $message->uuid, 'error' => $exception->getMessage()]);
        }
    }
}
