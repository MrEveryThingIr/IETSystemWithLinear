<?php

namespace App\Contracts\Media;

use App\Models\Asset;
use Illuminate\Http\UploadedFile;

/**
 * Turns an uploaded audio/video Asset into playable derivatives.
 *
 * Content and Asset never depend on a particular implementation. The default
 * one works on shared hosting (no FFmpeg, no background workers): it records
 * the browser-measured metadata and poster frame and streams the original
 * file with byte-range support. An FFmpeg worker or a managed video service
 * can implement this interface later to add adaptive (HLS) renditions as
 * extra asset_media_variants rows, without changing Content.
 */
interface VideoProcessor
{
    /**
     * @param  array{duration_ms?: mixed, width?: mixed, height?: mixed}  $metadata  measured by the uploading browser
     */
    public function process(Asset $asset, array $metadata, ?UploadedFile $poster): void;

    /** Short identifier shown in operations output (for example "local"). */
    public function name(): string;
}
