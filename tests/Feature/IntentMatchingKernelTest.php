<?php

namespace Tests\Feature;

use App\ConceptStatus;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use App\Support\IntentMatchFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentMatchingKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_construction_need_finds_compatible_service_offer_with_explainable_constraints(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();
        $private = Actor::factory()->create();
        $concept = Concept::factory()->create();

        $need = $this->intent($alice, $concept, ProfileIntentKind::Need, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Three Riverside construction days needed',
            'quantity' => 3,
            'unit' => 'day',
            'location_text' => 'Riverside',
            'schedule_kind' => ProfileIntentScheduleKind::Weekly,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'recurrence_weekdays' => [1, 2, 3],
            'time_window_start' => '08:00',
            'time_window_end' => '17:00',
            'cash_min' => '1000000',
            'cash_max' => '2000000',
            'currency_code' => 'IRR',
            'cash_basis' => 'day',
            'exchange_preference' => ProfileIntentExchangePreference::CashOnly,
        ]);

        $offer = $this->intent($bob, $concept, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Bob construction service',
            'quantity' => 5,
            'unit' => 'day',
            'location_text' => 'Riverside district',
            'schedule_kind' => ProfileIntentScheduleKind::Weekly,
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-11-10',
            'recurrence_weekdays' => [2, 3, 4],
            'time_window_start' => '07:00',
            'time_window_end' => '16:00',
            'cash_min' => '1500000',
            'cash_max' => '1800000',
            'currency_code' => 'IRR',
            'cash_basis' => 'day',
            'exchange_preference' => ProfileIntentExchangePreference::CashOnly,
        ]);

        $this->intent($carol, $concept, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Wrong-location service',
            'location_text' => 'North Harbor',
        ]);

        $this->intent($private, $concept, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Private service',
            'visibility' => ProfileItemVisibility::Private,
        ]);

        $results = app(IntentMatchFinder::class)->find($alice->user, $need);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->intent->is($offer));
        $this->assertContains('concept', $results->first()->reasons);
        $this->assertContains('quantity', $results->first()->reasons);
        $this->assertContains('location-overlap', $results->first()->reasons);
        $this->assertContains('date-overlap', $results->first()->reasons);
        $this->assertContains('time-overlap', $results->first()->reasons);
        $this->assertContains('weekly-overlap', $results->first()->reasons);
        $this->assertContains('cash-overlap', $results->first()->reasons);
        $this->assertDatabaseCount('relationships', 0);
        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('financial_obligations', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_capital_need_finds_capital_offer_and_rejects_wrong_value_constraints(): void
    {
        $alice = Actor::factory()->create();
        $carol = Actor::factory()->create();
        $diego = Actor::factory()->create();
        $concept = Concept::factory()->create();

        $need = $this->intent($alice, $concept, ProfileIntentKind::Need, [
            'subject_kind' => ProfileIntentSubjectKind::Capital,
            'arrangement_kind' => ProfileIntentArrangementKind::Financing,
            'title' => 'Construction capital needed',
            'cash_min' => '3000000',
            'cash_max' => '5000000',
            'currency_code' => 'EUR',
            'cash_basis' => 'total',
        ]);

        $offer = $this->intent($carol, $concept, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Capital,
            'arrangement_kind' => ProfileIntentArrangementKind::Financing,
            'title' => 'Carol capital offer',
            'cash_min' => '4000000',
            'cash_max' => '6000000',
            'currency_code' => 'EUR',
            'cash_basis' => 'total',
        ]);

        $this->intent($diego, $concept, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Capital,
            'arrangement_kind' => ProfileIntentArrangementKind::Financing,
            'title' => 'USD capital offer',
            'cash_min' => '3000000',
            'cash_max' => '5000000',
            'currency_code' => 'USD',
            'cash_basis' => 'total',
        ]);

        $results = app(IntentMatchFinder::class)->find($alice->user, $need);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->intent->is($offer));
        $this->assertContains('cash-overlap', $results->first()->reasons);
    }

    public function test_matching_uses_canonical_concept_identity_but_does_not_assume_hierarchy_substitutability(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $canonical = Concept::factory()->create();
        $merged = Concept::factory()->create();
        $merged->applyLifecycle([
            'status' => ConceptStatus::Merged->value,
            'merged_into_concept_id' => $canonical->id,
        ]);

        $other = Concept::factory()->create();

        $need = $this->intent($alice, $merged, ProfileIntentKind::Need, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Canonicalized need',
        ]);

        $canonicalOffer = $this->intent($bob, $canonical, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Canonical offer',
        ]);

        $this->intent($carol, $other, ProfileIntentKind::Offer, [
            'subject_kind' => ProfileIntentSubjectKind::Service,
            'arrangement_kind' => ProfileIntentArrangementKind::Service,
            'title' => 'Different concept offer',
        ]);

        $results = app(IntentMatchFinder::class)->find($alice->user, $need);

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->intent->is($canonicalOffer));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function intent(
        Actor $actor,
        Concept $concept,
        ProfileIntentKind $kind,
        array $overrides = [],
    ): ActorProfileIntent {
        $profile = ActorProfile::factory()->create(['actor_id' => $actor->id]);

        return ActorProfileIntent::factory()->create(array_merge([
            'actor_profile_id' => $profile->id,
            'concept_id' => $concept->id,
            'created_by_actor_id' => $actor->id,
            'kind' => $kind,
            'status' => ProfileIntentStatus::Active,
            'visibility' => ProfileItemVisibility::Authenticated,
            'subject_kind' => ProfileIntentSubjectKind::Other,
            'arrangement_kind' => ProfileIntentArrangementKind::Other,
            'exchange_preference' => ProfileIntentExchangePreference::DiscussLater,
            'schedule_kind' => ProfileIntentScheduleKind::Ongoing,
            'starts_on' => null,
        ], $overrides));
    }
}
