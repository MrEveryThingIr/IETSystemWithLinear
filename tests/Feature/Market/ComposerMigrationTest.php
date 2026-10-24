<?php

namespace Tests\Feature\Market;

use App\Models\ActorProfileIntent;
use App\Models\Relationship;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ComposerMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_additive_migration_rolls_back_and_backfills_existing_posts_and_deals(): void
    {
        $migration = require database_path('migrations/2026_11_10_000000_create_adaptive_intention_foundation.php');
        $migration->down();
        $legacy = ActorProfileIntent::factory()->create(['currency_code' => 'USD', 'cash_min' => 50]);
        $deal = Relationship::factory()->create(['originating_intent_id' => $legacy->id]);
        $migration->up();
        $this->assertNotNull($legacy->fresh()->published_version_id);
        $this->assertSame($legacy->fresh()->published_version_id, $deal->fresh()->originating_intent_version_id);
        $this->assertSame('USD', $legacy->fresh()->currency_code);
    }
}
