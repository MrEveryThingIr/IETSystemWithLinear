<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationOutbox;
use App\Livewire\Intents\Composer;
use App\Livewire\Notifications\NavItem;
use App\Livewire\Relationships\Create as DealStart;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\NotificationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** Someone who wants to talk about your post must reach you even when no queue worker is running. */
class DealNotificationsTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_an_invitation_is_notified_even_without_a_queue_worker_and_shown_on_the_post(): void
    {
        Bus::fake([DeliverNotificationOutbox::class]); // no worker: queued delivery never runs
        [$alice, $bob] = [Actor::factory()->create(), Actor::factory()->create()];
        foreach ([$alice, $bob] as $a) {
            $this->publishSurfaces($a->user, ['market', 'deals', 'notifications']);
        }

        Livewire::actingAs($alice->user)->test(Composer::class)->call('choose', 'offer')->call('chooseTopic', 'vehicle')
            ->set('core.title', 'Peugeot 206')->set('core.description', 'Used car')->set('core.visibility', 'public')
            ->call('addTerm', 'price')->set('priceMode', 'fixed')->set('price', '2,000,000')->call('publish')->assertHasNoErrors();
        $offer = ActorProfileIntent::query()->latest('id')->firstOrFail();

        Livewire::actingAs($bob->user)->withQueryParams(['intent' => $offer->uuid])->test(DealStart::class)->set('message', 'Hello')->call('save')->assertHasNoErrors();

        $outbox = NotificationOutbox::query()->where('recipient_user_id', $alice->user->id)->where('kind', 'relationship.event')->sole();
        $this->assertNull($outbox->dispatched_at);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $alice->user->id)->count());

        // The next time Alice looks (any page has the bell), it is delivered inline.
        $this->travel(10)->seconds();
        Livewire::actingAs($alice->user)->test(NavItem::class);
        $this->assertSame(1, $alice->user->unreadNotifications()->count());
        $this->assertNotNull($outbox->fresh()->dispatched_at);
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $bob->user->id)->count(), 'the sender is not notified of their own action');

        // The owner sees the waiting conversation on the post itself.
        $this->actingAs($alice->user)->get(route('intents.show', $offer))->assertOk()->assertSee('data-intent-deals', false);
    }
}
