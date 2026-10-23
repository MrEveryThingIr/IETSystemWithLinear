<?php

namespace Tests\Feature\LivingPlanner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Models\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_migration_rolls_back_its_index_and_reapplies_without_losing_plans(): void
    {
        $user = Actor::factory()->create()->user;
        $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Preserved plan');
        $migration = require database_path('migrations/2026_11_02_000000_add_living_occurrence_execution.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('plan_occurrences', 'window_closed_at'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('plan_occurrences', 'window_closed_at'));
        $this->assertSame('Preserved plan', $plan->fresh()->title);
    }
}
