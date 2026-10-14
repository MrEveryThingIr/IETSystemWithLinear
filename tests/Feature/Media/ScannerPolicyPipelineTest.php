<?php

namespace Tests\Feature\Media;

use App\Jobs\ProcessAssetMedia;
use App\Models\Asset;
use App\Support\AssetMediaPipeline;
use App\Support\MediaScannerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScannerPolicyPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // RefreshDatabase has already migrated in parent::setUp(). From here on,
        // policy decisions must not depend on APP_ENV=local/testing exemptions.
        $this->app['env'] = 'production';
    }

    public function test_enabled_scanner_with_successful_scan_marks_asset_clean_and_ready(): void
    {
        $this->configureScanner(enabled: true, required: true, processAvailable: true);
        Process::fake();
        $asset = $this->quarantinedAsset('clean media contents');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        $this->assertSame('clean', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertNotNull($asset->scan_completed_at);
        $this->assertNotNull($asset->readiness_verified_at);
        $this->assertTrue($asset->isReadyForPublication());
        Process::assertRan(fn ($process): bool => $process->command[0] === 'clamscan');
    }

    public function test_enabled_scanner_rejecting_malware_blocks_the_asset(): void
    {
        $this->configureScanner(enabled: true, required: false, processAvailable: true);
        Process::fake(['*' => Process::result(exitCode: 1)]);
        $asset = $this->quarantinedAsset('malicious fixture');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        $this->assertSame('rejected', $asset->scan_status);
        $this->assertSame('blocked', $asset->processing_status);
        $this->assertNull($asset->readiness_verified_at);
        $this->assertFalse($asset->isReadyForPublication());
    }

    public function test_required_scanner_that_is_unavailable_fails_safely_without_starting_a_process(): void
    {
        $this->configureScanner(enabled: true, required: true, processAvailable: false);
        Process::fake();
        $asset = $this->quarantinedAsset('needs a scan');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        Process::assertNothingRan();
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertNotSame('clean', $asset->scan_status);
        $this->assertSame('failed', $asset->processing_status);
        $this->assertNull($asset->readiness_verified_at);
        $this->assertStringContainsString('proc_open', (string) $asset->scan_error);
        $this->assertFalse($asset->isReadyForPublication());
    }

    public function test_disabled_non_required_scanner_in_production_makes_verified_asset_ready_but_not_clean(): void
    {
        $this->configureScanner(enabled: false, required: false, processAvailable: false);
        Process::fake();
        $asset = $this->quarantinedAsset('shared host upload');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        Process::assertNothingRan();
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertNull($asset->scan_completed_at);
        $this->assertNotNull($asset->readiness_verified_at);
        $this->assertTrue($asset->isReadyForPublication());
        $this->assertTrue(app()->environment('production'));
    }

    public function test_disabled_scanner_never_probes_or_needs_proc_open(): void
    {
        config(['media.scanner.enabled' => false, 'media.scanner.required' => false]);
        $this->app->instance(MediaScannerPolicy::class, new MediaScannerPolicy(function (): bool {
            $this->fail('Process execution must not be probed when the scanner is disabled.');
        }));
        Process::fake();
        $asset = $this->quarantinedAsset('no process needed');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        Process::assertNothingRan();
        $this->assertSame('ready', $asset->processing_status);
    }

    public function test_enabled_scanner_without_proc_open_is_treated_as_unavailable_when_not_required(): void
    {
        $this->configureScanner(enabled: true, required: false, processAvailable: false);
        Process::fake();
        $asset = $this->quarantinedAsset('scanner configured but host cannot run it');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        Process::assertNothingRan();
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
    }

    public function test_integrity_is_still_verified_when_the_scanner_is_disabled(): void
    {
        $this->configureScanner(enabled: false, required: false, processAvailable: false);
        Process::fake();
        $asset = $this->quarantinedAsset('original contents');
        Storage::disk('local')->put($asset->storage_key, 'tampered contents');

        $asset = app(AssetMediaPipeline::class)->process($asset);

        Process::assertNothingRan();
        $this->assertSame('unavailable', $asset->scan_status);
        $this->assertSame('failed', $asset->processing_status);
        $this->assertNull($asset->readiness_verified_at);
        $this->assertFalse($asset->isReadyForPublication());
    }

    public function test_missing_stored_file_fails_safely_when_the_scanner_is_disabled(): void
    {
        $this->configureScanner(enabled: false, required: false, processAvailable: false);
        $asset = $this->quarantinedAsset('soon missing');
        Storage::disk('local')->delete($asset->storage_key);

        $asset = app(AssetMediaPipeline::class)->process($asset);

        $this->assertSame('failed', $asset->processing_status);
        $this->assertFalse($asset->isReadyForPublication());
    }

    public function test_unscanned_asset_stops_being_publishable_when_policy_becomes_required(): void
    {
        $this->configureScanner(enabled: false, required: false, processAvailable: false);
        $asset = app(AssetMediaPipeline::class)->process($this->quarantinedAsset('policy change'));
        $this->assertTrue($asset->isReadyForPublication());

        config(['media.scanner.required' => true]);

        $this->assertFalse($asset->refresh()->isReadyForPublication());
    }

    public function test_scanner_crash_on_a_synchronous_queue_is_recorded_without_throwing(): void
    {
        $this->configureScanner(enabled: true, required: true, processAvailable: true);
        Process::fake(['*' => Process::result(errorOutput: 'scanner database missing', exitCode: 2)]);
        $asset = $this->quarantinedAsset('scanner crash');

        ProcessAssetMedia::dispatchSync($asset->id);

        $asset->refresh();
        $this->assertSame('failed', $asset->scan_status);
        $this->assertSame('failed', $asset->processing_status);
        $this->assertFalse($asset->isReadyForPublication());
    }

    private function configureScanner(bool $enabled, bool $required, bool $processAvailable): void
    {
        config([
            'media.scanner.enabled' => $enabled,
            'media.scanner.required' => $required,
            'media.scanner.binary' => 'clamscan',
        ]);

        $this->app->instance(MediaScannerPolicy::class, new MediaScannerPolicy(fn (): bool => $processAvailable));
    }

    private function quarantinedAsset(string $contents): Asset
    {
        $asset = Asset::factory()->create([
            'storage_key' => 'assets/testing/'.md5($contents).'.pdf',
            'sha256' => hash('sha256', $contents),
            'scan_status' => 'quarantined',
            'processing_status' => 'pending',
            'processing_completed_at' => null,
            'readiness_verified_at' => null,
        ]);
        Storage::disk('local')->put($asset->storage_key, $contents);

        return $asset;
    }
}
