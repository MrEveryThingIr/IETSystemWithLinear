<?php

namespace App\Support\Media;

use RuntimeException;

/**
 * "Fast start" for MP4 / MOV files, in plain PHP (no FFmpeg).
 *
 * Phones often write the index of a video (the `moov` box) at the END of the
 * file. A browser then has to fetch the start, jump to the end for the index,
 * and only then begin playing — several extra round trips that are painful on
 * slow or far-away connections (for example through a VPN to a host in Iran).
 *
 * This moves `moov` in front of the media data and shifts the chunk offsets
 * inside it (`stco` / `co64`) by the same amount, exactly like qt-faststart.
 * The media bytes themselves are copied unchanged.
 */
final class Mp4Faststart
{
    /** Index boxes larger than this are not loaded into memory. */
    private const MAX_MOOV_BYTES = 64 * 1024 * 1024;

    /** Containers whose children may hold stco/co64. */
    private const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts', 'udta', 'mvex', 'moof', 'traf'];

    /** MIME types written in the MP4 / QuickTime container. */
    public const MIME_TYPES = ['video/mp4', 'video/quicktime', 'video/x-m4v', 'audio/mp4', 'audio/x-m4a'];

    /**
     * Prepares an uploaded MP4/MOV for streaming before it is hashed and
     * stored. Never fails an upload: a file that cannot be handled is kept
     * exactly as it was.
     */
    public static function optimizeUpload(?string $path, string $mime): bool
    {
        if ($path === null || $path === '' || ! in_array(strtolower($mime), self::MIME_TYPES, true) || ! is_writable($path)) {
            return false;
        }

        // Only rewrite upload scratch files, never a file somewhere else on disk.
        $real = (string) realpath($path);
        $roots = array_filter([sys_get_temp_dir(), storage_path(), (string) ini_get('upload_tmp_dir')], fn (string $root): bool => $root !== '');
        $allowed = array_filter(array_map(fn (string $root): string|false => realpath($root), $roots));
        if (! collect($allowed)->contains(fn (string $root): bool => str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))) {
            return false;
        }

