<?php

namespace App\Livewire\Messages;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Conversations\ReviseConversationMessage;
use App\Actions\Messaging\ManageContacts;
use App\Actions\Messaging\ManageConversation;
use App\Actions\Messaging\SendMessage;
use App\ContextKind;
use App\Models\Actor;
use App\Models\ActorBlock;
use App\Models\ActorContact;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageReaction;
use App\Models\ConversationParticipant;
use App\Models\MessageReport;
use App\Models\User;
use App\Support\MediaLimits;
use App\Support\Messaging\Inbox;
use App\Support\Messaging\ThreadVersion;
use App\Support\RequestMemo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** One conversation: history, composer, attachments, voice, location, replies, reactions. */
class Thread extends Component
{
    use WithFileUploads;

    public const PAGE = 40;

    #[Locked]
    public Conversation $conversation;

    public string $body = '';

    public ?int $replyTo = null;

    public ?int $editing = null;

    public string $editBody = '';

    public int $limit = self::PAGE;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var TemporaryUploadedFile|null */
    public $voice = null;

    /** Shown inside another page (a Deal): no inbox header, a fixed-height body. */
    #[Locked]
    public bool $embedded = false;

    public string $notice = '';

    public bool $reporting = false;

    public ?int $reportMessage = null;

    public string $reportReason = 'spam';

    public string $reportDetails = '';

    public bool $showShareContact = false;

    public function mount(Conversation $conversation, bool $embedded = false): void
    {
        $this->embedded = $embedded;
        abort_unless(Gate::allows('view', $this->context($conversation)), 403);
        $this->conversation = $conversation;
        app(ManageConversation::class)->markRead($this->user(), $conversation);
    }

    public function refreshThread(): void
    {
        app(ManageConversation::class)->markRead($this->user(), $this->conversation);
        $this->dispatch('thread-updated', version: app(ThreadVersion::class)->for($this->conversation));
    }

    public function loadOlder(): void
    {
        $this->limit = min($this->limit + self::PAGE, 400);
    }

