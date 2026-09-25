<?php

namespace Tests\Feature;

use App\Events\UserInboxChanged;
use App\Jobs\DeliverNotificationOutbox;
use App\Models\Actor;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Support\NotificationOutboxDispatcher;
use App\Support\NotificationOutboxWriter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationKernelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_request_is_deduplicated_and_queued(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $writer = app(NotificationOutboxWriter::class);

        $first = $writer->request($user, 'test:one:'.$user->id, 'test.notice', [
            'title_key' => 'notifications.messages.generic_title',
            'url' => route('dashboard'),
        ]);
        $second = $writer->request($user, 'test:one:'.$user->id, 'test.notice', [
            'title_key' => 'notifications.messages.generic_title',
            'url' => route('dashboard'),
        ]);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertSame('/dashboard', $first->data['url']);
        Bus::assertDispatched(DeliverNotificationOutbox::class, 2);
    }

    public function test_delivery_is_idempotent_and_database_inbox_is_authoritative(): void
    {
        Event::fake([UserInboxChanged::class]);
        $user = User::factory()->create();
        $outbox = NotificationOutbox::query()->create([
            'recipient_user_id' => $user->id,
            'dedupe_key' => 'test:delivery:'.$user->id,
            'kind' => 'test.notice',
            'data' => ['title_key' => 'notifications.messages.generic_title', 'url' => '/dashboard'],
            'requested_at' => now(),
        ]);

        $job = new DeliverNotificationOutbox($outbox->id);
        $job->handle();
        $job->handle();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($outbox->refresh()->dispatched_at);
        $this->assertSame(1, $user->refresh()->unreadNotifications()->count());
        Event::assertDispatched(UserInboxChanged::class, 1);
    }

    public function test_inactive_recipient_is_discarded_without_inbox_record(): void
    {
        Event::fake([UserInboxChanged::class]);
        $user = User::factory()->create();
        $outbox = NotificationOutbox::query()->create([
            'recipient_user_id' => $user->id,
            'dedupe_key' => 'test:inactive:'.$user->id,
            'kind' => 'test.notice',
            'data' => ['title_key' => 'notifications.messages.generic_title'],
            'requested_at' => now(),
        ]);
        $user->forceFill(['status' => 'suspended'])->save();

        (new DeliverNotificationOutbox($outbox->id))->handle();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertNotNull($outbox->refresh()->discarded_at);
        Event::assertNotDispatched(UserInboxChanged::class);
    }

    public function test_dispatcher_requeues_only_pending_records(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $pending = NotificationOutbox::query()->create([
            'recipient_user_id' => $user->id,
            'dedupe_key' => 'test:pending:'.$user->id,
            'kind' => 'test.notice',
            'data' => [],
            'requested_at' => now(),
        ]);
        $delivered = NotificationOutbox::query()->create([
            'recipient_user_id' => $user->id,
            'dedupe_key' => 'test:done:'.$user->id,
            'kind' => 'test.notice',
            'data' => [],
            'requested_at' => now(),
        ]);
        $delivered->markDispatched();

        $this->assertSame(1, app(NotificationOutboxDispatcher::class)->dispatchPending());
        Bus::assertDispatched(
            DeliverNotificationOutbox::class,
            fn (DeliverNotificationOutbox $job): bool => $job->outboxId === $pending->id,
        );
    }

    public function test_private_broadcast_channel_is_user_scoped(): void
    {
        $alice = Actor::factory()->create()->user;
        $bob = Actor::factory()->create()->user;

        $callback = Broadcast::connection()->getChannels()->get('users.{userId}');

        $this->assertIsCallable($callback);
        $this->assertTrue($callback($alice, $alice->id));
        $this->assertFalse($callback($bob, $alice->id));
    }
}
