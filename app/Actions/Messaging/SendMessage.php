<?php

namespace App\Actions\Messaging;

use App\Actions\Conversations\PostContextMessage;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Support\Messaging\MessageNotifier;
use App\Support\RequestMemo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sends a message in any conversation the user may write in (private, group,
 * business, deal, work…). Text, voice note, photo/video/file, location or a
 * contact card. Safe to repeat with the same client id.
 */
class SendMessage
{
    public function __construct(
        private readonly PostContextMessage $post,
        private readonly MessageNotifier $notifier,
    ) {}

    /**
     * @param  array{kind?: string, reply_to?: int|null, asset_ids?: list<int>, meta?: array<string, mixed>|null, client_uuid?: string|null}  $options
     */
    public function execute(User $user, Conversation $conversation, string $body, array $options = []): ConversationMessage
    {
        $kind = (string) ($options['kind'] ?? 'text');
        $meta = $this->meta($kind, $options['meta'] ?? null);
        $clientUuid = $options['client_uuid'] ?? null;
        $context = Context::query()->findOrFail($conversation->context_id);
        abort_unless($conversation->key === 'main', 404);

        try {
            $message = $this->post->execute(
                $context,
                $user,
                Str::limit(trim($body), 4000, ''),
                $options['reply_to'] ?? null,
                $options['asset_ids'] ?? [],
                [],
                $kind,
                $meta,
                $clientUuid,
            );
        } catch (UniqueConstraintViolationException) {
            $message = ConversationMessage::query()->where('author_actor_id', RequestMemo::user($user)?->actor?->id)->where('client_uuid', $clientUuid)->firstOrFail();
        }

        $actor = RequestMemo::user($user)?->actor;
        if ($actor instanceof Actor) {
            ConversationParticipant::query()->updateOrCreate(
                ['conversation_id' => $conversation->id, 'actor_id' => $actor->id],
                ['last_read_message_id' => $message->id, 'last_seen_at' => now(), 'archived_at' => null],
            );
            // Someone writing brings an archived conversation back for everyone in it.
            ConversationParticipant::query()->where('conversation_id', $conversation->id)->whereNotNull('archived_at')->update(['archived_at' => null]);
            $this->notifier->newMessage($message, $actor);
        }

        return $message;
    }

    /** @return array<string, mixed>|null */
    private function meta(string $kind, mixed $meta): ?array
    {
        $meta = is_array($meta) ? $meta : [];
        if ($kind === 'location') {
            $lat = filter_var($meta['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($meta['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
                throw ValidationException::withMessages(['location' => __('messages.errors.location')]);
            }
            $label = is_string($meta['label'] ?? null) ? Str::limit(Str::squish($meta['label']), 120, '') : null;

            // Five decimals ≈ one metre; the sender chose to share this exact place.
            return ['lat' => round($lat, 5), 'lng' => round($lng, 5), 'label' => $label];
        }
        if ($kind === 'contact') {
            $actorId = (int) ($meta['actor_id'] ?? 0);
            abort_unless($actorId > 0 && Actor::query()->whereKey($actorId)->where('status', 'active')->exists(), 422, 'Unknown contact.');

            return ['actor_id' => $actorId];
        }
        if ($kind === 'voice') {
            $seconds = (int) ($meta['seconds'] ?? 0);

            return ['seconds' => max(0, min($seconds, 3600))];
        }

        return null;
    }
}
