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

        $this->assertSame('experience.needs-offers', $primary['needs-offers']['route']);
        $this->assertSame('experience.work', $primary['work']['route']);
        $this->assertSame('experience.organizations', $primary['organizations']['route']);
        $this->assertSame('money.index', $primary['money']['route']);
        $this->assertSame('experience.content', $primary['content']['route']);

        $this->assertSame(['help'], collect($navigation['help'])->pluck('key')->all());
        $this->assertSame('experience.help', $navigation['help'][0]['route']);

        $this->assertContains('deals', collect($primary['work']['items'])->pluck('key')->all());
        $this->assertContains('planner', collect($primary['work']['items'])->pluck('key')->all());
    }

    public function test_hubs_respect_publication_and_do_not_leak_unpublished_facilities(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['business']);

        $this->actingAs($actor->user)
            ->get(route('experience.organizations'))
            ->assertOk()
            ->assertSee(route('businesses.index'), false)
            ->assertDontSee(route('groups.index'), false);

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

    public function test_work_hub_composes_deals_and_planner_without_replacing_either_kernel(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['deals', 'planner']);

        $this->actingAs($actor->user)
            ->get(route('experience.work'))
            ->assertOk()
            ->assertSee('Needs my attention')
            ->assertSee('Active work')
            ->assertSee('Schedule')
            ->assertSee(route('deals.index'), false)
            ->assertSee(route('planner.index'), false);
    }

    public function test_money_route_is_now_the_daily_hub_and_detailed_accounts_remain_reachable(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['money']);

        $this->actingAs($actor->user)
            ->get(route('money.index'))
            ->assertOk()
            ->assertSee('What is outstanding')
            ->assertSee('Accounts & activity')
            ->assertSee(route('money.accounts'), false);

        $this->actingAs($actor->user)
            ->get(route('money.accounts'))
            ->assertOk();
    }

    public function test_content_and_help_are_single_composed_destinations(): void
    {
        $actor = Actor::factory()->create();

        $this->publishSurfaces($actor->user, ['content', 'manual', 'system-map']);

        $this->actingAs($actor->user)
            ->get(route('experience.content'))
            ->assertOk()
            ->assertSee('My Content')
            ->assertSee('Explore')
            ->assertSee('Create')
            ->assertSee(route('contexts.personal'), false)
            ->assertSee(route('content.library'), false);

        $this->actingAs($actor->user)
            ->get(route('experience.help'))
            ->assertOk()
            ->assertSee(route('manual'), false)
            ->assertSee(route('system-map'), false);
    }
}
