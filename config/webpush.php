<?php

return [
    // Optional. Run push:provision to create persistent keys when these are empty.
    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),
    // Laragon may inherit Apache's configuration for a different OpenSSL build.
    // Prefer this PHP runtime's bundled configuration on Windows when available.
    'openssl_config' => env('WEBPUSH_OPENSSL_CONFIG', PHP_OS_FAMILY === 'Windows'
        && is_file(dirname(PHP_BINARY).'/extras/ssl/openssl.cnf')
            ? dirname(PHP_BINARY).'/extras/ssl/openssl.cnf' : ''),
    // Contact for push services (RFC 8292): a mailto: or https: URL.
    'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'admin@example.com')),
];
