<?php

namespace App\Support;

/**
 * Release notes shown on the "What's new" page and the version shown next
 * to the site name. Texts live in lang/<locale>/changelog.php under
 * versions.<version>; this list fixes their order and dates.
 */
final class Changelog
{
    /** Newest first. Add a line (and its texts) with every release. */
    public const RELEASES = [
        '1.7.1' => '2026-10-24',
        '1.7.0' => '2026-10-23',
        '1.6.0' => '2026-10-22',
        '1.5.0' => '2026-10-21',
        '1.4.0' => '2026-10-19',
        '1.3.0' => '2026-10-17',
        '1.2.0' => '2026-10-15',
    ];

    /**
     * The running version: the release package's manifest, then APP_VERSION,
     * then the newest entry here (a development checkout).
     */
    public static function currentVersion(): string
    {
        $manifest = ReleaseManifest::read();
        $version = is_array($manifest) ? (string) ($manifest['application_version'] ?? '') : '';
        if ($version === '') {
            $version = (string) config('app.version', '');
        }
        $version = ltrim(trim($version), 'vV');

        return preg_match('/^\d+\.\d+\.\d+/', $version) === 1 ? $version : (string) array_key_first(self::RELEASES);
    }

    /** @return list<array{version: string, date: string, title: string, items: list<string>, current: bool}> */
    public static function entries(): array
    {
        $current = self::currentVersion();
        $entries = [];

        foreach (self::RELEASES as $version => $date) {
            $key = 'changelog.versions.'.str_replace('.', '_', $version);
            $items = trans($key.'.items');
            $entries[] = [
                'version' => $version,
                'date' => $date,
                'title' => (string) __($key.'.title'),
                'items' => is_array($items) ? array_values(array_map('strval', $items)) : [],
                'current' => $version === $current,
            ];
        }

        return $entries;
    }
}
