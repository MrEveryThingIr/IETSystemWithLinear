<?php

namespace App\Support\Messaging;

use App\Models\Actor;
use App\Models\ActorBlock;
use App\Models\ActorContact;
use App\Models\ConversationMessage;
use App\Models\DirectContext;
use App\Models\User;
use App\Support\RequestMemo;

/**
 * Who may write to whom in private conversations.
 *
 * - Nobody can write to someone who blocked them, or whom they blocked.
 * - Starting a conversation follows the recipient's setting:
 *   everyone / contacts (accepted contacts only) / nobody.
 * - Anyone may reply to a person who wrote to them, so a conversation, once
 *   started, continues until one blocks the other.
 * Being a contact never grants access to anything else.
 */
class MessagingPrivacy
{
    public const EVERYONE = 'everyone';

    public const CONTACTS = 'contacts';

    public const NOBODY = 'nobody';

    public const SETTINGS = [self::EVERYONE, self::CONTACTS, self::NOBODY];

    public function blocked(int $a, int $b): bool
    {
        return (bool) RequestMemo::remember('blocked:'.min($a, $b).':'.max($a, $b), fn (): bool => ActorBlock::between($a, $b));
    }

    public function areContacts(int $a, int $b): bool
    {
        return (bool) RequestMemo::remember('contacts:'.min($a, $b).':'.max($a, $b), fn (): bool => ActorContact::query()->between($a, $b)->where('status', ActorContact::ACCEPTED)->exists());
    }

    /** May $from start a private conversation with $to? */
    public function canStart(Actor $from, Actor $to): bool
    {
        if ((int) $from->id === (int) $to->id || ! $this->active($from) || ! $this->active($to) || $this->blocked((int) $from->id, (int) $to->id)) {
            return false;
        }

        return match ($to->user->message_privacy ?? self::CONTACTS) {
            self::EVERYONE => true,
            self::NOBODY => false,
            default => $this->areContacts((int) $from->id, (int) $to->id),
        };
    }

    /** May $sender write in this private conversation now? */
    public function canSend(Actor $sender, DirectContext $direct): bool
    {
        if (! $direct->includes((int) $sender->id)) {
            return false;
        }
        $other = Actor::query()->with('user')->find($direct->otherThan((int) $sender->id));
        if (! $other instanceof Actor || ! $this->active($sender) || ! $this->active($other) || $this->blocked((int) $sender->id, (int) $other->id)) {
            return false;
        }

        // Anyone may answer a person who wrote to them; otherwise the other's setting decides.
        return in_array((int) $other->id, $this->authors($direct), true) || $this->canStart($sender, $other);
    }

    /** Both people have written at least once. */
    public function established(DirectContext $direct): bool
    {
        $authors = $this->authors($direct);

        return in_array((int) $direct->actor_one_id, $authors, true) && in_array((int) $direct->actor_two_id, $authors, true);
    }

    /** @return list<int> who has written in this private conversation */
    private function authors(DirectContext $direct): array
    {
        return RequestMemo::remember('direct-authors:'.$direct->id, fn (): array => ConversationMessage::query()
            ->whereHas('conversation', fn ($q) => $q->where('context_id', $direct->context_id))
            ->distinct()
            ->pluck('author_actor_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all());
    }

    private function active(Actor $actor): bool
    {
        $user = $actor->user;

        return $actor->status === 'active' && $user instanceof User && $user->status === 'active' && $user->email_verified_at !== null;
    }
}
