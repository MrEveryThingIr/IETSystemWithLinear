<?php

namespace App\Actions\Messaging;

use App\Models\Actor;
use App\Models\ActorBlock;
use App\Models\ActorContact;
use App\Models\ConversationMessage;
use App\Models\DirectContext;
use App\Models\MessageReport;
use App\Models\User;
use App\Support\Messaging\MessagingPrivacy;
use App\Support\NotificationOutboxWriter;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contacts (ask, accept, decline, remove), blocking and reporting.
 * A contact only lets two people message each other; it never opens any
 * other record. Blocking stops messages and contact requests both ways and
 * is not announced to the blocked person.
 */
class ManageContacts
{
    public function __construct(
        private readonly MessagingPrivacy $privacy,
        private readonly NotificationOutboxWriter $outbox,
    ) {}

    public function request(User $user, Actor $other, ?string $note = null): ActorContact
    {
        $me = $this->me($user);
        abort_if((int) $me->id === (int) $other->id, 422);
        $other->loadMissing('user');
        abort_unless($other->status === 'active' && $other->user?->status === 'active', 404);
        $note = $note !== null && trim($note) !== '' ? Str::limit(Str::squish($note), 300, '') : null;

        // A blocked person sees the same result as a normal request, but nothing reaches the other side.
        if ($this->privacy->blocked((int) $me->id, (int) $other->id)) {
            throw ValidationException::withMessages(['contact' => __('messages.errors.cannot_request')]);
        }

        return DB::transaction(function () use ($me, $other, $note): ActorContact {
            [$one, $two] = DirectContext::pair((int) $me->id, (int) $other->id);
            $contact = ActorContact::query()->between($one, $two)->lockForUpdate()->first();
            if ($contact instanceof ActorContact) {
                if ($contact->status === ActorContact::ACCEPTED) {
                    return $contact;
                }
                // They had asked me: asking back accepts.
                if ($contact->status === ActorContact::PENDING && (int) $contact->requested_by_actor_id === (int) $other->id) {
                    $contact->update(['status' => ActorContact::ACCEPTED, 'responded_at' => now()]);

                    return $contact;
                }
                $contact->update(['status' => ActorContact::PENDING, 'requested_by_actor_id' => $me->id, 'note' => $note, 'responded_at' => null]);
            } else {
                $contact = ActorContact::query()->create([
                    'actor_one_id' => $one, 'actor_two_id' => $two, 'requested_by_actor_id' => $me->id,
                    'status' => ActorContact::PENDING, 'note' => $note,
                ]);
            }
            $this->notifyRequest($contact, $me, $other);

            return $contact;
        }, 3);
    }

    public function respond(User $user, ActorContact $contact, bool $accept): void
    {
        $me = $this->me($user);
        abort_unless($contact->status === ActorContact::PENDING
            && in_array((int) $me->id, [(int) $contact->actor_one_id, (int) $contact->actor_two_id], true)
            && (int) $contact->requested_by_actor_id !== (int) $me->id, 403);
        $contact->update(['status' => $accept ? ActorContact::ACCEPTED : ActorContact::DECLINED, 'responded_at' => now()]);
    }

    /** Removes a contact, or withdraws one's own request. */
    public function remove(User $user, ActorContact $contact): void
    {
        $me = $this->me($user);
        abort_unless(in_array((int) $me->id, [(int) $contact->actor_one_id, (int) $contact->actor_two_id], true), 403);
        $contact->update(['status' => ActorContact::DECLINED, 'responded_at' => now()]);
    }

    public function block(User $user, Actor $other): void
    {
        $me = $this->me($user);
        abort_if((int) $me->id === (int) $other->id, 422);
        ActorBlock::query()->firstOrCreate(['blocker_actor_id' => $me->id, 'blocked_actor_id' => $other->id]);
        // A pending request between them is closed quietly.
        ActorContact::query()->between((int) $me->id, (int) $other->id)->where('status', ActorContact::PENDING)
            ->update(['status' => ActorContact::DECLINED, 'responded_at' => now()]);
        RequestMemo::flush();
    }

    public function unblock(User $user, Actor $other): void
    {
        ActorBlock::query()->where('blocker_actor_id', $this->me($user)->id)->where('blocked_actor_id', $other->id)->delete();
        RequestMemo::flush();
    }

    public function report(User $user, Actor $reported, string $reason, ?string $details = null, ?ConversationMessage $message = null): MessageReport
    {
        $me = $this->me($user);
        abort_unless(in_array($reason, MessageReport::REASONS, true), 422);
        abort_if((int) $me->id === (int) $reported->id, 422);
        if ($message instanceof ConversationMessage) {
            // Only something the reporter could see, written by the reported person.
            $conversation = $message->conversation()->with('context')->firstOrFail();
            abort_unless((int) $message->author_actor_id === (int) $reported->id && Gate::forUser($user)->allows('view', $conversation->context), 403);
        }

        return MessageReport::query()->create([
            'reporter_actor_id' => $me->id,
            'reported_actor_id' => $reported->id,
            'conversation_message_id' => $message?->id,
            'reason' => $reason,
            'details' => $details !== null && trim($details) !== '' ? Str::limit(trim($details), 2000, '') : null,
            'status' => 'open',
        ]);
    }

    private function notifyRequest(ActorContact $contact, Actor $from, Actor $to): void
    {
        $recipient = $to->user;
        if (! $recipient instanceof User || $recipient->email_verified_at === null || $recipient->status !== 'active') {
            return;
        }
        try {
            $this->outbox->request(
                $recipient,
                'contact-request:'.$contact->uuid.':'.$contact->updated_at?->timestamp,
                'messages.contact_request',
                [
                    'title_key' => 'messages.notifications.contact_title',
                    'title_params' => ['name' => (string) ($from->user->username ?? '')],
                    'body_key' => 'messages.notifications.contact_body',
                    'body_params' => [],
                    'url' => route('contacts.index'),
                ],
            );
        } catch (\Throwable) {
            // The request stands even if the notification could not be queued.
        }
    }

    private function me(User $user): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor && $actor->status === 'active', 403);

        return $actor;
    }
}
