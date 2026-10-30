<?php

namespace Tests\Feature;

use App\Actions\Messaging\OpenDirectConversation;
use App\Models\Actor;
use App\Support\IntentionLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class MobileMessagingShellTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_launcher_visibility_uses_the_route_context_without_losing_browsing_choices(): void
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['market', 'planner', 'messages']);
        $launcher = app(IntentionLauncher::class);
        foreach (['messages.index', 'messages.show', 'contexts.conversation', 'intents.compose', 'intents.compose.edit', 'planner.create', 'planner.edit', 'planner.habits.create', 'relationships.create', 'proposals.create', 'contracts.create', 'commitments.create', 'contexts.contents.studio', 'groups.spaces.contents.blocks'] as $route) {
            $this->assertFalse($launcher->visibleFor($actor->user, $route), $route);
        }
        foreach (['dashboard', 'contacts.index', 'planner.index', 'intents.index', 'items.index'] as $route) {
            $this->assertTrue($launcher->visibleFor($actor->user, $route), $route);
        }
        $this->assertFalse($launcher->visibleFor(null, 'dashboard'));
    }

    public function test_conversation_shell_has_no_global_creation_triggers_in_either_direction(): void
    {
        $this->withoutVite();
        [$actor, $other] = [Actor::factory()->create(), Actor::factory()->create()];
        $other->user->forceFill(['message_privacy' => 'everyone'])->save();
        $this->publishSurfaces($actor->user, ['messages', 'market', 'planner']);
        $conversation = app(OpenDirectConversation::class)->execute($actor->user, $other);
        foreach (['en' => 'ltr', 'fa' => 'rtl'] as $locale => $direction) {
            $actor->user->forceFill(['locale' => $locale])->save();
            $this->actingAs($actor->user)->get(route('messages.index', ['c' => $conversation->uuid]))
                ->assertOk()->assertSee('dir="'.$direction.'"', false)
                ->assertSee('data-messaging-layout', false)->assertSee('data-message-list', false)
                ->assertSee('data-message-composer', false)->assertSee('data-composer', false)
                ->assertSee('data-bottom-bar', false)
                ->assertDontSee('data-launcher-fab', false)->assertDontSee('data-launcher-open', false)
                ->assertDontSee('data-launcher-choice', false)
                ->assertDontSee('min-h-[26rem]', false)->assertDontSee('100dvh-7.5rem', false);
            $this->get(route('dashboard'))->assertOk()->assertSee('data-launcher-fab', false)
                ->assertSee('data-launcher-open', false)->assertDontSee('data-messaging-layout', false);
        }
        $this->get(route('contexts.conversation', $conversation->context))->assertOk()
            ->assertSee('data-composer', false)->assertDontSee('data-launcher-fab', false)
            ->assertDontSee('data-messaging-layout', false);
        $this->get(route('messages.index'))->assertOk()->assertDontSee('data-launcher-fab', false);
    }
}
