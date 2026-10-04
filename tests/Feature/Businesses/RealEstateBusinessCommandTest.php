<?php

namespace Tests\Feature\Businesses;

use App\Models\PublicIntakePortal;
use App\Models\User;
use App\Services\Business\BusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RealEstateBusinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cli_command_reuses_the_business_owned_real_estate_intake_channel(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'مشاور املاک مهوری',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $before = PublicIntakePortal::query()->where('business_id', $business->id)->sole();

        $exit = Artisan::call('real-estate:intake-portal', [
            'business' => $business->uuid,
            '--welcome' => 'خوش آمدید',
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseCount('public_intake_portals', 1);

        $after = PublicIntakePortal::query()->sole();

        $this->assertSame($before->id, $after->id);
        $this->assertSame($business->id, $after->business_id);
        $this->assertSame('مشاور املاک مهوری', $after->title);
        $this->assertSame('خوش آمدید', $after->welcome_body);
    }

    public function test_cli_command_refuses_to_create_real_estate_outside_a_real_estate_business(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'دفتر خدمات',
            'kind' => 'services',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $exit = Artisan::call('real-estate:intake-portal', [
            'business' => $business->uuid,
        ]);

        $this->assertSame(1, $exit);
        $this->assertDatabaseCount('public_intake_portals', 0);
    }
}
