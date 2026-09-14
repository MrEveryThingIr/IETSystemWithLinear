<?php

namespace Tests\Feature\Groups;

use App\Models\Asset;
use App\Support\AssetMediaPipeline;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetMediaPipelineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_clean_scan_marks_asset_ready_and_verifies_identity(): void
    {
        Storage::fake('local');
        Process::fake();

        $contents = 'clean media contents';
        $asset = Asset::factory()->create([
            'storage_key' => 'assets/testing/clean.pdf',
            'sha256' => hash('sha256', $contents),
            'scan_status' => 'quarantined',
            'processing_status' => 'pending',
            'processing_completed_at' => null,
            'readiness_verified_at' => null,
        ]);
        Storage::disk('local')->put($asset->storage_key, $contents);

        $asset = app(AssetMediaPipeline::class)->process($asset);

        $this->assertSame('clean', $asset->scan_status);
        $this->assertSame('ready', $asset->processing_status);
        $this->assertNotNull($asset->scan_attempted_at);
        $this->assertNotNull($asset->scan_completed_at);
        $this->assertNotNull($asset->readiness_verified_at);
        $this->assertTrue($asset->isReadyForPublication());
        Process::assertRan(fn ($process): bool => $process->timeout === 120);
    }

    public function test_rejected_scan_blocks_processing_and_publication_readiness(): void
    {
        Storage::fake('local');
        Process::fake([
            '*' => Process::result(exitCode: 1),
        ]);

        $contents = 'malicious fixture';
        $asset = Asset::factory()->create([
            'storage_key' => 'assets/testing/rejected.pdf',
            'sha256' => hash('sha256', $contents),
            'scan_status' => 'quarantined',
            'processing_status' => 'pending',
            'processing_completed_at' => null,
            'readiness_verified_at' => null,
        ]);
        Storage::disk('local')->put($asset->storage_key, $contents);

        $asset = app(AssetMediaPipeline::class)->process($asset);

        $this->assertSame('rejected', $asset->scan_status);
        $this->assertSame('blocked', $asset->processing_status);
        $this->assertNull($asset->readiness_verified_at);
        $this->assertFalse($asset->isReadyForPublication());
    }
}
