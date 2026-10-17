<?php

namespace App\Support;

use LogicException;

/**
 * Central upload size limits.
 *
 * The effective limit is the lower of the configured application limit
 * (config/media.php) and the PHP host limits, so an upload that the host
 * cannot accept fails with an ordinary validation error instead of a
 * server error.
 */
final class MediaLimits
{
    /** Effective limit in kilobytes for an upload kind from config('media.limits'). */
    public static function kilobytes(string $kind): int
    {
        $configured = (int) config('media.limits.'.$kind, 0);
        if ($configured <= 0) {
            throw new LogicException("Unknown or invalid media upload limit [{$kind}].");
        }

        // PHP upload limits apply to web requests only; the CLI (artisan, queue
        // workers, tests) has its own unrelated php.ini values.
        return self::effective($configured, app()->runningInConsole() ? null : self::hostLimitKilobytes());
    }

    /** Lower of the configured limit and the host limit (null host limit = unlimited). */
    public static function effective(int $configuredKilobytes, ?int $hostKilobytes): int
    {
        return $hostKilobytes === null
            ? $configuredKilobytes
            : max(1, min($configuredKilobytes, $hostKilobytes));
    }

    /** @var array<string, string> Content Studio limit kind per media kind. */
    public const CONTENT_KINDS = [
        'image' => 'content_image',
        'audio' => 'content_audio',
        'video' => 'content_video',
        'pdf' => 'content_document',
        'file' => 'content_document',
    ];

    /** Content Studio limit kind for a MIME type. */
    public static function contentKindForMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::CONTENT_KINDS['image'],
            str_starts_with($mime, 'audio/') => self::CONTENT_KINDS['audio'],
            str_starts_with($mime, 'video/') => self::CONTENT_KINDS['video'],
            default => self::CONTENT_KINDS['file'],
        };
    }

    /**
     * Effective Content Studio limits in bytes, keyed by media kind
     * (image, audio, video, document), for display and browser preflight.
     *
     * @return array{image: int, audio: int, video: int, document: int}
     */
    public static function contentBytes(): array
    {
        return [
            'image' => self::kilobytes('content_image') * 1024,
            'audio' => self::kilobytes('content_audio') * 1024,
            'video' => self::kilobytes('content_video') * 1024,
            'document' => self::kilobytes('content_document') * 1024,
        ];
    }

    /** Laravel validation rule for the effective limit, e.g. "max:12288". */
    public static function rule(string $kind): string
    {
        return 'max:'.self::kilobytes($kind);
    }

    /** Lowest of upload_max_filesize and post_max_size in kilobytes, or null when unlimited/unknown. */
    public static function hostLimitKilobytes(): ?int
    {
        $limits = array_filter([
            self::iniBytes(ini_get('upload_max_filesize')),
            self::iniBytes(ini_get('post_max_size')),
        ], fn (?int $bytes): bool => $bytes !== null && $bytes > 0);

        if ($limits === []) {
            return null;
        }

        return intdiv(min($limits), 1024);
    }

    /** Parse a php.ini size value such as "64M" into bytes. Null when empty or unparsable. */
    public static function iniBytes(string|false $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || preg_match('/^(\d+)\s*([kmg]?)b?$/i', $value, $matches) !== 1) {
            return null;
        }

        $bytes = (int) $matches[1];

        return match (strtolower($matches[2])) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }
}
