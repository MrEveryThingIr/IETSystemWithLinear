<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationOutbox;
use App\Livewire\Notifications\Index;
use App\Models\User;
use App\Support\NotificationOutboxWriter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationExperienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_read_only_their_own_durable_notifications(): void
    {
        Bus::fake();
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceOutbox = app(NotificationOutboxWriter::class)->request(
            $alice,
            'experience:alice',
            'test.notice',
            ['title_key' => 'notifications.messages.generic_title', 'url' => route('dashboard')],
        );
        $bobOutbox = app(NotificationOutboxWriter::class)->request(
            $bob,
            'experience:bob',
            'test.notice',
            ['title_key' => 'notifications.messages.generic_title', 'url' => route('dashboard')],
        );

        (new DeliverNotificationOutbox($aliceOutbox->id))->handle();
        (new DeliverNotificationOutbox($bobOutbox->id))->handle();

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->assertSee('IET update')
            ->call('markRead', $aliceOutbox->uuid)
            ->assertHasNoErrors();

        $this->assertNotNull($alice->notifications()->findOrFail($aliceOutbox->uuid)->read_at);
        $this->assertNull($bob->notifications()->findOrFail($bobOutbox->uuid)->read_at);

        Livewire::actingAs($alice)
            ->test(Index::class)
            ->call('markRead', $bobOutbox->uuid)
            ->assertNotFound();
    }

    public function test_notification_route_requires_verified_authenticated_account(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('notifications.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->create())
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifications');
    }
}
