<?php

namespace Tests\Feature;

use App\Events\UserInboxChanged;
use App\Livewire\Notifications\NavItem;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationRealtimeConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reverb_transport_is_configured_but_not_the_default_test_truth(): void
    {
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));
        $this->assertSame('none', config('reverb.apps.apps.0.accept_client_events_from'));
        $this->assertContains('localhost', config('reverb.apps.apps.0.allowed_origins'));
    }

    public function test_inbox_changed_broadcast_is_private_and_minimal(): void
    {
        $event = new UserInboxChanged(42, '00000000-0000-4000-8000-000000000042');
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-users.42', $channels[0]->name);
        $this->assertSame(['notification_id' => '00000000-0000-4000-8000-000000000042'], $event->broadcastWith());
    }

    public function test_notification_nav_badge_reconciles_durable_unread_count(): void
    {
        $user = User::factory()->create();

        $user->notifications()->create([
            'id' => '00000000-0000-4000-8000-000000000043',
            'type' => 'test.notice',
            'data' => ['title_key' => 'notifications.messages.generic_title'],
        ]);

        Livewire::actingAs($user)
            ->test(NavItem::class)
            ->assertSee('1')
            ->dispatch('notification-inbox-changed')
            ->assertSee('1');
    }
}
