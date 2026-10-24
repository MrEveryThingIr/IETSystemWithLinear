<?php

namespace App\Livewire\Messages;

use App\Actions\Messaging\ManageContacts;
use App\Actions\Messaging\OpenDirectConversation;
use App\Models\Actor;
use App\Models\ActorBlock;
use App\Models\ActorContact;
use App\Models\User;
use App\Support\Messaging\MessagingPrivacy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Contacts, requests, blocked people, finding people by username, and who may message me. */
#[Layout('layouts.app')]
class Contacts extends Component
{
    #[Url]
    public string $find = '';

    public string $note = '';

    public string $privacy = MessagingPrivacy::CONTACTS;

    public string $status = '';

    public function mount(): void
    {
        $this->privacy = $this->user()->message_privacy ?: MessagingPrivacy::CONTACTS;
    }

    public function updatedPrivacy(): void
    {
        abort_unless(in_array($this->privacy, MessagingPrivacy::SETTINGS, true), 422);
        $this->user()->forceFill(['message_privacy' => $this->privacy])->save();
        $this->status = __('messages.privacy.saved');
    }

    public function ask(int $actorId, ManageContacts $contacts): void
    {
        $contacts->request($this->user(), Actor::query()->findOrFail($actorId), $this->note);
        $this->reset('note');
        $this->status = __('messages.contacts_page.requested');
    }

    public function respond(int $contactId, bool $accept, ManageContacts $contacts): void
    {
        $contacts->respond($this->user(), ActorContact::query()->findOrFail($contactId), $accept);
        $this->status = $accept ? __('messages.contacts_page.accepted') : __('messages.contacts_page.declined');
    }

    public function remove(int $contactId, ManageContacts $contacts): void
    {
        $contacts->remove($this->user(), ActorContact::query()->findOrFail($contactId));
    }

    public function block(int $actorId, ManageContacts $contacts): void
    {
        $contacts->block($this->user(), Actor::query()->findOrFail($actorId));
        $this->status = __('messages.contacts_page.blocked');
    }

    public function unblock(int $actorId, ManageContacts $contacts): void
    {
        $contacts->unblock($this->user(), Actor::query()->findOrFail($actorId));
    }

    public function message(int $actorId, OpenDirectConversation $open): void
    {
        $conversation = $open->execute($this->user(), Actor::query()->with('user')->findOrFail($actorId));
        $this->redirectRoute('messages.index', ['c' => $conversation->uuid], navigate: true);
    }

    public function render(MessagingPrivacy $privacy): View
    {
        $me = $this->user()->actor;
        abort_unless($me instanceof Actor, 403);
        $meId = (int) $me->id;

        $all = ActorContact::query()->involving($meId)->whereIn('status', [ActorContact::PENDING, ActorContact::ACCEPTED])->latest('updated_at')->get();
        $people = Actor::query()->whereIn('id', $all->map(fn (ActorContact $c): int => $c->otherThan($meId)))->with('user', 'profile')->get()->keyBy('id');
        $blocked = ActorBlock::query()->where('blocker_actor_id', $meId)->with('blocked.user')->get();

        $results = collect();
        $term = ltrim(trim($this->find), '@');
        if (mb_strlen($term) >= 3) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $results = Actor::query()
                ->where('status', 'active')
                ->whereKeyNot($meId)
                ->whereHas('user', fn ($q) => $q->where('status', 'active')->whereNotNull('email_verified_at')->where('username', 'like', $escaped.'%'))
                ->with('user')
                ->limit(10)
                ->get()
                ->reject(fn (Actor $actor): bool => $privacy->blocked($meId, (int) $actor->id));
        }

        return view('livewire.messages.contacts', [
            'meId' => $meId,
            'incoming' => $all->where('status', ActorContact::PENDING)->filter(fn (ActorContact $c): bool => (int) $c->requested_by_actor_id !== $meId),
            'outgoing' => $all->where('status', ActorContact::PENDING)->filter(fn (ActorContact $c): bool => (int) $c->requested_by_actor_id === $meId),
            'accepted' => $all->where('status', ActorContact::ACCEPTED),
            'people' => $people,
            'blocked' => $blocked,
            'results' => $results,
            'contactIds' => $all->map(fn (ActorContact $c): int => $c->otherThan($meId))->all(),
            'canStart' => fn (Actor $actor): bool => $privacy->canStart($me, $actor),
        ])->title(__('messages.contacts'));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
