<?php

namespace Tests\Feature;

use App\Actions\Categories\EnsureSharedCategories;
use App\Actions\Groups\CreateGroup;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Livewire\Groups\Show as GroupShow;
use App\Models\Actor;
use App\Models\Capability;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\ProfileIntentKind;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessService;
use App\Support\Parties\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BusinessGroupCoherenceTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private Actor $owner;

    private Actor $member;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($admin->user)->create(['role' => PlatformRole::Superadmin]);
        app(EnsureSharedCategories::class)->ensureCurrent();
        $this->owner = Actor::factory()->create();
        $this->member = Actor::factory()->create();
        foreach ([$this->owner, $this->member] as $actor) {
            $actor->user->forceFill(['locale' => 'en'])->save();
            $this->publishSurfaces($actor->user, ['business', 'market', 'groups', 'profile']);
        }
    }

    private function concept(string $slug): int
    {
        return (int) DB::table('concepts')->where('slug', $slug)->value('id');
    }

    public function test_business_page_shows_team_skills_and_its_offers_with_publishing_only_for_managers(): void
    {
        $business = app(BusinessService::class)->create($this->owner, ['name' => 'Spark Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $membership = app(BusinessService::class)->addMember($business, $this->member);
        Capability::query()->create([
            'holder_actor_id' => $this->member->id, 'business_membership_id' => $membership->id, 'concept_id' => $this->concept('housing.electrical'),
            'visibility' => 'members', 'status' => 'active', 'created_by_actor_id' => $this->member->id,
        ]);
        $profile = app(EnsureActorProfile::class)->execute($this->owner->user);
        app(CreateActorProfileIntent::class)->execute($this->owner->user, $profile, ProfileIntentKind::Offer, '', [
            'title' => 'Panel upgrades', 'schedule_kind' => 'ongoing', 'timezone' => 'UTC', 'round_trip' => false, 'visibility' => 'members',
        ], ['publisher' => Party::of($business), 'concept_id' => $this->concept('housing.electrical')]);

        $this->actingAs($this->owner->user)->get(route('businesses.show', $business))
            ->assertOk()
            ->assertSee('Panel upgrades')
            ->assertSee('Electrical installation &amp; repair', false)
            ->assertSee(route('intents.compose', ['as' => 'business:'.$business->id]), false)
            ->assertDontSee('class="hero"', false);

        $this->actingAs($this->member->user)->get(route('businesses.show', $business))
            ->assertOk()
            ->assertSee('Panel upgrades')
            ->assertDontSee(route('intents.compose', ['as' => 'business:'.$business->id]), false);
    }

    public function test_business_categories_link_to_shared_categories_by_exact_name(): void
    {
        $business = app(BusinessService::class)->create($this->owner, ['name' => 'Spark Co', 'kind' => 'services', 'visibility' => 'private', 'status' => 'active']);
        $linked = app(BusinessCatalogService::class)->ensureCategory($business, 'Electrical installation & repair', 'electrical');
        $unlinked = app(BusinessCatalogService::class)->ensureCategory($business, 'Our special stuff', 'special');

        $this->assertSame($this->concept('housing.electrical'), (int) $linked->concept_id);
        $this->assertNull($unlinked->concept_id);

        $this->actingAs($this->owner->user)->get(route('businesses.catalog.index', $business))
            ->assertOk()
            ->assertSee('↔', false);
    }

    public function test_contact_center_and_business_pages_follow_the_user_language(): void
    {
        $this->actingAs($this->owner->user)->get(route('profile.contacts.index'))
            ->assertOk()
            ->assertSee('Contacts &amp; addresses', false)
            ->assertDontSee('راه‌های ارتباطی');

        $this->owner->user->forceFill(['locale' => 'fa'])->save();
        $this->actingAs($this->owner->user->fresh())->get(route('profile.contacts.index'))
            ->assertOk()
            ->assertSee('راه‌های ارتباطی و آدرس‌ها');

        $this->owner->user->forceFill(['locale' => 'ar'])->save();
        $this->actingAs($this->owner->user->fresh())->get(route('businesses.create'))
            ->assertOk()
            ->assertDontSee('class="hero"', false);
    }

    public function test_group_page_lists_group_needs_and_lets_managers_publish_for_the_group(): void
    {
        $group = app(CreateGroup::class)->execute($this->owner, 'Street association', null);
        $profile = app(EnsureActorProfile::class)->execute($this->owner->user);
        app(CreateActorProfileIntent::class)->execute($this->owner->user, $profile, ProfileIntentKind::Need, '', [
            'title' => 'Fix the street lights', 'schedule_kind' => 'ongoing', 'timezone' => 'UTC', 'round_trip' => false, 'visibility' => 'members',
        ], ['publisher' => Party::of($group), 'concept_id' => $this->concept('housing.electrical')]);

        Livewire::actingAs($this->owner->user)->test(GroupShow::class, ['group' => $group])
            ->assertSee('Fix the street lights')
            ->assertSee(route('intents.compose', ['as' => 'group:'.$group->id]), false);
    }
}
