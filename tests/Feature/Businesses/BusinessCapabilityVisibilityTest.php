<?php

namespace Tests\Feature\Businesses;

use App\Models\User;
use App\Services\Business\BusinessService;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCapabilityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_internal_workspace_is_for_operators_only(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک مهوری',
            'kind' => 'real_estate',
            'visibility' => 'public',
            'status' => 'active',
        ]);

        $outsider = User::factory()->create();
        $outsider->actor()->create();

        app(FeatureSurfaceGrantService::class)->sync($outsider, ['business'], null);

        $this->actingAs($outsider)
            ->get(route('businesses.index'))
            ->assertOk()
            ->assertDontSee('مشاور املاک مهوری');

        $this->actingAs($outsider)
            ->get(route('businesses.show', $business))
            ->assertForbidden();

        $this->get(route('public.businesses.show', ['business' => $business->slug]))
            ->assertOk()
            ->assertSee('مشاور املاک مهوری');
    }

    public function test_unpublished_adjacent_capabilities_do_not_render_in_business_workspace(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Focused Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        app(FeatureSurfaceGrantService::class)->sync($owner, ['business'], null);

        $response = $this->actingAs($owner)
            ->get(route('businesses.show', $business))
            ->assertOk();

        $response
            ->assertSee(route('businesses.clients.index', $business), false)
            ->assertSee(route('businesses.catalog.index', $business), false)
            ->assertDontSee(route('planner.index'), false)
            ->assertDontSee(route('deals.index'), false)
            ->assertDontSee(route('money.index'), false)
            ->assertDontSee(__('business.show.money.title'));

        app(FeatureSurfaceGrantService::class)->sync(
            $owner,
            ['business', 'planner', 'deals'],
            null,
        );

        $response = $this->actingAs($owner->refresh())
            ->get(route('businesses.show', $business))
            ->assertOk();

        $response
            ->assertSee(route('planner.index'), false)
            ->assertSee(route('deals.index'), false)
            ->assertSee(route('money.index'), false)
            ->assertSee(__('business.show.money.title'));
    }
}
