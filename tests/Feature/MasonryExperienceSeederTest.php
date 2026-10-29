<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\ActorProfileIntent;
use App\Models\Bookkeeping\Transaction;
use App\Models\CommitmentEvent;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\Fulfillment;
use App\Models\FulfillmentReview;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Deals\DealMoney;
use Carbon\Carbon;
use Database\Seeders\MasonryExperienceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class MasonryExperienceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_demo_is_playable_historical_and_repeat_safe(): void
    {
        $this->app->instance('env', 'local');
        foreach (['IET_MASONRY_DEMO_ALLOW' => '1', 'IET_MASONRY_DEMO_PASSWORD' => 'Masonry-Test-Only-2026!'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }
        $clock = Carbon::getTestNow();
        try {
            $this->seed(MasonryExperienceSeeder::class);
            $this->assertEquals($clock, Carbon::getTestNow());
            $deal = Relationship::query()->sole();
            $need = ActorProfileIntent::query()->sole();
            $this->assertSame('need', $need->kind->value);
            $this->assertSame($need->id, $deal->originating_intent_id);
            $this->assertNotNull($deal->originating_intent_version_id);
            $this->assertSame('active', Contract::query()->sole()->status->value);
            $this->assertSame(2, ContractAcceptance::query()->count());
            $this->assertSame(11, Fulfillment::query()->count());
            $this->assertSame(4, Fulfillment::query()->where('unit', 'day')->count());
            $this->assertSame(7, Fulfillment::query()->where('unit', 'square_metre')->count());
            $this->assertSame(10, FulfillmentReview::query()->count());
            $this->assertSame(1, Fulfillment::query()->where('status', 'submitted')->count());
            $partial = FulfillmentReview::query()->whereNotNull('accepted_quantity')->sole();
            $this->assertEquals(15, $partial->accepted_quantity);
            $this->assertEquals(17, $partial->fulfillment->quantity);
            $entries = Transaction::query()->orderBy('id')->get();
            $this->assertCount(12, $entries);
            $this->assertSame(10, $entries->where('type.value', 'obligation')->count());
            $this->assertSame(2, $entries->where('type.value', 'payment')->count());
            $timestamps = $entries->map(fn ($entry) => $entry->created_at->toIso8601String());
            $this->assertSame($timestamps->sort()->values()->all(), $timestamps->all());
            $this->assertSame(['confirmed'], $entries->pluck('status.value')->unique()->values()->all());
            foreach ($entries as $entry) {
                $this->assertSame('iet-demo-employer', $entry->fromParty->actor->user->username);
                $this->assertSame('iet-demo-mason', $entry->toParty->actor->user->username);
            }
            $money = app(DealMoney::class);
            $summary = $money->summary($money->items($deal), $money->reportedValue($deal), 0, $money->onAccount($deal));
            $this->assertSame(31500000, $summary['earned']);
            $this->assertSame(5500000, $summary['received_total']);
            $this->assertSame(26000000, $summary['roughly_owed']);
            $this->assertSame(2700000, $summary['reported_value']);
            $ask = $money->asks($deal)[0];
            $this->assertSame(4000000, $ask['amount']);
            $this->assertSame(3000000, $ask['paid']);
            $this->assertSame('open', $ask['status']);
            $this->assertSame(21, CommitmentEvent::query()->whereIn('event_type', ['fulfillment_submitted', 'fulfillment_reviewed'])->count());
            foreach (['iet-demo-employer', 'iet-demo-mason'] as $username) {
                Auth::logout();
                Livewire::test(Login::class)->set('email', $username)->set('password', 'Masonry-Test-Only-2026!')->call('login')->assertHasNoErrors();
                $user = User::query()->where('username', $username)->sole();
                $this->assertAuthenticatedAs($user);
                $this->assertSame('active', $user->actor->status);
                foreach (['agreement', 'work', 'money', 'conversation'] as $tab) {
                    $this->actingAs($user)->get('/relationships/'.$deal->uuid.'?tab='.$tab)->assertOk();
                }
            }
            $counts = [User::query()->count(), Transaction::query()->count(), CommitmentEvent::query()->count()];
            try {
                $this->seed(MasonryExperienceSeeder::class);
                $this->fail('Duplicate run must be rejected.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('already exists', $e->getMessage());
            }
            $this->assertSame($counts, [User::query()->count(), Transaction::query()->count(), CommitmentEvent::query()->count()]);
        } finally {
            foreach (['IET_MASONRY_DEMO_ALLOW', 'IET_MASONRY_DEMO_PASSWORD'] as $key) {
                putenv($key);
                unset($_ENV[$key]);
            }
        }
    }
}
