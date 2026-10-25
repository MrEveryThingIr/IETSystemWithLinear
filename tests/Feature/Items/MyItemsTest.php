<?php

namespace Tests\Feature\Items;

use App\Actions\Categories\EnsureSharedCategories;
use App\Actions\Items\ConsolidateLegacyProfileItems;
use App\Actions\Items\SaveInterest;
use App\Actions\Profile\AddProfileConceptAssertion;
use App\Actions\Profile\EnsureActorProfile;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionVisibility;
use App\Livewire\Items\Interests;
use App\Livewire\Profile\Capabilities;
use App\Models\Actor;
use App\Models\Capability;
use App\Models\ConceptAssertion;
use App\Models\Interest;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\Services\Business\BusinessService;
use App\Support\Profile\ProfileDisclosureCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class MyItemsTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($admin->user)->create(['role' => PlatformRole::Superadmin]);
        $this->assertTrue(app(EnsureSharedCategories::class)->ensureCurrent());
        $this->withoutVite();
    }

    private function concept(string $slug): int
    {
        return (int) DB::table('concepts')->where('slug', $slug)->value('id');
    }

    private function person(array $surfaces = ['profile', 'market']): Actor
    {
        $actor = Actor::factory()->create();
        app(EnsureActorProfile::class)->execute($actor->user);
        $this->publishSurfaces($actor->user, $surfaces);

        return $actor;
    }

    public function test_old_profile_tags_are_copied_once_into_abilities_and_interests_and_never_removed(): void
    {
        $owner = $this->person();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $add = app(AddProfileConceptAssertion::class);
        $skill = $add->execute($owner->user, $profile, 'Welding', ConceptAssertionPredicate::HasSkill, ConceptAssertionVisibility::Inherited, 75);
        $interested = $add->execute($owner->user, $profile, 'Chess', ConceptAssertionPredicate::InterestedIn, ConceptAssertionVisibility::Inherited);
        $learn = $add->execute($owner->user, $profile, 'Chess', ConceptAssertionPredicate::WantsToLearn, ConceptAssertionVisibility::Private);

        // A share that listed the old skill tag.
        $recipient = $this->person();
        $grantId = DB::table('actor_profile_disclosure_grants')->insertGetId([
            'uuid' => (string) str()->uuid(),
            'actor_profile_id' => $profile->id,
            'grantee_actor_id' => $recipient->id,
            'created_by_actor_id' => $owner->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('actor_profile_disclosure_items')->insert(['grant_id' => $grantId, 'kind' => 'concept_assertion', 'item_key' => 'assertion:'.$skill->uuid, 'created_at' => now(), 'updated_at' => now()]);

        $report = app(ConsolidateLegacyProfileItems::class)->execute();
        $this->assertSame(['abilities' => 1, 'interests' => 1, 'merged' => 1, 'shares' => 1, 'skipped' => 0], $report);

        $ability = Capability::query()->where('holder_actor_id', $owner->id)->sole();
        $this->assertSame((int) $skill->concept_id, (int) $ability->concept_id);
        $this->assertSame('advanced', $ability->level);
        $this->assertSame('members', $ability->visibility, 'A tag shown with the profile is never widened beyond signed-in members.');

        $interest = Interest::query()->heldBy($owner)->sole();
        $this->assertSame(['follow', 'learn'], $interest->purposes);
        $this->assertSame('private', $interest->visibility, 'When two tags merge, the more private one wins.');

        $this->assertTrue(DB::table('actor_profile_disclosure_items')->where('grant_id', $grantId)->where('item_key', 'ability:'.$ability->uuid)->exists());

        // Running again copies nothing, and the old tags are untouched.
        $this->assertSame(['abilities' => 0, 'interests' => 0, 'merged' => 0, 'shares' => 0, 'skipped' => 0], app(ConsolidateLegacyProfileItems::class)->execute());
        $this->assertSame(1, Capability::query()->count());
        $this->assertSame(1, Interest::query()->count());
        $this->assertSame(3, ConceptAssertion::query()->whereIn('id', [$skill->id, $interested->id, $learn->id])->count());
    }

    public function test_an_existing_ability_absorbs_the_matching_old_tag_instead_of_duplicating(): void
    {
        $owner = $this->person();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $tag = app(AddProfileConceptAssertion::class)->execute($owner->user, $profile, 'Tiling', ConceptAssertionPredicate::HasSkill);
        Capability::query()->create([
            'holder_actor_id' => $owner->id, 'concept_id' => $tag->concept_id, 'visibility' => 'private', 'created_by_actor_id' => $owner->id,
        ]);

        $report = app(ConsolidateLegacyProfileItems::class)->execute();

        $this->assertSame(1, $report['merged']);
        $this->assertSame(1, Capability::query()->where('holder_actor_id', $owner->id)->count());
        $this->assertSame('private', Capability::query()->where('holder_actor_id', $owner->id)->value('visibility'));
    }

    public function test_interests_are_private_by_default_and_one_per_topic(): void
    {
        $owner = $this->person();
        $chess = $this->concept('sports');
        $this->assertGreaterThan(0, $chess);

        Livewire::actingAs($owner->user)->test(Interests::class)
            ->call('add')
            ->set('conceptId', $chess)
            ->call('save')
            ->assertHasErrors('purposes')
            ->call('togglePurpose', 'learn')
            ->call('togglePurpose', 'enjoy')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertSee(__('items.interests.saved_private'));

        $interest = Interest::query()->heldBy($owner)->sole();
        $this->assertSame(['learn', 'enjoy'], $interest->purposes);
        $this->assertSame('private', $interest->visibility);

        // Choosing the same topic again opens the existing interest.
        Livewire::actingAs($owner->user)->test(Interests::class)
            ->call('add')
            ->set('conceptId', $chess)
            ->assertSet('editing', $interest->uuid)
            ->assertSee(__('items.interests.already_have'));

        $this->expectException(ValidationException::class);
        app(SaveInterest::class)->create($owner->user, ['concept_id' => $chess, 'purposes' => ['follow'], 'visibility' => 'private']);
    }

    public function test_nobody_else_can_see_change_or_remove_a_private_interest(): void
    {
        $owner = $this->person();
        $other = $this->person();
        $interest = app(SaveInterest::class)->create($owner->user, ['concept_id' => $this->concept('sports'), 'purposes' => ['follow'], 'visibility' => 'private']);
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $profile->update(['visibility' => 'public']);

        $label = DB::table('concept_labels')->where('concept_id', $interest->concept_id)->where('locale', 'en')->value('label');
        $this->actingAs($other->user)->get(route('profiles.show', $profile))->assertOk()->assertDontSee($label);
        $this->assertFalse($other->user->can('view', $interest));

        Livewire::actingAs($other->user)->test(Interests::class)->call('edit', $interest->uuid)->assertNotFound();

        $this->expectException(AuthorizationException::class);
        app(SaveInterest::class)->remove($other->user, $interest);
    }

    public function test_an_interest_put_on_the_profile_is_shown_there_with_its_purposes(): void
    {
        $owner = $this->person();
        $other = $this->person();
        $interest = app(SaveInterest::class)->create($owner->user, ['concept_id' => $this->concept('sports'), 'purposes' => ['learn'], 'visibility' => 'profile']);
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $profile->update(['visibility' => 'public']);
        $label = DB::table('concept_labels')->where('concept_id', $interest->concept_id)->where('locale', 'en')->value('label');

        $this->actingAs($other->user)->get(route('profiles.show', $profile))
            ->assertOk()
            ->assertSee($label)
            ->assertSee(__('items.interests.purposes.learn'));
    }

    public function test_my_items_shows_all_four_kinds_and_opens_the_requested_form(): void
    {
        $owner = $this->person();

        $this->actingAs($owner->user)->get(route('items.index'))
            ->assertOk()
            ->assertSee(__('items.interests.title'))
            ->assertSee(__('items.abilities.title'))
            ->assertSee(__('items.offers_needs.title'))
            ->assertSee('data-bottom-bar', false)
            ->assertSee('data-launcher', false);

        $this->actingAs($owner->user)->get(route('items.index', ['add' => 'interest']))->assertOk()->assertSee('data-interest-form', false);
        $this->actingAs($owner->user)->get(route('items.index', ['add' => 'ability']))->assertOk()->assertSee('data-capability-form', false);

        // Without the market, offers and needs are simply not listed.
        $plain = $this->person(['profile']);
        $this->actingAs($plain->user)->get(route('items.index'))->assertOk()->assertDontSee(__('items.offers_needs.title'));
    }

    public function test_abilities_default_to_only_me_and_open_in_place_with_details_on_request(): void
    {
        $owner = $this->person();

        Livewire::actingAs($owner->user)->test(Capabilities::class, ['embedded' => true])
            ->call('add')
            ->assertSet('visibility', 'private')
            ->assertSet('showMore', false)
            ->assertDontSee('data-capability-details', false)
            ->set('conceptId', $this->concept('housing.plumbing'))
            ->set('level', 'advanced')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(__('capabilities.saved_where', ['visibility' => __('capabilities.visibility.private')]));

        $this->assertSame('private', Capability::query()->where('holder_actor_id', $owner->id)->value('visibility'));
    }

    public function test_a_persons_own_ability_page_leads_to_my_items_and_business_abilities_stay_reachable(): void
    {
        $owner = $this->person(['profile', 'business']);

        Livewire::actingAs($owner->user)->test(Capabilities::class)->assertRedirect(route('items.index').'#abilities');

        $business = app(BusinessService::class)->create($owner, ['name' => 'Atlas Steel', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $this->actingAs($owner->user)->get(route('profile.professions.index', ['for' => 'business:'.$business->id]))
            ->assertOk()
            ->assertSee(__('capabilities.title'));
    }

    public function test_profile_page_points_to_my_items_instead_of_a_second_editor(): void
    {
        $owner = $this->person();

        $this->actingAs($owner->user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-items-summary', false)
            ->assertSee(route('items.index'), false);
    }

    public function test_sharing_offers_abilities_and_interests_by_their_new_keys(): void
    {
        $owner = $this->person();
        $profile = app(EnsureActorProfile::class)->execute($owner->user);
        $interest = app(SaveInterest::class)->create($owner->user, ['concept_id' => $this->concept('sports'), 'purposes' => ['enjoy'], 'visibility' => 'private']);
        $ability = Capability::query()->create(['holder_actor_id' => $owner->id, 'concept_id' => $this->concept('housing.plumbing'), 'visibility' => 'private', 'created_by_actor_id' => $owner->id]);

        $keys = app(ProfileDisclosureCatalog::class)->validKeys($profile);

        $this->assertContains('ability:'.$ability->uuid, $keys);
        $this->assertContains('interest:'.$interest->uuid, $keys);
    }

    public function test_offer_and_need_starts_follow_the_intention(): void
    {
        $owner = $this->person();

        $this->actingAs($owner->user)->get(route('intents.create.classic', ['side' => 'offer']))
            ->assertOk()
            ->assertSee(__('intents.journeys.offer_service.title'))
            ->assertDontSee(__('intents.journeys.need_service.title'));

        $this->actingAs($owner->user)->get(route('intents.create.classic', ['side' => 'need']))
            ->assertOk()
            ->assertSee(__('intents.journeys.need_service.title'))
            ->assertDontSee(__('intents.journeys.offer_service.title'));
    }

    public function test_items_page_requires_the_profile_surface(): void
    {
        $owner = $this->person([]);

        $this->actingAs($owner->user)->get(route('items.index'))->assertForbidden();
    }
}
