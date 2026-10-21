<?php

return [
    // Optional. When empty, keys are created once and kept in storage/app/private.
    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),
    // Contact for push services (RFC 8292): a mailto: or https: URL.
    'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'admin@example.com')),
];
