<?php

namespace Tests\Feature\Surfaces;

use App\Models\FeatureSurfaceGrant;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Services\Surfaces\ExperienceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceCompositionNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_navigation_composes_granted_surfaces_into_human_goal_destinations(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        foreach (['profile', 'market', 'money', 'deals', 'planner', 'business', 'groups', 'content'] as $surface) {
            FeatureSurfaceGrant::query()->create([
                'user_id' => $user->getKey(),
                'surface_key' => $surface,
                'granted_at' => now(),
            ]);
        }

        $navigation = app(ExperienceNavigation::class)->for($user);
        $primary = collect($navigation['primary'])->keyBy('key');

        // Daily destinations, one task each; the Planner stands on its own.
        $this->assertSame(
            ['market', 'deals', 'planner', 'money'],
            collect($navigation['primary'])->pluck('key')->all()
        );
        $this->assertSame('intents.index', $primary['market']['route']);
        $this->assertSame('deals.index', $primary['deals']['route']);
        $this->assertSame('planner.index', $primary['planner']['route']);
        $this->assertSame('bookkeeping.index', $primary['money']['route']);
        $this->assertSame(['planner'], collect($primary['planner']['items'])->pluck('key')->all());
        $this->assertSame(['money'], collect($primary['money']['items'])->pluck('key')->all());

        $spaces = collect($navigation['spaces'])->keyBy('key');
        $this->assertSame(['organizations', 'content'], $spaces->keys()->all());
        $this->assertSame(['business', 'groups'], collect($spaces['organizations']['items'])->pluck('key')->all());

        $this->assertSame(['items', 'profile'], collect($navigation['account'])->pluck('key')->all());
        $this->assertSame('items.index', $navigation['account'][0]['route']);
        $this->assertSame([], $navigation['admin']);
    }

    public function test_admin_tools_are_separated_from_ordinary_primary_navigation(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();
        PlatformAccessGrant::factory()->for($user)->create();

        $navigation = app(ExperienceNavigation::class)->for($user);

        $primarySurfaceKeys = collect($navigation['primary'])
            ->flatMap(fn (array $destination) => collect($destination['items'])->pluck('key'))
            ->all();

        $this->assertNotContains('actors', $primarySurfaceKeys);
        $this->assertNotContains('access-invitations', $primarySurfaceKeys);
        $this->assertNotContains('development-origins', $primarySurfaceKeys);

        $this->assertEqualsCanonicalizing(
            ['access-invitations', 'development-origins', 'actors', 'category-review', 'field-review', 'message-reports', 'day-texts', 'publication-control'],
            collect($navigation['admin'])->pluck('key')->all()
        );
    }

    public function test_unpublished_facilities_do_not_leak_through_experience_grouping(): void
    {
        $user = User::factory()->create();
        $user->actor()->create();

        FeatureSurfaceGrant::query()->create([
            'user_id' => $user->getKey(),
            'surface_key' => 'profile',
            'granted_at' => now(),
        ]);

        $navigation = app(ExperienceNavigation::class)->for($user);

        $this->assertSame([], $navigation['primary']);
        $this->assertSame([], $navigation['spaces']);
        $this->assertSame(['items', 'profile'], collect($navigation['account'])->pluck('key')->all());
    }
}
