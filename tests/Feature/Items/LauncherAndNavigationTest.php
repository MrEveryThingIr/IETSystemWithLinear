<?php

namespace Tests\Feature\Items;

use App\Models\Actor;
use App\Support\IntentionLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class LauncherAndNavigationTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_launcher_offers_only_usable_choices_and_puts_the_page_context_first(): void
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['profile', 'market', 'planner']);
        $launcher = app(IntentionLauncher::class);

        $this->assertSame(['need', 'offer', 'ability', 'interest', 'plan'], array_column($launcher->choicesFor($actor->user, 'dashboard'), 'key'));
        $this->assertSame('plan', $launcher->choicesFor($actor->user, 'planner.show')[0]['key']);
        $this->assertSame(['interest', 'ability'], array_slice(array_column($launcher->choicesFor($actor->user, 'items.index'), 'key'), 0, 2));
        $this->assertLessThanOrEqual(5, count($launcher->choicesFor($actor->user, 'dashboard')), 'The launcher stays a short list, not a second menu.');

        $plain = Actor::factory()->create();
        $this->publishSurfaces($plain->user, ['profile']);
        $this->assertSame(['ability', 'interest'], array_column($launcher->choicesFor($plain->user, 'dashboard'), 'key'));
        $this->assertSame([], $launcher->choicesFor(null));
    }

    public function test_every_signed_in_page_has_the_phone_bar_the_launcher_and_a_separate_planner(): void
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['profile', 'market', 'deals', 'planner', 'messages', 'money']);

        $this->actingAs($actor->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-bottom-bar', false)
            ->assertSee('data-bottom-item="deals"', false)
            ->assertSee('data-bottom-item="planner"', false)
            ->assertSee('data-bottom-item="messages"', false)
            ->assertSee('data-launcher-fab', false)
            ->assertSee('data-launcher-choice="need"', false)
            ->assertSee('data-nav-primary="planner"', false)
            ->assertSee(route('items.index'), false);
    }

    public function test_navigation_and_form_words_never_show_internal_vocabulary(): void
    {
        $banned = ['profession', 'has skill', 'intent', 'proposal', 'obligation', 'fulfil', 'ledger', 'capabilit'];
        foreach (['en'] as $locale) {
            $strings = [
                ...array_values(trans('experience.navigation', [], $locale)),
                ...array_values(trans('ui.navigation', [], $locale)),
                ...collect(trans('launcher', [], $locale))->flatten()->all(),
                ...collect(trans('items', [], $locale))->flatten()->all(),
                ...collect(trans('capabilities', [], $locale))->flatten()->all(),
            ];
            foreach ($strings as $string) {
                foreach ($banned as $word) {
                    $this->assertStringNotContainsStringIgnoringCase($word, (string) $string, "\"{$string}\" uses an internal word.");
                }
            }
        }
    }
}
