<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Relationships\CreateRelationship;
use App\Livewire\Home\Today;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\FinancialObligation;
use App\Models\MonetaryUnit;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\PlanScheduleRule;
use App\Models\Relationship;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class HomeTodayExperienceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use PublishesFeatureSurfaces;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('release.profile', 'full');
    }

    public function test_today_composes_owned_schedule_and_active_intent_without_creating_new_domain_truth(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $plan = Plan::factory()->create([
            'context_id' => $context->id,
            'created_by_actor_id' => $actor->id,
            'title' => 'Inspect Riverside dimensions',
            'timezone' => 'UTC',
        ]);
        $rule = PlanScheduleRule::factory()->create(['plan_id' => $plan->id]);
        PlanOccurrence::factory()->create([
            'plan_id' => $plan->id,
            'schedule_rule_id' => $rule->id,
            'local_date' => now('UTC')->toDateString(),
            'scheduled_start_at' => now('UTC')->startOfDay()->addHours(9),
            'scheduled_end_at' => now('UTC')->startOfDay()->addHours(10),
            'timezone' => 'UTC',
        ]);

        $concept = Concept::factory()->create();
        $profile = ActorProfile::factory()->create(['actor_id' => $actor->id]);
        ActorProfileIntent::factory()->create([
            'actor_profile_id' => $profile->id,
            'concept_id' => $concept->id,
            'created_by_actor_id' => $actor->id,
            'kind' => ProfileIntentKind::Need,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Private,
            'title' => 'Need construction inspection support',
        ]);

        $beforeRelationships = Relationship::query()->count();
        $beforeObligations = FinancialObligation::query()->count();

        Livewire::actingAs($actor->user)
            ->test(Today::class)
            ->assertSee('Inspect Riverside dimensions')
            ->assertSee('Need construction inspection support')
            ->assertSee('What should I do today?');

        $this->assertSame($beforeRelationships, Relationship::query()->count());
        $this->assertSame($beforeObligations, FinancialObligation::query()->count());
    }

    public function test_today_guides_a_new_user_through_minimal_setup_without_persistent_verification_noise(): void
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['profile', 'market', 'planner', 'business', 'groups', 'content']);

        Livewire::actingAs($actor->user)
            ->test(Today::class)
            ->assertSee('Finish your setup')
            ->assertSee('How should people know you?')
            ->assertSee('How can people reach you?')
            ->assertSee('What do you want to do first?')
            ->assertSee('Recommended next step')
            ->assertSee('Set my identity')
            ->assertDontSee('Email verified')
            ->assertSee('Find or offer something')
            ->assertSee('Plan something')
            ->assertSee('Run a business')
            ->assertSee('Work with a group')
            ->assertSee('Create content');
    }

    public function test_today_only_shows_verification_success_for_the_redirect_request_that_flashes_it(): void
    {
        $actor = Actor::factory()->create();

        $this->actingAs($actor->user)
            ->withSession(['email_verified_now' => true])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Email verified');
    }

    public function test_today_hides_setup_checklist_once_minimal_setup_and_first_goal_exist(): void
    {
        $actor = Actor::factory()->create();
        ActorProfile::factory()->create([
            'actor_id' => $actor->id,
            'display_name' => 'Ready User',
        ]);
        $actor->contactPoints()->create([
            'kind' => 'mobile',
            'label' => 'Primary',
            'value' => '09121234567',
            'normalized_value' => '+989121234567',
            'is_primary' => true,
            'visibility' => 'private',
        ]);

        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        Plan::factory()->create([
            'context_id' => $context->id,
            'created_by_actor_id' => $actor->id,
            'title' => 'First meaningful plan',
            'timezone' => 'UTC',
        ]);

        $this->publishSurfaces($actor->user, ['profile', 'planner']);

        Livewire::actingAs($actor->user)
            ->test(Today::class)
            ->assertDontSee('Finish your setup')
            ->assertSee('Review your plans');
    }

    public function test_today_distinguishes_waiting_on_me_from_waiting_on_others(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $concept = Concept::factory()->create();

        app(CreateRelationship::class)->execute(
            $bob->user,
            $concept,
            'provider',
            [['actor' => $alice, 'role' => 'client']],
            title: 'Bob asks Alice to confirm',
        );

        app(CreateRelationship::class)->execute(
            $alice->user,
            $concept,
            'client',
            [['actor' => $bob, 'role' => 'provider']],
            title: 'Alice waits for Bob',
        );

        Livewire::actingAs($alice->user)
            ->test(Today::class)
            ->assertSee('Recommended next step')
            ->assertSee('Bob asks Alice to confirm')
            ->assertSee('A participant invitation is waiting for your response.')
            ->assertSee('Alice waits for Bob')
            ->assertSee('Another participant still needs to accept the proposed Relationship.');
    }

    public function test_obligation_summary_keeps_currencies_and_directions_separate(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $eur = MonetaryUnit::factory()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'exponent' => 2,
        ]);
        $usd = MonetaryUnit::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exponent' => 2,
        ]);

        FinancialObligation::factory()->create([
            'creditor_actor_id' => $alice->id,
            'debtor_actor_id' => $bob->id,
            'monetary_unit_id' => $eur->id,
            'amount_minor' => 10000,
            'description' => 'Riverside work receivable',
            'recognized_by_actor_id' => $alice->id,
            'recognized_at' => CarbonImmutable::now(),
        ]);

        FinancialObligation::factory()->create([
            'creditor_actor_id' => $bob->id,
            'debtor_actor_id' => $alice->id,
            'monetary_unit_id' => $usd->id,
            'amount_minor' => 5000,
            'description' => 'Tool reimbursement payable',
            'recognized_by_actor_id' => $alice->id,
            'recognized_at' => CarbonImmutable::now(),
        ]);

        Livewire::actingAs($alice->user)
            ->test(Today::class)
            ->assertSee('EUR')
            ->assertSee('USD')
            ->assertSee('100.00')
            ->assertSee('50.00')
            ->assertSee('Receivable / owed to me')
            ->assertSee('Payable / I owe');
    }

    public function test_getting_started_route_keeps_compatibility_but_lands_on_today_checklist(): void
    {
        $actor = Actor::factory()->create();

        $this->actingAs($actor->user)
            ->get(route('getting-started'))
            ->assertRedirect(route('dashboard') . '#getting-started');
    }

    public function test_dashboard_route_is_the_today_operating_view(): void
    {
        $actor = Actor::factory()->create();

        $this->actingAs($actor->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Today')
            ->assertSee('Waiting on me')
            ->assertSee('Recent activity');
    }
}
