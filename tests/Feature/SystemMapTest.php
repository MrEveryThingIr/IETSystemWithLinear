<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\User;
use App\Support\SystemMap\SystemMapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_open_system_map(): void
    {
        $actor = Actor::factory()->create();
        $this->withoutVite();

        $this->actingAs($actor->user)
            ->get(route('system-map'))
            ->assertOk()
            ->assertSee('System Map')
            ->assertSee('Personal accounting')
            ->assertSee('Financial obligations')
            ->assertSee('Planner & temporal fabric')
            ->assertSee('data-system-map', false);
    }

    public function test_system_map_requires_authentication_and_verification(): void
    {
        $this->get('/system-map')->assertRedirect(route('login'));

        $user = User::factory()->unverified()->create();
        Actor::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/system-map')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_curated_graph_has_unique_valid_nodes_and_financial_review_path(): void
    {
        $map = app(SystemMapBuilder::class)->build();

        $ids = collect($map['nodes'])->pluck('id');

        $this->assertSame($ids->count(), $ids->unique()->count());

        foreach ($map['edges'] as $edge) {
            $this->assertContains($edge['source'], $ids);
            $this->assertContains($edge['target'], $ids);
        }

        $this->assertSame(
            ['planner', 'fulfillment', 'obligations', 'settlements', 'accounting', 'financial_lab', 'payments', 'finance_review'],
            $map['presets']['finance'],
        );

        $this->assertTrue(
            collect($map['edges'])->contains(
                fn (array $edge): bool => $edge['source'] === 'fulfillment' && $edge['target'] === 'obligations',
            ),
        );

        $this->assertTrue(
            collect($map['edges'])->contains(
                fn (array $edge): bool => $edge['source'] === 'settlements' && $edge['target'] === 'accounting',
            ),
        );
    }
}
