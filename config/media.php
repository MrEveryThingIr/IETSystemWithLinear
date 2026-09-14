<?php

return [
    'scanner' => [
        'binary' => env('MEDIA_SCANNER_BINARY', 'clamscan'),
        'timeout' => (int) env('MEDIA_SCANNER_TIMEOUT', 120),
    ],
];
