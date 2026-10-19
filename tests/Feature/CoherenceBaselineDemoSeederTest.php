<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ContractServiceTerm;
use App\Models\User;
use App\ProfileIntentKind;
use App\Support\BusinessEconomyProjection;
use App\Support\IntentMatchFinder;
use Database\Seeders\CoherenceBaselineDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoherenceBaselineDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_baseline_seed_is_visible_repeatable_and_priced_in_toman(): void
    {
        $this->seed(CoherenceBaselineDemoSeeder::class);
        $this->seed(CoherenceBaselineDemoSeeder::class);

        $provider = User::query()->where('username', (string) config('bootstrap.superadmin.username'))->sole();

        $names = Business::query()
            ->where('owner_actor_id', $provider->actor->id)
            ->pluck('name')
            ->all();

        $this->assertContains('مشاور املاک مهوری', $names);
        $this->assertContains('Atlas Inspection Services', $names);
        $this->assertContains('Everyday Tools Store', $names);
        $this->assertContains('Bright Home Services', $names);
        $this->assertCount(4, $names);

        foreach (['inspection-buyer@example.com', 'tool-buyer@example.com', 'home-buyer@example.com', 'property-buyer@example.com'] as $email) {
            $buyer = User::query()->where('email', $email)->sole();

            $need = $buyer->actor->profile->intents()
                ->where('kind', ProfileIntentKind::Need->value)
                ->where('metadata->source', 'coherence_baseline_demo')
                ->sole();

            $this->assertTrue(
                app(IntentMatchFinder::class)->find($buyer, $need)->isNotEmpty(),
                $email.' should have at least one visible canonical market match.',
            );
        }

        $businessEconomy = app(BusinessEconomyProjection::class);

        $atlas = Business::query()->where('name', 'Atlas Inspection Services')->sole();
        $atlasProjection = $businessEconomy->forBusiness($atlas);
        $this->assertSame(1, $atlasProjection['deal_count']);
        $this->assertSame(1, $atlasProjection['contract_count']);

        $tools = Business::query()->where('name', 'Everyday Tools Store')->sole();
        $toolsProjection = $businessEconomy->forBusiness($tools);
        $this->assertSame(1, $toolsProjection['deal_count']);
        $this->assertSame(1, $toolsProjection['contract_count']);

        $this->assertDatabaseCount('relationships', 4);
        $this->assertDatabaseCount('contracts', 4);
        $this->assertTrue(ContractServiceTerm::query()->whereHas('monetaryUnit', fn ($query) => $query->where('code', 'IRT'))->count() === 4);
    }
}
