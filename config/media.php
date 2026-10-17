<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Malware scanner policy
    |--------------------------------------------------------------------------
    |
    | enabled:  run the configured scanner binary for every uploaded Asset.
    |           Scanning needs process execution (proc_open). When it is
    |           disabled, or the host cannot execute processes, no process is
    |           started and the Asset is recorded with scan_status=unavailable.
    |           An unscanned Asset is never marked "clean".
    |
    | required: whether an Asset may become usable without a malware scan.
    |           true  -> an unscanned Asset stays unusable (processing failed).
    |           false -> after its stored SHA-256 is verified, an unscanned Asset
    |                    may become ready and publishable.
    |
    | Shared hosts without proc_open (for example DirectAdmin shared hosting)
    | use MEDIA_SCANNER_ENABLED=false and MEDIA_SCANNER_REQUIRED=false.
    |
    */
    'scanner' => [
        'enabled' => (bool) env('MEDIA_SCANNER_ENABLED', true),
        'required' => (bool) env('MEDIA_SCANNER_REQUIRED', true),
        'binary' => env('MEDIA_SCANNER_BINARY', 'clamscan'),
        'timeout' => (int) env('MEDIA_SCANNER_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload limits (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Application limits per upload kind. The effective limit used for
    | validation is the lower of this value and the PHP host limits
    | (upload_max_filesize, post_max_size), so a host with smaller limits
    | produces a normal validation error instead of a server error.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Resumable uploads (large audio and video)
    |--------------------------------------------------------------------------
    |
    | Lesson video and long audio are uploaded in chunks, each one small enough
    | for the host's PHP upload limits, so file size is not limited by
    | post_max_size. chunk_kb is lowered automatically below the host limit.
    |
    */
    'resumable' => [
        'chunk_kb' => (int) env('MEDIA_RESUMABLE_CHUNK_KB', 4096),
        'max_video_kb' => (int) env('MEDIA_RESUMABLE_MAX_VIDEO_KB', 1048576),
        'max_audio_kb' => (int) env('MEDIA_RESUMABLE_MAX_AUDIO_KB', 512000),
        'session_hours' => (int) env('MEDIA_RESUMABLE_SESSION_HOURS', 48),
        'max_open_sessions' => (int) env('MEDIA_RESUMABLE_MAX_OPEN_SESSIONS', 5),
    ],

    /*
    | Video processing implementation (App\Contracts\Media\VideoProcessor).
    | "local" works on shared hosting: no transcoding, browser-measured
    | metadata and poster, byte-range streaming of the original file.
    */
    'video' => [
        'processor' => env('MEDIA_VIDEO_PROCESSOR', 'local'),
    ],

    'limits' => [
        'profile_image' => (int) env('MEDIA_LIMIT_PROFILE_IMAGE_KB', 8192),
        'asset' => (int) env('MEDIA_LIMIT_ASSET_KB', 12288),
        'business_intro_video' => (int) env('MEDIA_LIMIT_BUSINESS_INTRO_VIDEO_KB', 51200),
        'real_estate_image' => (int) env('MEDIA_LIMIT_REAL_ESTATE_IMAGE_KB', 12288),
        'real_estate_audio' => (int) env('MEDIA_LIMIT_REAL_ESTATE_AUDIO_KB', 25600),
        'real_estate_video' => (int) env('MEDIA_LIMIT_REAL_ESTATE_VIDEO_KB', 102400),

        // Content Studio limits per media type. Uploads still pass through
        // PHP, so the effective limit is capped by the host limits; larger
        // video uses the resumable upload path instead.
        'content_image' => (int) env('MEDIA_LIMIT_CONTENT_IMAGE_KB', 15360),
        'content_document' => (int) env('MEDIA_LIMIT_CONTENT_DOCUMENT_KB', 25600),
        'content_audio' => (int) env('MEDIA_LIMIT_CONTENT_AUDIO_KB', 51200),
        'content_video' => (int) env('MEDIA_LIMIT_CONTENT_VIDEO_KB', 102400),
    ],
];