        try {
            return self::optimize($path);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /** True when the file starts with its index already (or is not an MP4 we can handle). */
    public static function isFaststart(string $path): bool
    {
        $boxes = self::topLevelBoxes($path);
        $moov = self::find($boxes, 'moov');
        $mdat = self::find($boxes, 'mdat');

        return $moov === null || $mdat === null || $moov['offset'] < $mdat['offset'];
    }

    /**
     * Rewrites $source with the index first into $target ($target may equal
     * $source; the file is replaced atomically). Returns false when nothing
     * needed to change or the file cannot be handled safely.
     */
    public static function optimize(string $source, ?string $target = null): bool
    {
        $target ??= $source;
        $boxes = self::topLevelBoxes($source);
        $moov = self::find($boxes, 'moov');
        $mdat = self::find($boxes, 'mdat');

        if ($moov === null || $mdat === null || $moov['offset'] < $mdat['offset']) {
            return false;
        }
        // Only the standard layout is handled: ftyp first, all media data
        // before the index. Anything unusual is left exactly as it was.
        $ftyp = self::find($boxes, 'ftyp');
        if ($moov['size'] > self::MAX_MOOV_BYTES || $ftyp === null || $ftyp['offset'] !== 0) {
            return false;
        }
        foreach ($boxes as $box) {
            if ($box['type'] === 'mdat' && $box['offset'] > $moov['offset']) {
                return false;
            }
        }

        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException('The video could not be opened.');
        }

        $temporary = null;
        try {
            fseek($in, $moov['offset']);
            $moovBytes = (string) fread($in, $moov['size']);
            if (strlen($moovBytes) !== $moov['size'] || str_contains(substr($moovBytes, 0, 64), 'cmov')) {
                return false;
            }

            // Everything except ftyp moves forward by the size of moov.
            $patched = self::shiftOffsets($moovBytes, $moov['size']);
            if ($patched === null) {
                return false;
            }

            $temporary = $target.'.faststart-'.bin2hex(random_bytes(4));
            $out = fopen($temporary, 'wb');
            if ($out === false) {
                throw new RuntimeException('The video could not be rewritten.');
            }

            try {
                self::copy($in, $out, $ftyp['offset'], $ftyp['size']);
                if (fwrite($out, $patched) !== strlen($patched)) {
                    throw new RuntimeException('The video index could not be written.');
                }
                foreach ($boxes as $box) {
                    if ($box['type'] === 'moov' || $box['type'] === 'ftyp') {
                        continue;
                    }
                    self::copy($in, $out, $box['offset'], $box['size']);
                }
            } finally {
                fclose($out);
            }

            if (filesize($temporary) !== filesize($source) || self::topLevelBoxes($temporary) === [] || ! self::isFaststart($temporary)) {
                return false;
            }

            // Windows cannot replace a file while our source handle is open.
            // Keep the original in place until the complete output is validated.
            fclose($in);
            $in = null;
            if (! @rename($temporary, $target)) {
                throw new RuntimeException('The video could not be replaced.');
            }

            return true;
        } finally {
            if (is_resource($in)) {
                fclose($in);
            }
            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * @return list<array{type: string, offset: int, size: int}>
     */
    private static function topLevelBoxes(string $path): array
    {
        $length = @filesize($path);
        $handle = @fopen($path, 'rb');
        if ($length === false || $handle === false) {
            return [];
        }

        $boxes = [];
        $offset = 0;

        try {
            while ($offset + 8 <= $length && count($boxes) < 10_000) {
                fseek($handle, $offset);
                $header = (string) fread($handle, 16);
                if (strlen($header) < 8) {
                    break;
                }
                $size = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);

                if ($size === 1) {
                    if (strlen($header) < 16) {
                        break;
                    }
                    $size = self::uint64(substr($header, 8, 8));
                } elseif ($size === 0) {
                    $size = $length - $offset;
                }

                if ($size < 8 || $offset + $size > $length || preg_match('/^[\x20-\x7e]{4}$/', $type) !== 1) {
                    return []; // not a well-formed MP4: leave it alone
                }

                $boxes[] = ['type' => $type, 'offset' => $offset, 'size' => $size];
                $offset += $size;
            }
        } finally {
            fclose($handle);
        }

        return $offset === $length ? $boxes : [];
    }

    /**
     * Adds $delta to every chunk offset in stco/co64 boxes inside moov.
     * Returns null when a 32-bit offset would overflow.
     */
    private static function shiftOffsets(string $moov, int $delta): ?string
    {
        $header = unpack('N', substr($moov, 0, 4))[1] === 1 ? 16 : 8;

        return self::walk($moov, $header, strlen($moov), $delta);
    }

    private static function walk(string $data, int $start, int $end, int $delta): ?string
    {
        $position = $start;

        while ($position + 8 <= $end) {
            $size = unpack('N', substr($data, $position, 4))[1];
            $type = substr($data, $position + 4, 4);
            $header = 8;

            if ($size === 1) {
                $size = self::uint64(substr($data, $position + 8, 8));
                $header = 16;
            } elseif ($size === 0) {
                $size = $end - $position;
            }

            if ($size < $header || $position + $size > $end) {
                return null;
            }

            if ($type === 'stco' || $type === 'co64') {
                $entries = unpack('N', substr($data, $position + $header + 4, 4))[1];
                $width = $type === 'stco' ? 4 : 8;
                $table = $position + $header + 8;

                if ($table + $entries * $width > $position + $size) {
                    return null;
                }

                for ($i = 0; $i < $entries; $i++) {
                    $at = $table + $i * $width;
                    if ($type === 'stco') {
                        $value = unpack('N', substr($data, $at, 4))[1] + $delta;
                        if ($value > 0xFFFFFFFF) {
                            return null;
                        }
                        $data = substr_replace($data, pack('N', $value), $at, 4);
                    } else {
                        $value = self::uint64(substr($data, $at, 8)) + $delta;
                        $data = substr_replace($data, pack('J', $value), $at, 8);
                    }
                }
            } elseif (in_array($type, self::CONTAINERS, true)) {
                $inner = self::walk($data, $position + $header, $position + $size, $delta);
                if ($inner === null) {
                    return null;
                }
                $data = $inner;
            }

            $position += $size;
        }

        return $data;
    }

    /**
     * @param  list<array{type: string, offset: int, size: int}>  $boxes
     * @return array{type: string, offset: int, size: int}|null
     */
    private static function find(array $boxes, string $type): ?array
    {
        foreach ($boxes as $box) {
            if ($box['type'] === $type) {
                return $box;
            }
        }

        return null;
    }

    /** @param resource $in @param resource $out */
    private static function copy($in, $out, int $offset, int $length): void
    {
        fseek($in, $offset);
        $copied = stream_copy_to_stream($in, $out, $length);
        if ($copied !== $length) {
            throw new RuntimeException('The video could not be copied.');
        }
    }

    private static function uint64(string $bytes): int
    {
        return unpack('J', $bytes)[1];
    }
}
