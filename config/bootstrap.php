<?php

return [
    'superadmin' => [
        'username' => env('IET_BOOTSTRAP_SUPERADMIN_USERNAME', 'MrEveryThing'),
        'email' => env('IET_BOOTSTRAP_SUPERADMIN_EMAIL', 'mreverything@example.test'),
        'password' => env('IET_BOOTSTRAP_SUPERADMIN_PASSWORD', 'password'),
    ],

    // A normal fresh seed is intentionally clean. Historical/demo scenarios
    // are opt-in so browser acceptance can begin from a real first-use state.
    'demo_data' => (bool) env('IET_SEED_DEMO_DATA', false),
];
