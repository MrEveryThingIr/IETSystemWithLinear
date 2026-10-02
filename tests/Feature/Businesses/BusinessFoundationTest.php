<?php

namespace Tests\Feature\Businesses;

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Profession;
use App\Models\User;
use App\Services\Business\BusinessService;
use Database\Seeders\ProfessionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BusinessFoundationTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProfessionSeeder::class);
    }

    public function test_user_can_create_business_and_becomes_its_owner(): void
    {
        $user = $this->userWithActor();
        $this->publishSurfaces($user, ['business']);

        $this->actingAs($user)
            ->post(route('businesses.store'), [
                'name' => 'دفتر املاک نمونه',
                'kind' => 'real_estate',
                'short_intro' => 'خرید و فروش و اجاره ملک',
                'visibility' => 'private',
                'status' => 'active',
            ])
            ->assertRedirect();

        $business = Business::query()->sole();

        $this->assertSame($user->actor->getKey(), $business->owner_actor_id);

        $membership = BusinessMembership::query()->sole();
        $this->assertSame('owner', $membership->role);
        $this->assertSame($user->actor->getKey(), $membership->actor_id);
    }

    public function test_business_shell_hides_unpublished_sibling_capabilities(): void
    {
        $owner = $this->userWithActor();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Focused Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->withoutVite()
            ->actingAs($owner)
            ->get(route('businesses.show', $business))
            ->assertOk()
            ->assertDontSee(route('planner.index'), false)
            ->assertDontSee(route('deals.index'), false)
            ->assertDontSee(route('money.index'), false);
    }

    public function test_stranger_cannot_view_private_business(): void
    {
        $owner = $this->userWithActor();
        $stranger = $this->userWithActor();
        $this->publishSurfaces($stranger, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Private Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->actingAs($stranger)
            ->get(route('businesses.show', $business))
            ->assertForbidden();
    }

    public function test_owner_can_add_member_and_assign_business_profession(): void
    {
        $owner = $this->userWithActor();
        $worker = $this->userWithActor(['email' => 'worker@example.test']);
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Construction Co',
            'kind' => 'construction',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('businesses.members.store', $business), [
                'user' => 'worker@example.test',
                'role' => 'member',
                'job_title' => 'برق‌کار',
            ])
            ->assertRedirect();

        $membership = BusinessMembership::query()
            ->where('business_id', $business->getKey())
            ->where('actor_id', $worker->actor->getKey())
            ->sole();

        $electrician = Profession::query()->where('code', 'electrician')->sole();

        $this->actingAs($owner)
            ->post(route('businesses.members.professions.store', [$business, $membership]), [
                'profession_id' => $electrician->getKey(),
                'is_primary' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue($membership->fresh()->professions->contains($electrician));
    }

    public function test_ordinary_member_cannot_manage_business(): void
    {
        $owner = $this->userWithActor();
        $member = $this->userWithActor();

        $service = app(BusinessService::class);

        $business = $service->create($owner->actor, [
            'name' => 'Business',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $service->addMember($business, $member->actor);
        $this->publishSurfaces($member, ['business']);

        $this->actingAs($member)
            ->put(route('businesses.update', $business), [
                'name' => 'Hacked',
                'kind' => 'services',
                'visibility' => 'private',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertSame('Business', $business->fresh()->name);
    }

    public function test_owner_can_transfer_ownership_only_to_active_member(): void
    {
        $owner = $this->userWithActor();
        $nextOwner = $this->userWithActor();

        $service = app(BusinessService::class);

        $business = $service->create($owner->actor, [
            'name' => 'Transferable',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $service->addMember($business, $nextOwner->actor, 'manager');
        $this->publishSurfaces($owner, ['business']);

        $this->actingAs($owner)
            ->post(route('businesses.transfer-ownership', $business), [
                'actor_id' => $nextOwner->actor->getKey(),
            ])
            ->assertRedirect();

        $business->refresh();

        $this->assertSame($nextOwner->actor->getKey(), $business->owner_actor_id);
        $this->assertSame(
            'owner',
            $business->memberships()->where('actor_id', $nextOwner->actor->getKey())->sole()->role
        );
        $this->assertSame(
            'manager',
            $business->memberships()->where('actor_id', $owner->actor->getKey())->sole()->role
        );
    }

    private function userWithActor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->actor()->create();

        return $user->refresh();
    }
}
