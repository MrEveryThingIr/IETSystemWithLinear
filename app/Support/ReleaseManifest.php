<?php

namespace App\Support;

/**
 * Reads the non-secret release-manifest.json written by the Production
 * Package workflow into the package root.
 */
final class ReleaseManifest
{
    /** @return array<string, mixed>|null */
    public static function read(?string $path = null): ?array
    {
        $path ??= base_path('release-manifest.json');
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<string, string> */
    public static function aboutRows(?string $path = null): array
    {
        $manifest = self::read($path);
        if ($manifest === null) {
            return ['Manifest' => 'not present (development checkout)'];
        }

        $source = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];
        $build = is_array($manifest['build'] ?? null) ? $manifest['build'] : [];

        return [
            'Package' => (string) ($manifest['package'] ?? 'unknown'),
            'Application version' => (string) ($manifest['application_version'] ?? 'unknown'),
            'Git commit' => (string) ($source['git_commit'] ?? 'unknown'),
            'Git ref' => (string) ($source['git_ref'] ?? 'unknown'),
            'Built at' => (string) ($build['built_at'] ?? 'unknown'),
            'Workflow run' => (string) ($build['workflow_run_id'] ?? 'unknown'),
            'PHP target' => (string) ($build['php_target'] ?? 'unknown'),
        ];
    }
}
