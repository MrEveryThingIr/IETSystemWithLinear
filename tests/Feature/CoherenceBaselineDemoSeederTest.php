<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\ProfileIntentKind;
use App\Support\IetPosition;
use App\Support\IntentMatchFinder;
use Database\Seeders\CoherenceBaselineDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoherenceBaselineDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_baseline_seed_is_visible_repeatable_and_economically_meaningful(): void
    {
        $this->seed(CoherenceBaselineDemoSeeder::class);
        $this->seed(CoherenceBaselineDemoSeeder::class);

        $provider = User::query()->where('email', 'test@example.com')->sole();

        $names = Business::query()
            ->where('owner_actor_id', $provider->actor->id)
            ->pluck('name')
            ->all();

        $this->assertContains('Safdar Real Estate Office', $names);
        $this->assertContains('Atlas Inspection Services', $names);
        $this->assertContains('Everyday Tools Store', $names);
        $this->assertContains('Bright Home Services', $names);
        $this->assertCount(4, $names);

        $providerPosition = app(IetPosition::class)->forUser($provider);

        $this->assertSame(120, $providerPosition['wallet_minor']);
        $this->assertSame(430, $providerPosition['receivable_minor']);
        $this->assertSame(0, $providerPosition['payable_minor']);
        $this->assertSame(550, $providerPosition['net_position_minor']);
        $this->assertSame(120, $providerPosition['cashout_eligible_minor']);

        $expected = [
            'inspection-buyer@example.com' => 0,
            'tool-buyer@example.com' => -80,
            'home-buyer@example.com' => -150,
            'property-buyer@example.com' => -200,
        ];

        foreach ($expected as $email => $net) {
            $buyer = User::query()->where('email', $email)->sole();
            $this->assertSame($net, app(IetPosition::class)->forUser($buyer)['net_position_minor']);

            $need = $buyer->actor->profile->intents()
                ->where('kind', ProfileIntentKind::Need->value)
                ->where('metadata->source', 'coherence_baseline_demo')
                ->sole();

            $this->assertTrue(
                app(IntentMatchFinder::class)->find($buyer, $need)->isNotEmpty(),
                $email.' should have at least one visible canonical market match.',
            );
        }

        $this->assertDatabaseCount('financial_obligations', 4);
        $this->assertDatabaseCount('settlements', 1);
    }
}
