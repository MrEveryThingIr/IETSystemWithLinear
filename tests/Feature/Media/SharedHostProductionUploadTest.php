<?php

namespace Tests\Feature\Media;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\UploadActorProfileImage;
use App\Models\Actor;
use App\Models\BusinessListingMedia;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessService;
use App\Support\MediaScannerPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/**
 * Shared-host production baseline: APP_ENV=production, QUEUE_CONNECTION=sync,
 * MEDIA_SCANNER_ENABLED=false, MEDIA_SCANNER_REQUIRED=false and no proc_open.
 * Uploads must complete through the real Asset pipeline without HTTP 500.
 */
class SharedHostProductionUploadTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Process::fake();
        config([
            'queue.default' => 'sync',
            'media.scanner.enabled' => false,
            'media.scanner.required' => false,
        ]);
        // The host has proc_open disabled; any probe reports it unavailable.
        $this->app->instance(MediaScannerPolicy::class, new MediaScannerPolicy(fn (): bool => false));
        // Migrations already ran in parent::setUp(); switch to production so the
        // local/testing upload shortcut is not used and the job really runs.
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        Process::assertNothingRan();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_production_profile_image_upload_completes_without_proc_open(): void
    {
        $actor = Actor::factory()->create();
        $profile = app(EnsureActorProfile::class)->execute($actor->user);

        $image = app(UploadActorProfileImage::class)->execute(
            $actor->user,
            $profile,
            UploadedFile::fake()->image('portrait.jpg', 512, 512)->size(300),
        );

        $asset = $image->asset->refresh();
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertTrue($asset->isReadyForPublication());
        Storage::disk('local')->assertExists($asset->storage_key);

        $this->actingAs($actor->user)
            ->get(route('profiles.images.show', [$profile, $image]))
            ->assertOk();
    }

    public function test_production_planner_context_asset_upload_completes_without_proc_open(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 06:00:00 UTC');

        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $plan = app(CreatePlan::class)->execute($context, $actor->user, 'Shared host evidence', timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan, $actor->user, PlanScheduleFrequency::Once, '2026-09-25', '08:00', 60,
        );
        $occurrence = $rule->occurrences()->sole();

        // The same actions the Planner page runs, executed with APP_ENV=production
        // so the upload goes through ProcessAssetMedia on the sync queue.
        $asset = app(CreateContextAsset::class)->execute(
            $context,
            $actor->user,
            UploadedFile::fake()->create('proof.txt', 1, 'text/plain'),
            'owned',
        );
        app(AttachPlanOccurrenceEvidence::class)->execute($occurrence, $actor->user, [$asset->id], []);

        $asset->refresh();
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertNotNull($asset->readiness_verified_at);
        $this->assertTrue($asset->isReadyForPublication());
        $this->assertDatabaseHas('plan_occurrence_assets', [
            'plan_occurrence_id' => $occurrence->id,
            'asset_id' => $asset->id,
        ]);
    }

    public function test_production_business_listing_media_upload_completes_without_proc_open(): void
    {
        $owner = User::factory()->create();
        $owner->actor()->create();
        $owner->refresh();
        $this->publishSurfaces($owner, ['business']);

        $business = app(BusinessService::class)->create($owner->actor, [
            'name' => 'Shared Host Real Estate',
            'kind' => 'real_estate',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        $listing = app(BusinessCatalogService::class)->createListing(
            $business,
            $owner->actor,
            'property',
            ['title' => 'Apartment', 'short_description' => 'Uploaded on a shared host'],
            visibility: 'public',
        );

        // Production enforces CSRF; send a real session token like a browser would.
        $this->actingAs($owner)
            ->withSession(['_token' => 'shared-host-token'])
            ->post(route('businesses.catalog.listings.media.store', [$business, $listing]), [
                '_token' => 'shared-host-token',
                'media' => UploadedFile::fake()->image('front.jpg', 900, 600),
                'rights_status' => 'owned',
                'caption' => 'Front view',
                'visibility' => 'public',
                'cover' => '1',
            ])
            ->assertRedirect();

        $asset = BusinessListingMedia::query()->with('asset')->sole()->asset;
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertTrue($asset->isReadyForPublication());
    }
}
