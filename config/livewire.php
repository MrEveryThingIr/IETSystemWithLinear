<?php

/*
| Only the temporary upload settings are overridden here; every other Livewire
| option keeps the package default (the package config is merged underneath).
|
| Livewire validates every temporary upload with its own rule (12 MB by
| default) before the component sees the file. That default silently blocked
| Content uploads larger than 12 MB, so the rule now follows the largest
| application media limit. Each component still validates its own, smaller,
| per-type limit, and PHP's host limits still apply.
*/

return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => [
            'required',
            'file',
            'max:'.max(array_map('intval', [
                env('MEDIA_LIMIT_ASSET_KB', 12288),
                env('MEDIA_LIMIT_CONTENT_IMAGE_KB', 15360),
                env('MEDIA_LIMIT_CONTENT_DOCUMENT_KB', 25600),
                env('MEDIA_LIMIT_CONTENT_AUDIO_KB', 51200),
                env('MEDIA_LIMIT_CONTENT_VIDEO_KB', 102400),
            ])),
        ],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        // Large media on slow connections needs more than the 5 minute default.
        'max_upload_time' => (int) env('LIVEWIRE_MAX_UPLOAD_MINUTES', 30),
        'cleanup' => true,
    ],
];