    public function send(string $clientId, SendMessage $send): void
    {
        $this->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'files' => ['array', 'max:6'],
            'files.*' => ['file', 'max:'.MediaLimits::kilobytes('asset')],
        ], [], ['files.*' => __('messages.composer.file')]);
        if (trim($this->body) === '' && $this->files === []) {
            return;
        }

        $assetIds = [];
        foreach ($this->files as $file) {
            $assetIds[] = $this->store($file);
        }
        $send->execute($this->user(), $this->conversation, $this->body, [
            'reply_to' => $this->replyTo,
            'asset_ids' => $assetIds,
            'client_uuid' => $clientId,
        ]);
        $this->reset('body', 'replyTo', 'files', 'notice');
        $this->dispatch('message-sent');
    }

    public function sendVoice(int $seconds, string $clientId, SendMessage $send): void
    {
        $this->validate(['voice' => ['required', 'file', 'max:'.MediaLimits::kilobytes('asset')]], [], ['voice' => __('messages.composer.voice')]);
        try {
            $assetId = $this->store($this->voice);
        } catch (HttpException $e) {
            // A refused file (type, size, permission) is shown, never swallowed.
            $this->reset('voice');
            $this->voiceFailed('store', $e->getStatusCode().' '.$e->getMessage());

            return;
        }
        $send->execute($this->user(), $this->conversation, '', ['kind' => 'voice', 'asset_ids' => [$assetId], 'meta' => ['seconds' => $seconds], 'client_uuid' => $clientId, 'reply_to' => $this->replyTo]);
        $this->reset('voice', 'replyTo', 'notice');
        $this->dispatch('message-sent');
    }

    public function sendLocation(float $lat, float $lng, string $clientId, SendMessage $send): void
    {
        $send->execute($this->user(), $this->conversation, '', ['kind' => 'location', 'meta' => ['lat' => $lat, 'lng' => $lng], 'client_uuid' => $clientId]);
        $this->reset('notice');
        $this->dispatch('message-sent');
    }

    public function shareContact(int $actorId, SendMessage $send): void
    {
        // Only one's own accepted contacts can be shared as a card.
        $me = (int) $this->user()->actor?->id;
        abort_unless(ActorContact::query()->between($me, $actorId)->where('status', ActorContact::ACCEPTED)->exists(), 403);
        $send->execute($this->user(), $this->conversation, '', ['kind' => 'contact', 'meta' => ['actor_id' => $actorId], 'client_uuid' => (string) Str::uuid()]);
        $this->showShareContact = false;
        $this->dispatch('message-sent');
    }

    public function voiceUnsupported(): void
    {
        $this->notice = __('messages.composer.voice_unsupported');
    }

    /** The browser reports the stage that failed so a silent failure always leaves a trace in the log. */
    public function voiceFailed(string $stage = '', string $detail = ''): void
    {
        Log::warning('messages.voice_failed', [
            'stage' => mb_substr($stage, 0, 20),
            'detail' => mb_substr($detail, 0, 400),
            'user_id' => $this->user()->id,
            'conversation' => $this->conversation->uuid,
        ]);
        $this->notice = __('messages.composer.voice_failed');
    }

    public function locationUnavailable(): void
    {
        $this->notice = __('messages.composer.location_unavailable');
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function reply(int $messageId): void
    {
        $this->replyTo = $this->message($messageId)->id;
    }

    public function cancelReply(): void
    {
        $this->replyTo = null;
    }

    public function react(int $messageId, string $emoji, ManageConversation $manage): void
    {
        $manage->react($this->user(), $this->message($messageId), $emoji);
    }

    public function startEdit(int $messageId): void
    {
        $message = $this->own($messageId);
        abort_unless($message->kind === 'text', 422);
        $this->editing = $message->id;
        $this->editBody = $message->currentBody();
    }

    public function cancelEdit(): void
    {
        $this->reset('editing', 'editBody');
    }

    public function saveEdit(ReviseConversationMessage $revise): void
    {
        $this->validate(['editBody' => ['required', 'string', 'max:4000']]);
        abort_unless($this->editing !== null, 422);
        $revise->edit($this->own($this->editing), $this->user(), $this->editBody);
        $this->cancelEdit();
    }

    public function deleteMessage(int $messageId, ReviseConversationMessage $revise): void
    {
        $revise->delete($this->own($messageId), $this->user());
    }

    public function mute(?int $hours, ManageConversation $manage): void
    {
        $manage->mute($this->user(), $this->conversation, $hours);
    }

    public function archive(bool $archived, ManageConversation $manage): void
    {
        $manage->archive($this->user(), $this->conversation, $archived);
        $this->dispatch('inbox-changed');
    }

    public function pin(bool $pinned, ManageConversation $manage): void
    {
        $manage->pin($this->user(), $this->conversation, $pinned);
        $this->dispatch('inbox-changed');
    }

    public function block(bool $blocked, ManageContacts $contacts): void
    {
        $other = $this->otherPerson();
        abort_unless($other instanceof Actor, 422);
        $blocked ? $contacts->block($this->user(), $other) : $contacts->unblock($this->user(), $other);
    }

    public function openReport(?int $messageId = null): void
    {
        $this->reportMessage = $messageId !== null ? $this->message($messageId)->id : null;
        $this->reportReason = 'spam';
        $this->reportDetails = '';
        $this->reporting = true;
    }

    public function submitReport(ManageContacts $contacts): void
    {
        $this->validate([
            'reportReason' => ['required', 'in:'.implode(',', MessageReport::REASONS)],
            'reportDetails' => ['nullable', 'string', 'max:2000'],
        ]);
        $message = $this->reportMessage !== null ? $this->message($this->reportMessage) : null;
        $reported = $message !== null ? Actor::query()->findOrFail($message->author_actor_id) : $this->otherPerson();
        abort_unless($reported instanceof Actor, 422);
        $contacts->report($this->user(), $reported, $this->reportReason, $this->reportDetails, $message);
        $this->reset('reportMessage', 'reportDetails', 'reporting');
        $this->notice = __('messages.report.sent');
    }

    public function render(): View
    {
        $user = $this->user();
        $context = $this->context($this->conversation);
        abort_unless(Gate::forUser($user)->allows('view', $context), 403);
        $actorId = (int) $user->actor?->id;

        $messages = ConversationMessage::query()
            ->where('conversation_id', $this->conversation->id)
            ->with(['author.user', 'author.profile', 'latestRevision', 'replyTo.author.user', 'replyTo.latestRevision', 'assets', 'reactions', 'evidenceReferences.content.activeRevision', 'evidenceReferences.revision'])
            ->latest('id')
            ->limit($this->limit + 1)
            ->get();
        $hasOlder = $messages->count() > $this->limit;
        $messages = $messages->take($this->limit)->reverse()->values();

        $participant = ConversationParticipant::query()->where('conversation_id', $this->conversation->id)->where('actor_id', $actorId)->first();
        $other = $this->otherPerson();
        $otherRead = $other instanceof Actor
            ? (int) ConversationParticipant::query()->where('conversation_id', $this->conversation->id)->where('actor_id', $other->id)->value('last_read_message_id')
            : 0;
        $contactCards = Actor::query()->whereIn('id', $messages->where('kind', 'contact')->map(fn ($m) => (int) ($m->meta['actor_id'] ?? 0))->filter()->unique())
            ->with('user', 'profile')->get()->keyBy('id');

        return view('livewire.messages.thread', [
            'context' => $context,
            'messages' => $messages,
            'hasOlder' => $hasOlder,
            'participant' => $participant,
            'other' => $other,
            'otherRead' => $otherRead,
            'iBlocked' => $other instanceof Actor && ActorBlock::query()->where('blocker_actor_id', $actorId)->where('blocked_actor_id', $other->id)->exists(),
            'canWrite' => Gate::forUser($user)->allows('interactContent', $context),
            'title' => $other instanceof Actor ? (string) ($other->user->username ?? '') : app(Inbox::class)->contextTitle($context),
            'replyMessage' => $this->replyTo !== null ? $messages->firstWhere('id', $this->replyTo) ?? ConversationMessage::query()->with('author.user')->find($this->replyTo) : null,
            'version' => app(ThreadVersion::class)->for($this->conversation),
            'emoji' => ConversationMessageReaction::EMOJI,
            'contactCards' => $contactCards,
            'myContacts' => $this->showShareContact ? $this->acceptedContacts($actorId) : collect(),
            'meId' => $actorId,
        ]);
    }

    public function closeReport(): void
    {
        $this->reset('reporting', 'reportMessage', 'reportDetails');
    }

    public function openShareContact(): void
    {
        $this->showShareContact = ! $this->showShareContact;
    }

    /** @return Collection<int, Actor> */
    private function acceptedContacts(int $actorId): Collection
    {
        $ids = ActorContact::query()->involving($actorId)->where('status', ActorContact::ACCEPTED)->get()->map(fn (ActorContact $c): int => $c->otherThan($actorId));

        return Actor::query()->whereIn('id', $ids)->with('user')->get()->sortBy(fn (Actor $a) => $a->user->username ?? '')->values();
    }

    private function store(TemporaryUploadedFile $file): int
    {
        return (int) app(CreateContextAsset::class)->execute($this->context($this->conversation), $this->user(), $file, 'owned')->id;
    }

    private function message(int $id): ConversationMessage
    {
        return ConversationMessage::query()->where('conversation_id', $this->conversation->id)->findOrFail($id);
    }

    private function own(int $id): ConversationMessage
    {
        $message = $this->message($id);
        abort_unless((int) $message->author_actor_id === (int) $this->user()->actor?->id, 403);

        return $message;
    }

    private function otherPerson(): ?Actor
    {
        $context = $this->context($this->conversation);
        if ($context->kind !== ContextKind::Direct || $context->directBinding === null) {
            return null;
        }

        return Actor::query()->with('user', 'profile')->find($context->directBinding->otherThan((int) $this->user()->actor?->id));
    }

    private function context(Conversation $conversation): Context
    {
        return RequestMemo::remember('thread-context:'.$conversation->id, fn () => Context::query()->with('directBinding')->findOrFail($conversation->context_id));
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
