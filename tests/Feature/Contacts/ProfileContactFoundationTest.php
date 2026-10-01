<?php

namespace Tests\Feature\Contacts;

use App\Models\ActorAddress;
use App\Models\ContactPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ProfileContactFoundationTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_guest_cannot_open_contact_center(): void
    {
        $this->get(route('profile.contacts.index'))
            ->assertRedirect();
    }

    public function test_user_can_add_multiple_contact_points_and_only_one_primary_per_kind(): void
    {
        $user = $this->userWithActor();
        $this->publishSurfaces($user, ['profile']);

        $this->actingAs($user)->post(route('profile.contacts.contact.store'), [
            'kind' => 'mobile',
            'label' => 'شخصی',
            'value' => '۰۹۱۲ ۱۲۳ ۴۵۶۷',
            'visibility' => 'private',
            'is_primary' => '1',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('profile.contacts.contact.store'), [
            'kind' => 'mobile',
            'label' => 'کاری',
            'value' => '0912-987-6543',
            'visibility' => 'contacts',
            'is_primary' => '1',
        ])->assertRedirect();

        $points = ContactPoint::query()
            ->where('contactable_type', $user->actor->getMorphClass())
            ->where('contactable_id', $user->actor->getKey())
            ->where('kind', 'mobile')
            ->get();

        $this->assertCount(2, $points);
        $this->assertSame(1, $points->where('is_primary', true)->count());
        $this->assertTrue(
            $points->contains(fn (ContactPoint $point) => $point->normalized_value === '09121234567')
        );
    }

    public function test_user_can_store_work_and_residence_addresses(): void
    {
        $user = $this->userWithActor();
        $this->publishSurfaces($user, ['profile']);

        foreach ([
            ['type' => 'residence', 'label' => 'خانه', 'city' => 'تهران'],
            ['type' => 'work', 'label' => 'کارخانه', 'city' => 'کرج'],
        ] as $payload) {
            $this->actingAs($user)->post(route('profile.contacts.address.store'), [
                ...$payload,
                'country_code' => 'IR',
                'visibility' => 'private',
            ])->assertRedirect();
        }

        $this->assertSame(
            2,
            ActorAddress::query()
                ->where('addressable_type', $user->actor->getMorphClass())
                ->where('addressable_id', $user->actor->getKey())
                ->count()
        );
    }

    public function test_contact_center_shows_authentication_email_without_copying_it(): void
    {
        $user = $this->userWithActor([
            'email' => 'identity@example.test',
        ]);
        $this->publishSurfaces($user, ['profile']);

        $this->actingAs($user)
            ->get(route('profile.contacts.index'))
            ->assertOk()
            ->assertSee('identity@example.test');

        $this->assertDatabaseCount('contact_points', 0);
    }

    private function userWithActor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->actor()->create();

        return $user->refresh();
    }
}
