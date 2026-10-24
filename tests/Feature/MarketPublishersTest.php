<?php

namespace Tests\Feature;

use App\Actions\Categories\EnsureSharedCategories;
use App\Actions\Groups\CreateGroup;
use App\Actions\Market\ManageIntentMedia;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Livewire\Intents\Create;
use App\Livewire\Intents\Directory;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Business;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\Services\Business\BusinessService;
use App\Support\IntentMatchFinder;
use App\Support\Parties\Party;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketPublishersTest extends TestCase
{
    use RefreshDatabase;

    private Actor $owner;

    private Actor $member;

    private Actor $stranger;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($admin->user)->create(['role' => PlatformRole::Superadmin]);
        app(EnsureSharedCategories::class)->ensureCurrent();

        $this->owner = Actor::factory()->create();
        $this->member = Actor::factory()->create();
        $this->stranger = Actor::factory()->create();
        $this->business = app(BusinessService::class)->create($this->owner, ['name' => 'Spark Co', 'kind' => 'services', 'visibility' => 'public', 'status' => 'active']);
        app(BusinessService::class)->addMember($this->business, $this->member);
    }

    private function slug(string $slug): int
    {
        return (int) DB::table('concepts')->where('slug', $slug)->value('id');
    }

    /** @param array<string, mixed> $input */
    private function publish(Actor $actor, ProfileIntentKind $kind, ?Party $publisher, string $category, array $input = []): ActorProfileIntent
    {
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        return app(CreateActorProfileIntent::class)->execute($actor->user, $profile, $kind, '', [
            'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
            'timezone' => 'UTC',
            'round_trip' => false,
            'visibility' => 'public',
            ...$input,
        ], array_filter(['publisher' => $publisher, 'concept_id' => $this->slug($category)]));
    }

    private function visibleIds(?Actor $actor): array
    {
        return ActorProfileIntent::query()->visibleTo($actor?->user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function test_business_publisher_is_separate_from_the_acting_person_and_members_visibility_is_enforced(): void
    {
        $offer = $this->publish($this->owner, ProfileIntentKind::Offer, Party::of($this->business), 'housing.electrical', ['visibility' => 'members']);

        $this->assertSame('business:'.$this->business->id, $offer->publisher()->key());
        $this->assertNull($offer->publisher_actor_id);
        $this->assertSame((int) $this->owner->id, (int) $offer->profile->actor_id);

        $this->assertContains($offer->id, $this->visibleIds($this->owner));
        $this->assertContains($offer->id, $this->visibleIds($this->member));
        $this->assertNotContains($offer->id, $this->visibleIds($this->stranger));
        $this->assertNotContains($offer->id, $this->visibleIds(null));
        $this->assertTrue($this->member->user->can('view', $offer));
        $this->assertFalse($this->member->user->can('update', $offer));
        $this->assertTrue($this->owner->user->can('update', $offer));
        $this->assertFalse($this->stranger->user->can('view', $offer));

        // The publisher can never be changed afterwards.
        $offer->publisher_business_id = null;
        $offer->publisher_actor_id = $this->owner->id;
        $this->expectException(\LogicException::class);
        $offer->save();
    }

    public function test_a_plain_member_cannot_publish_for_the_business_and_a_person_cannot_use_members_visibility(): void
    {
        try {
            $this->publish($this->member, ProfileIntentKind::Offer, Party::of($this->business), 'housing.electrical');
            $this->fail('A member who does not manage the business published for it.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectException(ValidationException::class);
        $this->publish($this->member, ProfileIntentKind::Need, null, 'housing.electrical', ['visibility' => 'members']);
    }

    public function test_group_managers_publish_needs_for_their_group(): void
    {
        $group = app(CreateGroup::class)->execute($this->owner, 'Street association', null);
        $need = $this->publish($this->owner, ProfileIntentKind::Need, Party::of($group), 'housing.electrical', ['visibility' => 'members']);

        $this->assertSame('group:'.$group->id, $need->publisher()->key());
        $this->assertNotContains($need->id, $this->visibleIds($this->stranger));

        $this->expectException(HttpException::class);
        $this->publish($this->stranger, ProfileIntentKind::Need, Party::of($group), 'housing.electrical');
    }

    public function test_paused_and_suspended_publishers_hide_their_items_from_others(): void
    {
        $offer = $this->publish($this->owner, ProfileIntentKind::Offer, Party::of($this->business), 'housing.electrical');
        $this->assertContains($offer->id, $this->visibleIds($this->stranger));

        DB::table('businesses')->where('id', $this->business->id)->update(['status' => 'suspended']);
        $this->assertNotContains($offer->id, $this->visibleIds($this->stranger));
        $this->assertContains($offer->id, $this->visibleIds($this->owner));
    }

    public function test_matches_explain_category_family_and_skip_the_same_publisher(): void
    {
        $need = $this->publish($this->stranger, ProfileIntentKind::Need, null, 'housing');
        $offer = $this->publish($this->owner, ProfileIntentKind::Offer, Party::of($this->business), 'housing.electrical');
        $ownOffer = $this->publish($this->stranger, ProfileIntentKind::Offer, null, 'housing.electrical');
        $unrelated = $this->publish($this->member, ProfileIntentKind::Offer, null, 'housing.plumbing');

        $matches = app(IntentMatchFinder::class)->find($this->stranger->user, $need);
        $ids = $matches->map(fn ($match): int => (int) $match->intent->id)->all();

        $this->assertContains($offer->id, $ids);
        $this->assertNotContains($ownOffer->id, $ids);
        $this->assertContains($unrelated->id, $ids); // plumbing is also inside "housing"
        $this->assertContains('category-within', $matches->firstWhere(fn ($m) => $m->intent->id === $offer->id)->reasons);

        // Siblings directly under an area are too broad to count.
        $electricalNeed = $this->publish($this->stranger, ProfileIntentKind::Need, null, 'housing.electrical');
        $siblingIds = app(IntentMatchFinder::class)->find($this->stranger->user, $electricalNeed)->map(fn ($m): int => (int) $m->intent->id)->all();
        $this->assertContains($offer->id, $siblingIds);
        $this->assertNotContains($unrelated->id, $siblingIds);
    }

    public function test_photos_are_served_only_to_people_who_can_see_the_item(): void
    {
        Storage::fake('local');
        $offer = $this->publish($this->owner, ProfileIntentKind::Offer, Party::of($this->business), 'housing.electrical', ['visibility' => 'members']);
        $media = app(ManageIntentMedia::class)->add($this->owner->user, $offer, UploadedFile::fake()->image('panel.jpg', 640, 480), 'New panel');
        $other = $this->publish($this->stranger, ProfileIntentKind::Need, null, 'housing.electrical');

        $url = route('intents.media.show', [$offer, $media]);
        $response = $this->actingAs($this->member->user)->get($url);
        $response->assertOk();
        $this->actingAs($this->stranger->user)->get($url)->assertForbidden();
        $this->actingAs($this->stranger->user)->get(route('intents.media.show', [$other, $media]))->assertNotFound();
        $this->get($url)->assertForbidden();

        try {
            app(ManageIntentMedia::class)->add($this->member->user, $offer, UploadedFile::fake()->image('x.jpg'));
            $this->fail('A non-manager added a photo.');
        } catch (AuthorizationException) {
            $this->assertSame(1, $offer->media()->count());
        }

        $document = app(ManageIntentMedia::class)->add($this->owner->user, $offer, UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'));
        $this->assertSame('application/pdf', $document->asset->mime_type);
    }

    public function test_wizard_publishes_for_a_managed_business_and_refuses_other_publishers(): void
    {
        $this->actingAs($this->owner->user);
        Livewire::test(Create::class)
            ->set('ownerKey', 'business:'.$this->business->id)
            ->set('journeyPreset', 'offer_service')
            ->call('chooseJourney', 'offer_service')
            ->set('conceptId', $this->slug('housing.electrical'))
            ->set('cashMin', '20')
            ->set('currencyCode', 'USD')
            ->set('cashBasis', 'square_metre')
            ->set('negotiable', false)
            ->set('visibility', 'members')
            ->call('save')
            ->assertHasNoErrors();

        $offer = ActorProfileIntent::query()->latest('id')->firstOrFail();
        $this->assertSame((int) $this->business->id, (int) $offer->publisher_business_id);
        $this->assertSame('square_metre', $offer->cash_basis);
        $this->assertFalse($offer->negotiable);

        $this->actingAs($this->member->user);
        Livewire::test(Create::class)->set('ownerKey', 'business:'.$this->business->id)->assertForbidden();
    }

    public function test_directory_filters_by_area_and_by_my_organisations(): void
    {
        $offer = $this->publish($this->owner, ProfileIntentKind::Offer, Party::of($this->business), 'housing.electrical', ['title' => 'Panel upgrades']);
        $this->publish($this->stranger, ProfileIntentKind::Need, null, 'sports.fitness', ['title' => 'Running coach']);

        Livewire::actingAs($this->owner->user)->test(Directory::class)
            ->assertSee('Panel upgrades')->assertSee('Running coach')
            ->set('area', $this->slug('housing'))
            ->assertSee('Panel upgrades')->assertDontSee('Running coach')
            ->set('area', 0)->set('scope', 'mine')
            ->assertSee('Panel upgrades')->assertDontSee('Running coach')
            ->assertSee('Spark Co');

        $this->assertNotNull($offer->id);
    }
}
