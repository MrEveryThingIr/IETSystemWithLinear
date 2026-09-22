<?php

namespace Tests\Feature;

use App\Actions\Profile\AddProfileConceptAssertion;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\CreateProfileDisclosureGrant;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\RevokeProfileDisclosureGrant;
use App\Actions\Profile\SetActorProfileIntentStatus;
use App\Actions\Profile\UpdateActorProfile;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionVisibility;
use App\Livewire\Profile\Sharing;
use App\Models\Actor;
use App\Models\ActorProfileDisclosureGrant;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use App\ProfileVisibility;
use App\Support\Profile\ProfileCompletenessService;
use App\Support\Profile\ProfileRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ActorProfileSharingAndCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_selective_sharing_composer_is_collapsed_until_requested(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        Livewire::actingAs($actor->user)
            ->test(Sharing::class, ['profile' => $profile])
            ->assertSet('composerOpen', false)
            ->call('openComposer')
            ->assertSet('composerOpen', true)
            ->call('cancelComposer')
            ->assertSet('composerOpen', false);
    }

    public function test_completeness_is_purpose_specific_and_does_not_make_profile_fields_globally_required(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);
        $service = app(ProfileCompletenessService::class);
        $requirements = [
            ProfileRequirement::field('headline', 'Headline'),
            ProfileRequirement::concept(ConceptAssertionPredicate::HasSkill, 'Skill'),
            ProfileRequirement::activeIntent(ProfileIntentKind::Need, 'Need'),
        ];

        $initial = $service->summarize($profile, $requirements);
        $this->assertSame(0, $initial['completed']);
        $this->assertSame(3, $initial['total']);

        app(UpdateActorProfile::class)->execute($actor->user, $profile, [
            'display_name' => null,
            'headline' => 'Software builder',
            'bio' => null,
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Private->value,
        ]);

        app(AddProfileConceptAssertion::class)->execute(
            $actor->user,
            $profile,
            'Laravel',
            ConceptAssertionPredicate::HasSkill,
            ConceptAssertionVisibility::Private,
        );

        app(CreateActorProfileIntent::class)->execute(
            $actor->user,
            $profile,
            ProfileIntentKind::Need,
            'Code Review',
            [
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Private->value,
            ],
        );

        $complete = $service->summarize($profile->fresh(), $requirements);
        $this->assertSame(3, $complete['completed']);
        $this->assertSame(100, $complete['percent']);
        $this->assertNull($profile->fresh()->display_name);
    }

    public function test_owner_can_selectively_share_private_profile_items_with_one_recipient(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $profile = app(UpdateActorProfile::class)->execute($owner->user, $profile, [
            'display_name' => 'Selective Owner',
            'headline' => 'Private headline',
            'bio' => 'This must stay unshared.',
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Private->value,
        ]);

        $assertion = app(AddProfileConceptAssertion::class)->execute(
            $owner->user,
            $profile,
            'Laravel',
            ConceptAssertionPredicate::HasSkill,
            ConceptAssertionVisibility::Private,
        );

        $intent = app(CreateActorProfileIntent::class)->execute(
            $owner->user,
            $profile,
            ProfileIntentKind::Offer,
            'Mentoring',
            [
                'title' => 'Private mentoring offer',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'round_trip' => false,
                'visibility' => ProfileItemVisibility::Private->value,
            ],
        );

        $recipient = Actor::factory()->create();
        $recipientProfile = app(EnsureActorProfile::class)->execute($recipient->user);
        $grant = app(CreateProfileDisclosureGrant::class)->execute(
            $owner->user,
            $profile,
            $recipientProfile,
            ['field:display_name', 'assertion:'.$assertion->uuid, 'intent:'.$intent->uuid],
            'Collaboration introduction',
            30,
        );

        $this->actingAs($recipient->user)
            ->get(route('profiles.shares.show', $grant))
            ->assertOk()
            ->assertSee('Selective Owner')
            ->assertSee('Laravel')
            ->assertSee('Private mentoring offer')
            ->assertDontSee('This must stay unshared.')
            ->assertDontSee($owner->user->email);

        app(SetActorProfileIntentStatus::class)->execute(
            $owner->user,
            $intent,
            ProfileIntentStatus::Closed,
        );

        $this->actingAs($recipient->user)
            ->get(route('profiles.shares.show', $grant))
            ->assertOk()
            ->assertSee('Laravel')
            ->assertDontSee('Private mentoring offer');
    }

    public function test_unselected_identity_fields_do_not_leak_through_shared_view_chrome(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $profile = app(UpdateActorProfile::class)->execute($owner->user, $profile, [
            'display_name' => 'Do Not Leak This Name',
            'headline' => null,
            'bio' => null,
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Private->value,
        ]);

        $assertion = app(AddProfileConceptAssertion::class)->execute(
            $owner->user,
            $profile,
            'Selective Skill',
            ConceptAssertionPredicate::HasSkill,
            ConceptAssertionVisibility::Private,
        );

        $recipient = Actor::factory()->create();
        $recipientProfile = app(EnsureActorProfile::class)->execute($recipient->user);
        $grant = app(CreateProfileDisclosureGrant::class)->execute(
            $owner->user,
            $profile,
            $recipientProfile,
            ['assertion:'.$assertion->uuid],
        );

        $this->actingAs($recipient->user)
            ->get(route('profiles.shares.show', $grant))
            ->assertOk()
            ->assertSee('Selective Skill')
            ->assertDontSee('Do Not Leak This Name')
            ->assertDontSee($owner->user->username)
            ->assertDontSee($owner->user->email);
    }

    public function test_foreign_or_non_shareable_items_cannot_be_smuggled_into_a_grant(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        app(UpdateActorProfile::class)->execute($owner->user, $profile, [
            'display_name' => 'Owner',
            'headline' => null,
            'bio' => null,
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Private->value,
        ]);

        $other = Actor::factory()->create();
        $otherProfile = app(EnsureActorProfile::class)->execute($other->user);
        $foreign = app(AddProfileConceptAssertion::class)->execute(
            $other->user,
            $otherProfile,
            'Secret Foreign Concept',
            ConceptAssertionPredicate::InterestedIn,
            ConceptAssertionVisibility::Private,
        );

        $recipient = Actor::factory()->create();
        $recipientProfile = app(EnsureActorProfile::class)->execute($recipient->user);

        try {
            app(CreateProfileDisclosureGrant::class)->execute(
                $owner->user,
                $profile,
                $recipientProfile,
                ['field:email', 'assertion:'.$foreign->uuid],
            );
            $this->fail('Invalid disclosure items were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selectedItems', $exception->errors());
        }

        $this->assertSame(0, ActorProfileDisclosureGrant::query()->count());
    }

    public function test_only_selected_recipient_can_open_share_and_revocation_is_immediate(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $profile = app(UpdateActorProfile::class)->execute($owner->user, $profile, [
            'display_name' => 'Owner',
            'headline' => null,
            'bio' => null,
            'location_text' => null,
            'website_url' => null,
            'visibility' => ProfileVisibility::Private->value,
        ]);

        $recipient = Actor::factory()->create();
        $recipientProfile = app(EnsureActorProfile::class)->execute($recipient->user);
        $grant = app(CreateProfileDisclosureGrant::class)->execute(
            $owner->user,
            $profile,
            $recipientProfile,
            ['field:display_name'],
        );

        $outsider = Actor::factory()->create();
        $this->actingAs($outsider->user)->get(route('profiles.shares.show', $grant))->assertForbidden();
        $this->actingAs($recipient->user)->get(route('profiles.shares.show', $grant))->assertOk();

        $recipient->forceFill(['status' => 'archived'])->save();
        $this->actingAs($recipient->user)->get(route('profiles.shares.show', $grant))->assertForbidden();
        $recipient->forceFill(['status' => 'active'])->save();

        app(RevokeProfileDisclosureGrant::class)->execute($owner->user, $grant);

        $this->actingAs($recipient->user)->get(route('profiles.shares.show', $grant))->assertForbidden();
        $this->assertSame(ProfileVisibility::Private, $profile->fresh()->visibility);
        $this->assertNotNull($grant->fresh()->revoked_at);
    }

    public function test_expired_grant_is_not_authorized(): void
    {
        $owner = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $recipient = Actor::factory()->create();

        $grant = ActorProfileDisclosureGrant::factory()->expired()->create([
            'actor_profile_id' => $profile->id,
            'grantee_actor_id' => $recipient->id,
            'created_by_actor_id' => $owner->id,
        ]);

        $this->actingAs($recipient->user)
            ->get(route('profiles.shares.show', $grant))
            ->assertForbidden();
    }
}
