<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Services\Surfaces\ExperienceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ExperienceHubTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_primary_navigation_points_to_goal_hubs_instead_of_subsystem_choices(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, [
            'market',
            'deals',
            'planner',
            'business',
            'groups',
            'money',
            'content',
            'manual',
            'system-map',
        ]);

        $navigation = app(ExperienceNavigation::class)->for($actor->user);
        $primary = collect($navigation['primary'])->keyBy('key');

        $spaces = collect($navigation['spaces'])->keyBy('key');

        $this->assertSame('experience.needs-offers', $primary['market']['route']);
        $this->assertSame('deals.index', $primary['deals']['route']);
        $this->assertSame('planner.index', $primary['planner']['route']);
        $this->assertSame('bookkeeping.index', $primary['money']['route']);
        $this->assertSame('experience.organizations', $spaces['organizations']['route']);
        $this->assertSame('experience.content', $spaces['content']['route']);

        $this->assertSame(['help'], collect($navigation['help'])->pluck('key')->all());
        $this->assertSame('experience.help', $navigation['help'][0]['route']);
    }

    public function test_hubs_respect_publication_and_do_not_leak_unpublished_facilities(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['business']);

        $this->actingAs($actor->user)
            ->get(route('experience.organizations'))
            ->assertOk()
            ->assertSee(route('businesses.index'), false)
            ->assertDontSee(route('groups.index'), false)
            ->assertDontSee('Groups');

        $this->actingAs($actor->user)
            ->get(route('experience.work'))
            ->assertForbidden();

        $this->actingAs($actor->user)
            ->get(route('experience.needs-offers'))
            ->assertForbidden();
    }

    public function test_needs_and_offers_hub_is_the_simple_entry_before_the_market_directory(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['market']);

        $this->actingAs($actor->user)
            ->get(route('experience.needs-offers'))
            ->assertOk()
            ->assertSee('Needs & Offers')
            ->assertSee('Mine')
            ->assertSee('Discover')
            ->assertSee('Matches')
            ->assertSee(route('intents.create'), false)
            ->assertSee(route('intents.index'), false);
    }

    public function test_old_work_links_land_on_deals_or_the_planner_and_nothing_it_showed_is_lost(): void
    {
        $both = Actor::factory()->create();
        $this->publishSurfaces($both->user, ['deals', 'planner']);
        $this->actingAs($both->user)->get(route('experience.work'))->assertRedirect(route('deals.index'));

        $plannerOnly = Actor::factory()->create();
        $this->publishSurfaces($plannerOnly->user, ['planner']);
        $this->actingAs($plannerOnly->user)->get(route('experience.work'))->assertRedirect(route('planner.index'));

        // What the Work page listed under deals now heads the Deals page.
        $this->actingAs($both->user)->get(route('deals.index'))->assertOk()->assertSee(__('deals.title'));
    }

    public function test_money_destination_opens_bookkeeping_and_the_old_finance_pages_are_gone(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['money']);

        $this->actingAs($actor->user)
            ->get(route('bookkeeping.index'))
            ->assertOk()
            ->assertSee(__('bookkeeping.personal.title'));

        foreach (['/money', '/money/accounts', '/accounting', '/exchange'] as $retired) {
            $this->actingAs($actor->user)->get($retired)->assertNotFound();
        }
    }

    public function test_help_hub_only_shows_published_help_tools(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['manual']);

        $this->actingAs($actor->user)
            ->get(route('experience.help'))
            ->assertOk()
            ->assertSee(route('manual'), false)
            ->assertDontSee(route('system-map'), false);
    }

    public function test_content_and_help_are_single_composed_destinations(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['content', 'manual', 'system-map']);

        $this->actingAs($actor->user)
            ->get(route('experience.content'))
            ->assertOk()
            ->assertSee('Content')
            ->assertSee('Drafts')
            ->assertSee('Shared with me')
            ->assertSee('Create')
            ->assertSee(route('content.library'), false);

        $this->actingAs($actor->user)
            ->get(route('experience.help'))
            ->assertOk()
            ->assertSee(route('manual'), false)
            ->assertSee(route('system-map'), false);
    }
}
