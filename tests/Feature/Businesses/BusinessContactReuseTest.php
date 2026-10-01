<?php

namespace Tests\Feature\Businesses;

use App\Models\User;
use App\Services\Business\BusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class BusinessContactReuseTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_business_reuses_m1_contacts_and_addresses(): void
    {
        $owner = User::factory()->create();
        $ownerActor = $owner->actor()->create();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($ownerActor, [
            'name' => 'Sample Store',
            'kind' => 'retail',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('businesses.contacts.store', $business), [
                'kind' => 'phone',
                'label' => 'دفتر',
                'value' => '۰۲۱ ۱۲۳۴۵۶۷۸',
                'visibility' => 'public',
                'is_primary' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('businesses.addresses.store', $business), [
                'type' => 'work',
                'label' => 'شعبه مرکزی',
                'country_code' => 'IR',
                'city' => 'تهران',
                'visibility' => 'public',
                'is_primary' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(1, $business->contactPoints()->count());
        $this->assertSame(1, $business->addresses()->count());
    }
}
