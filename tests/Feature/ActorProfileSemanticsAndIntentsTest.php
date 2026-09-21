<?php

namespace Tests\Feature;

use App\Actions\Profile\AddProfileConceptAssertion;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\SetActorProfileIntentStatus;
use App\Actions\Profile\UpdateActorProfile;
use App\Actions\Profile\UpdateActorProfileIntent;
use App\Actions\Profile\ResolveActorProfileConcept;
use App\Actions\Concepts\AssertConcept;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use App\Models\ConceptVocabulary;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use App\ProfileVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActorProfileSemanticsAndIntentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_semantics_reuse_one_personal_concept_across_different_predicates(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $add = app(AddProfileConceptAssertion::class);

        $skill = $add->execute(
            $actor->user,
            $profile,
            'Chess',
            ConceptAssertionPredicate::HasSkill,
        );
        $interest = $add->execute(
            $actor->user,
            $profile,
            'Chess',
            ConceptAssertionPredicate::InterestedIn,
            ConceptAssertionVisibility::Private,
        );

        $this->assertSame($skill->concept_id, $interest->concept_id);
        $this->assertSame(1, Concept::query()->count());
        $this->assertSame(1, ConceptVocabulary::query()->where('slug', 'profile-concepts')->count());
        $this->assertDatabaseHas('concept_assertions', [
            'subject_type' => ConceptAssertionSubject::Actor->value,
            'subject_id' => $actor->id,
            'concept_id' => $skill->concept_id,
            'predicate' => ConceptAssertionPredicate::HasSkill->value,
            'visibility' => ConceptAssertionVisibility::Inherited->value,
        ]);
        $this->assertDatabaseHas('concept_assertions', [
            'subject_type' => ConceptAssertionSubject::Actor->value,
            'subject_id' => $actor->id,
            'concept_id' => $interest->concept_id,
            'predicate' => ConceptAssertionPredicate::InterestedIn->value,
            'visibility' => ConceptAssertionVisibility::Private->value,
        ]);
    }

    public function test_weekly_round_trip_transport_need_is_structured_for_future_matching(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        $intent = app(CreateActorProfileIntent::class)->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Transportation',
            [
                'title' => 'Weekend ride',
                'description' => 'Travel from A to B each Saturday and return the next day.',
                'schedule_kind' => ProfileIntentScheduleKind::Weekly->value,
                'starts_on' => '2026-09-26',
                'timezone' => 'America/Toronto',
                'recurrence_interval' => 1,
                'recurrence_weekdays' => [6],
                'time_window_start' => '08:00',
                'time_window_end' => '10:00',
                'origin_text' => 'Location A',
                'destination_text' => 'Location B',
                'round_trip' => true,
                'return_after_days' => 1,
                'visibility' => ProfileItemVisibility::Inherited->value,
            ],
        );

        $this->assertSame(ProfileIntentKind::Need, $intent->kind);
        $this->assertSame(ProfileIntentScheduleKind::Weekly, $intent->schedule_kind);
        $this->assertSame([6], $intent->recurrence_weekdays);
        $this->assertSame('Location A', $intent->origin_text);
        $this->assertSame('Location B', $intent->destination_text);
        $this->assertTrue($intent->round_trip);
        $this->assertSame(1, $intent->return_after_days);

        $this->assertDatabaseHas('concept_assertions', [
            'subject_type' => ConceptAssertionSubject::Actor->value,
            'subject_id' => $actor->id,
            'concept_id' => $intent->concept_id,
            'predicate' => ConceptAssertionPredicate::Needs->value,
        ]);
    }

    public function test_periodic_quantity_need_and_recurring_service_offer_can_coexist(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $create = app(CreateActorProfileIntent::class);

        $rice = $create->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Rice',
            [
                'title' => 'Weekly rice',
                'quantity' => 5,
                'unit' => 'kg',
                'schedule_kind' => ProfileIntentScheduleKind::Weekly->value,
                'timezone' => 'America/Toronto',
                'recurrence_weekdays' => [5],
                'recurrence_interval' => 1,
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Private->value,
            ],
        );

        $service = $create->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Offer,
            'Programming Tutoring',
            [
                'title' => 'Evening Laravel tutoring',
                'schedule_kind' => ProfileIntentScheduleKind::Weekly->value,
                'timezone' => 'America/Toronto',
                'recurrence_weekdays' => [2, 4],
                'time_window_start' => '18:00',
                'time_window_end' => '21:00',
                'recurrence_interval' => 1,
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Inherited->value,
            ],
        );

        $this->assertSame('5.0000', $rice->quantity);
        $this->assertSame('kg', $rice->unit);
        $this->assertSame(ProfileIntentKind::Offer, $service->kind);
        $this->assertSame([2, 4], $service->recurrence_weekdays);

        $this->assertDatabaseHas('concept_assertions', [
            'subject_id' => $actor->id,
            'concept_id' => $rice->concept_id,
            'predicate' => ConceptAssertionPredicate::Needs->value,
            'visibility' => ConceptAssertionVisibility::Private->value,
        ]);
        $this->assertDatabaseHas('concept_assertions', [
            'subject_id' => $actor->id,
            'concept_id' => $service->concept_id,
            'predicate' => ConceptAssertionPredicate::Offers->value,
            'visibility' => ConceptAssertionVisibility::Inherited->value,
        ]);
    }

    public function test_pausing_and_closing_intents_keep_history_and_sync_semantic_summary(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $intent = app(CreateActorProfileIntent::class)->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Fresh vegetables',
            [
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'America/Toronto',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Inherited->value,
            ],
        );

        app(SetActorProfileIntentStatus::class)->execute(
            $actor->user,
            $intent,
            ProfileIntentStatus::Paused,
        );

        $this->assertDatabaseMissing('concept_assertions', [
            'subject_id' => $actor->id,
            'concept_id' => $intent->concept_id,
            'predicate' => ConceptAssertionPredicate::Needs->value,
        ]);

        $intent = app(SetActorProfileIntentStatus::class)->execute(
            $actor->user,
            $intent,
            ProfileIntentStatus::Active,
        );
        $this->assertDatabaseHas('concept_assertions', [
            'subject_id' => $actor->id,
            'concept_id' => $intent->concept_id,
            'predicate' => ConceptAssertionPredicate::Needs->value,
        ]);

        $intent = app(SetActorProfileIntentStatus::class)->execute(
            $actor->user,
            $intent,
            ProfileIntentStatus::Closed,
        );

        $this->assertSame(ProfileIntentStatus::Closed, $intent->status);
        $this->assertNotNull($intent->closed_at);
        $this->assertModelExists($intent);

        $this->expectException(HttpException::class);
        app(SetActorProfileIntentStatus::class)->execute(
            $actor->user,
            $intent,
            ProfileIntentStatus::Active,
        );
    }

    public function test_profile_intent_summary_never_overwrites_or_deletes_a_manual_actor_assertion(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $concept = app(ResolveActorProfileConcept::class)->execute(
            $actor->user,
            $profile,
            'Transportation',
        );

        $manual = app(AssertConcept::class)->execute(
            $actor->user,
            $actor,
            $concept,
            ConceptAssertionPredicate::Needs,
            visibility: ConceptAssertionVisibility::Private,
            metadata: ['purpose' => 'manual_profile_statement'],
        );

        $intent = app(CreateActorProfileIntent::class)->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Transportation',
            [
                'schedule_kind' => ProfileIntentScheduleKind::Weekly->value,
                'timezone' => 'America/Toronto',
                'recurrence_weekdays' => [6],
                'recurrence_interval' => 1,
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Public->value,
            ],
        );

        $manual->refresh();
        $this->assertSame(ConceptAssertionVisibility::Private, $manual->visibility);
        $this->assertSame('manual_profile_statement', $manual->metadata['purpose'] ?? null);

        app(SetActorProfileIntentStatus::class)->execute(
            $actor->user,
            $intent,
            ProfileIntentStatus::Paused,
        );

        $this->assertModelExists(ConceptAssertion::query()->findOrFail($manual->id));
        $this->assertSame(
            ConceptAssertionVisibility::Private,
            ConceptAssertion::query()->findOrFail($manual->id)->visibility,
        );
    }

    public function test_schedule_validation_rejects_incomplete_weekly_and_round_trip_declarations(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        try {
            app(CreateActorProfileIntent::class)->execute(
                $actor->user,
                $profile,
                ProfileIntentKind::Need,
                'Transportation',
                [
                    'schedule_kind' => ProfileIntentScheduleKind::Weekly->value,
                    'timezone' => 'America/Toronto',
                    'recurrence_weekdays' => [],
                    'round_trip' => true,
                    'origin_text' => 'A',
                    'destination_text' => 'B',
                    'visibility' => ProfileItemVisibility::Inherited->value,
                ],
            );
            $this->fail('Incomplete recurring route was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recurrence_weekdays', $exception->errors());
            $this->assertArrayHasKey('return_after_days', $exception->errors());
        }

        $this->assertSame(0, ActorProfileIntent::query()->count());
    }

    public function test_public_profile_honors_item_level_privacy_for_semantics_and_intents(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $profile = app(UpdateActorProfile::class)->execute($owner->user, $profile, [
            'display_name' => 'Visible Profile',
            'headline' => null,
            'bio' => null,
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Public->value,
        ]);

        $add = app(AddProfileConceptAssertion::class);
        $add->execute(
            $owner->user,
            $profile,
            'Laravel',
            ConceptAssertionPredicate::HasSkill,
            ConceptAssertionVisibility::Inherited,
        );
        $add->execute(
            $owner->user,
            $profile,
            'Private Topic',
            ConceptAssertionPredicate::InterestedIn,
            ConceptAssertionVisibility::Private,
        );

        $create = app(CreateActorProfileIntent::class);
        $create->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Offer,
            'Tutoring',
            [
                'title' => 'Public tutoring',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'America/Toronto',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Public->value,
            ],
        );
        $create->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Need,
            'Private Need',
            [
                'title' => 'Secret need',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'America/Toronto',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Private->value,
            ],
        );
        $create->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Need,
            'Authenticated Need',
            [
                'title' => 'Members-only need',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'America/Toronto',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Authenticated->value,
            ],
        );

        $this->get(route('profiles.show', $profile))
            ->assertOk()
            ->assertSee('Laravel')
            ->assertSee('Public tutoring')
            ->assertDontSee('Private Topic')
            ->assertDontSee('Secret need')
            ->assertDontSee('Members-only need');

        $viewer = Actor::factory()->create();
        $this->actingAs($viewer->user)
            ->get(route('profiles.show', $profile))
            ->assertOk()
            ->assertSee('Members-only need')
            ->assertDontSee('Secret need');
    }

    public function test_other_actor_cannot_edit_profile_intent(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $intent = app(CreateActorProfileIntent::class)->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Offer,
            'Electrical Work',
            [
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'America/Toronto',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Inherited->value,
            ],
        );

        $intruder = Actor::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(UpdateActorProfileIntent::class)->execute(
            $intruder->user,
            $intent,
            [
                'title' => 'Hijacked',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'America/Toronto',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Inherited->value,
            ],
        );
    }
}
