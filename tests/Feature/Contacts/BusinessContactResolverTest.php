<?php

namespace Tests\Feature\Contacts;

use App\Models\BusinessContact;
use App\Models\PublicIntakePortal;
use App\Services\Contacts\BusinessContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessContactResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_phone_is_reused_for_same_business_owner(): void
    {
        if (! class_exists(PublicIntakePortal::class)) {
            $this->markTestSkipped('Real Estate intake is not installed.');
        }

        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر نمونه',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $resolver = app(BusinessContactResolver::class);

        $first = $resolver->resolve($portal, 'علی', '۰۹۱۲۱۲۳۴۵۶۷', 'test');
        $second = $resolver->resolve($portal, 'علی', '0912 123 4567', 'test');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseCount('business_contacts', 1);
        $this->assertDatabaseCount('contact_points', 1);
    }

    public function test_same_phone_can_belong_to_contacts_of_different_business_owners(): void
    {
        if (! class_exists(PublicIntakePortal::class)) {
            $this->markTestSkipped('Real Estate intake is not installed.');
        }

        $a = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر الف',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $b = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => 'دفتر ب',
            'locale' => 'fa',
            'is_active' => true,
        ]);

        $resolver = app(BusinessContactResolver::class);

        $resolver->resolve($a, 'مشتری', '09121234567', 'test');
        $resolver->resolve($b, 'مشتری', '09121234567', 'test');

        $this->assertDatabaseCount('business_contacts', 2);
    }
}
