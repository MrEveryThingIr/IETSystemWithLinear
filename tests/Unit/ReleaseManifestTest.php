<?php

namespace Tests\Unit;

use App\Support\ReleaseManifest;
use PHPUnit\Framework\TestCase;

class ReleaseManifestTest extends TestCase
{
    public function test_about_rows_identify_the_github_build(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iet-manifest-');
        file_put_contents($path, json_encode([
            'schema' => 1,
            'package' => 'iet-v1.0.1',
            'application_version' => '1.0.1',
            'source' => ['git_commit' => str_repeat('a', 40), 'git_ref' => 'refs/heads/release/formal-v1-production'],
            'build' => ['built_at' => '2026-10-14T08:00:00Z', 'workflow_run_id' => '123', 'php_target' => '8.4'],
        ]));

        try {
            $rows = ReleaseManifest::aboutRows($path);
        } finally {
            unlink($path);
        }

        $this->assertSame('iet-v1.0.1', $rows['Package']);
        $this->assertSame(str_repeat('a', 40), $rows['Git commit']);
        $this->assertSame('8.4', $rows['PHP target']);
    }

    public function test_missing_manifest_is_reported_without_failing(): void
    {
        $rows = ReleaseManifest::aboutRows(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.json');

        $this->assertSame(['Manifest' => 'not present (development checkout)'], $rows);
    }
}
