<?php

namespace Tests\Feature;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PostGroupSpaceMessage;
use App\Actions\Messaging\ManageContacts;
use App\Actions\Messaging\ManageConversation;
use App\Actions\Messaging\OpenDirectConversation;
use App\Actions\Messaging\SendMessage;
use App\Livewire\Messages\Contacts;
use App\Livewire\Messages\Index;
use App\Livewire\Messages\Thread;
use App\Livewire\Platform\MessageReports;
use App\Models\Actor;
use App\Models\ActorContact;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\GroupSpace;
use App\Models\MessageReport;
use App\Models\NotificationOutbox;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\Support\Messaging\Inbox;
use App\Support\Messaging\MessagingPrivacy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private Actor $ana;

    private Actor $ben;

    private Actor $eve;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        [$this->ana, $this->ben, $this->eve] = [Actor::factory()->create(), Actor::factory()->create(), Actor::factory()->create()];
        foreach ([$this->ana, $this->ben, $this->eve] as $actor) {
            $actor->user->forceFill(['locale' => 'en'])->save();
            $this->publishSurfaces($actor->user, ['messages', 'groups']);
        }
    }

    private function assertDenied(callable $action, string $message): void
    {
        try {
            $action();
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode(), $message);

            return;
        } catch (AuthorizationException) {
            $this->assertTrue(true);

            return;
        }
        $this->fail($message);
    }

    private function befriend(Actor $a, Actor $b): void
    {
        $contact = app(ManageContacts::class)->request($a->user, $b);
        app(ManageContacts::class)->respond($b->user, $contact, true);
    }

    private function direct(Actor $from, Actor $to): Conversation
    {
        return app(OpenDirectConversation::class)->execute($from->user, $to->fresh('user'));
    }

    private function send(Actor $from, Conversation $conversation, string $body, array $options = []): ConversationMessage
    {
        return app(SendMessage::class)->execute($from->user->fresh(), $conversation, $body, $options);
    }

    public function test_privacy_setting_decides_who_may_start_and_blocking_stops_everything(): void
    {
        $privacy = app(MessagingPrivacy::class);
        $this->assertSame('contacts', $this->ben->user->fresh()->message_privacy);
        $this->assertFalse($privacy->canStart($this->ana->fresh('user'), $this->ben->fresh('user')));

        $this->assertDenied(fn () => $this->direct($this->ana, $this->ben), 'A stranger started a conversation with a contacts-only person.');

        $this->befriend($this->ana, $this->ben);
        $conversation = $this->direct($this->ana, $this->ben);
        $this->assertSame($conversation->id, $this->direct($this->ben, $this->ana)->id, 'One conversation per pair, either direction.');

        $this->send($this->ana, $conversation, 'Hello Ben');
        $this->send($this->ben, $conversation, 'Hi Ana');
        $this->ben->user->forceFill(['message_privacy' => 'nobody'])->save();
        $this->assertDenied(fn () => $this->direct($this->eve, $this->ben), 'Nobody may start a new conversation.');
        // Established conversations continue even when new ones are closed.
        $this->send($this->ana, $conversation, 'Still here');

        app(ManageContacts::class)->block($this->ben->user, $this->ana);
        $this->assertDenied(fn () => $this->send($this->ana, $conversation, 'Blocked?'), 'A blocked person could still write.');
        $this->expectException(ValidationException::class);
        app(ManageContacts::class)->request($this->ana->user, $this->ben);
    }

    public function test_everyone_setting_and_outsiders_cannot_read_or_post(): void
    {
        $this->ben->user->forceFill(['message_privacy' => 'everyone'])->save();
        $conversation = $this->direct($this->ana, $this->ben);
        $message = $this->send($this->ana, $conversation, 'Hello', ['client_uuid' => $uuid = (string) Str::uuid()]);
        $this->assertSame($message->id, $this->send($this->ana, $conversation, 'Hello', ['client_uuid' => $uuid])->id, 'Same client id → same message.');
        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());

        $this->actingAs($this->eve->user)->getJson(route('messages.state', $conversation))->assertForbidden();
        Livewire::actingAs($this->eve->user)->test(Thread::class, ['conversation' => $conversation])->assertForbidden();
        $this->assertDenied(fn () => $this->send($this->eve, $conversation, 'Let me in'), 'An outsider posted.');
        $this->actingAs($this->ben->user)->getJson(route('messages.state', $conversation))->assertOk()->assertJsonStructure(['version']);
    }

    public function test_read_state_mute_archive_and_unread_counts(): void
    {
        $this->befriend($this->ana, $this->ben);
        $conversation = $this->direct($this->ana, $this->ben);
        $this->send($this->ana, $conversation, 'one');
        $this->send($this->ana, $conversation, 'two');
        $inbox = app(Inbox::class);

        $this->assertSame(2, $inbox->unreadTotal($this->ben->user->fresh()));
        $this->assertSame(0, $inbox->unreadTotal($this->ana->user->fresh()));
        $row = $inbox->for($this->ben->user->fresh())->sole();
        $this->assertSame(2, $row['unread']);
        $this->assertSame($this->ana->user->username, $row['title']);

        app(ManageConversation::class)->mute($this->ben->user, $conversation, 8);
        $this->assertSame(0, $inbox->unreadTotal($this->ben->user->fresh()));
        app(ManageConversation::class)->mute($this->ben->user, $conversation, null);
        app(ManageConversation::class)->markRead($this->ben->user, $conversation);
        $this->assertSame(0, $inbox->unreadTotal($this->ben->user->fresh()));

        app(ManageConversation::class)->archive($this->ben->user, $conversation, true);
        $this->assertCount(0, $inbox->for($this->ben->user->fresh()));
        $this->assertCount(1, $inbox->for($this->ben->user->fresh(), archived: true));
        $this->send($this->ana, $conversation, 'three');
        $this->assertCount(1, $inbox->for($this->ben->user->fresh()), 'A new message brings it back.');
    }

    public function test_reactions_edits_deletes_and_search(): void
    {
        $this->befriend($this->ana, $this->ben);
        $conversation = $this->direct($this->ana, $this->ben);
        $message = $this->send($this->ana, $conversation, 'Meeting at the café tomorrow');
        $manage = app(ManageConversation::class);

        $manage->react($this->ben->user, $message, '👍');
        $this->assertSame(1, $message->reactions()->count());
        $manage->react($this->ben->user, $message, '👍');
        $this->assertSame(0, $message->reactions()->count(), 'Choosing it again removes it.');
        $this->assertDenied(fn () => $manage->react($this->eve->user, $message, '👍'), 'An outsider reacted.');

        $this->assertCount(1, app(Inbox::class)->search($this->ben->user->fresh(), 'café'));
        $this->assertCount(0, app(Inbox::class)->search($this->eve->user->fresh(), 'café'));

        Livewire::actingAs($this->ben->user)->test(Thread::class, ['conversation' => $conversation])
            ->call('startEdit', $message->id)->assertForbidden();
        Livewire::actingAs($this->ana->user)->test(Thread::class, ['conversation' => $conversation])
            ->call('startEdit', $message->id)->set('editBody', 'Meeting at the library tomorrow')->call('saveEdit')->assertHasNoErrors()
            ->assertSee('library');
        $this->assertCount(0, app(Inbox::class)->search($this->ben->user->fresh(), 'café'));
        $this->assertCount(1, app(Inbox::class)->search($this->ben->user->fresh(), 'library'));

        Livewire::actingAs($this->ana->user)->test(Thread::class, ['conversation' => $conversation])
            ->call('deleteMessage', $message->id)->assertSee(__('messages.deleted'));
        $this->assertCount(0, app(Inbox::class)->search($this->ben->user->fresh(), 'library'));
    }

    public function test_thread_sends_text_files_voice_location_and_contact_cards(): void
    {
        $this->befriend($this->ana, $this->ben);
        $this->befriend($this->ana, $this->eve);
        $conversation = $this->direct($this->ana, $this->ben);

        Livewire::actingAs($this->ana->user)->test(Thread::class, ['conversation' => $conversation])
            ->set('body', 'Photo of the panel')
            ->set('files', [UploadedFile::fake()->image('panel.jpg', 300, 200)])
            ->call('send', (string) Str::uuid())
            ->assertHasNoErrors()
            ->set('voice', UploadedFile::fake()->create('voice.webm', 20, 'audio/webm'))
            ->call('sendVoice', 4, (string) Str::uuid())
            ->assertHasNoErrors()
            ->call('sendLocation', 35.6892, 51.389, (string) Str::uuid())
            ->call('shareContact', $this->eve->id)
            ->assertSee(__('messages.location.shared'))
            ->assertSee($this->eve->user->username);

        $kinds = ConversationMessage::query()->where('conversation_id', $conversation->id)->orderBy('id')->pluck('kind')->all();
        $this->assertSame(['text', 'voice', 'location', 'contact'], $kinds);
        $photo = ConversationMessage::query()->where('conversation_id', $conversation->id)->where('kind', 'text')->sole();
        $asset = $photo->assets()->sole();

        $this->actingAs($this->ben->user)->get(route('messages.assets.show', [$conversation, $photo, $asset]))->assertOk();
        $this->actingAs($this->eve->user)->get(route('messages.assets.show', [$conversation, $photo, $asset]))->assertForbidden();

        // Only one's own accepted contacts can be shared.
        Livewire::actingAs($this->ben->user)->test(Thread::class, ['conversation' => $conversation])
            ->call('shareContact', $this->eve->id)->assertForbidden();

        $this->expectException(ValidationException::class);
        $this->send($this->ana, $conversation, '', ['kind' => 'location', 'meta' => ['lat' => 120, 'lng' => 10]]);
    }

    public function test_group_chat_appears_in_the_inbox_and_disappears_when_access_ends(): void
    {
        $group = app(CreateGroup::class)->execute($this->ana, 'Choir', null);
        $space = GroupSpace::query()->where('group_id', $group->id)->where('kind', 'chat')->first();
        $this->assertNotNull($space, 'Groups come with a chat space.');
        $context = Context::query()->findOrFail($space->contextBinding->context_id);
        $conversation = Conversation::query()->firstOrCreate(['context_id' => $context->id, 'key' => 'main'], ['status' => 'active', 'created_by_actor_id' => $this->ana->id]);

        $this->send($this->ana, $conversation, 'Rehearsal on Friday');
        $this->assertSame('group_space', app(Inbox::class)->for($this->ana->user->fresh())->sole()['kind']);
        $this->assertCount(0, app(Inbox::class)->for($this->eve->user->fresh()));

        $this->assertDenied(fn () => app(ManageConversation::class)->markRead($this->eve->user, $conversation), 'A non-member opened the group chat.');
    }

    public function test_notifications_reach_the_other_person_but_not_when_muted(): void
    {
        $this->befriend($this->ana, $this->ben);
        $conversation = $this->direct($this->ana, $this->ben);
        $this->send($this->ana, $conversation, 'Ping');
        $row = NotificationOutbox::query()->where('kind', 'messages.new')->where('recipient_user_id', $this->ben->user->id)->sole();
        $this->assertStringNotContainsString('Ping', json_encode($row->data), 'Notifications never carry the message text.');

        app(ManageConversation::class)->mute($this->ben->user, $conversation, 0);
        $this->travel(11)->minutes();
        $this->send($this->ana, $conversation, 'Ping again');
        $this->assertSame(1, NotificationOutbox::query()->where('kind', 'messages.new')->where('recipient_user_id', $this->ben->user->id)->count());
    }

    public function test_contacts_page_flow_and_privacy_setting(): void
    {
        Livewire::actingAs($this->ana->user)->test(Contacts::class)
            ->set('find', substr($this->ben->user->username, 0, 4))
            ->assertSee($this->ben->user->username)
            ->call('ask', $this->ben->id)
            ->assertSee(__('messages.contacts_page.requested'));

        $contact = ActorContact::query()->sole();
        Livewire::actingAs($this->ben->user)->test(Contacts::class)
            ->assertSee($this->ana->user->username)
            ->call('respond', $contact->id, true)
            ->set('privacy', 'everyone')
            ->assertSee(__('messages.privacy.saved'));
        $this->assertSame('accepted', $contact->fresh()->status);
        $this->assertSame('everyone', $this->ben->user->fresh()->message_privacy);

        // Eve cannot answer a request that was not sent to her.
        Livewire::actingAs($this->eve->user)->test(Contacts::class)->call('respond', $contact->id, false)->assertForbidden();

        Livewire::actingAs($this->ana->user)->test(Contacts::class)->call('message', $this->ben->id)->assertRedirect();
        $this->actingAs($this->ana->user)->get(route('messages.index'))->assertOk()->assertSee($this->ben->user->username);
    }

    public function test_reports_reach_administrators_only(): void
    {
        $this->befriend($this->ana, $this->ben);
        $conversation = $this->direct($this->ana, $this->ben);
        $message = $this->send($this->ana, $conversation, 'Buy cheap stuff');

        Livewire::actingAs($this->ben->user)->test(Thread::class, ['conversation' => $conversation])
            ->call('openReport', $message->id)->set('reportReason', 'spam')->call('submitReport')->assertHasNoErrors();
        $this->assertSame((int) $this->ana->id, (int) MessageReport::query()->sole()->reported_actor_id);

        // Eve cannot report a message she cannot see.
        $this->assertDenied(fn () => app(ManageContacts::class)->report($this->eve->user, $this->ana, 'spam', null, $message), 'A report about an unseen message was accepted.');

        Livewire::actingAs($this->ben->user)->test(MessageReports::class)->assertForbidden();
        $admin = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($admin->user)->create(['role' => PlatformRole::Superadmin]);
        Livewire::actingAs($admin->user)->test(MessageReports::class)->assertSee('Buy cheap stuff')
            ->call('markReviewed', MessageReport::query()->sole()->uuid);
        $this->assertSame('reviewed', MessageReport::query()->sole()->status);
    }

    public function test_inbox_page_renders_list_and_open_conversation(): void
    {
        $this->befriend($this->ana, $this->ben);
        $conversation = $this->direct($this->ana, $this->ben);
        $this->send($this->ben, $conversation, 'Welcome!');

        Livewire::actingAs($this->ana->user)->withQueryParams(['c' => $conversation->uuid])->test(Index::class)
            ->assertSee($this->ben->user->username)
            ->assertSee('Welcome!');
        Livewire::actingAs($this->eve->user)->withQueryParams(['c' => $conversation->uuid])->test(Index::class)
            ->assertSet('open', '')
            ->assertDontSee('Welcome!');
        $this->actingAs($this->ana->user)->get(route('messages.show', $conversation))->assertRedirect(route('messages.index', ['c' => $conversation->uuid]));
    }

    public function test_a_person_can_always_answer_someone_who_wrote_to_them(): void
    {
        // Ben accepts everyone, Ana only contacts: Ana writes first, Ben may answer.
        $this->ben->user->forceFill(['message_privacy' => 'everyone'])->save();
        $conversation = $this->direct($this->ana, $this->ben);
        $this->assertDenied(fn () => $this->send($this->ben, $conversation, 'Can I write first?'), 'Ben wrote first to a contacts-only person.');
        $this->send($this->ana, $conversation, 'Hi Ben');
        $this->assertSame('Thanks!', $this->send($this->ben, $conversation, 'Thanks!')->body);
    }

    public function test_posting_in_existing_chats_puts_them_in_the_inbox(): void
    {
        $group = app(CreateGroup::class)->execute($this->ana, 'Choir', null);
        $space = GroupSpace::query()->where('group_id', $group->id)->where('kind', 'chat')->firstOrFail();
        app(PostGroupSpaceMessage::class)->execute($space, $this->ana->user, 'Old chat still works');

        $row = app(Inbox::class)->for($this->ana->user->fresh())->sole();
        $this->assertSame('group_space', $row['kind']);
        $this->assertSame(0, $row['unread']);
    }
}
