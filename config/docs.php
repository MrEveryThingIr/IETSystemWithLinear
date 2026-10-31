<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public documentation
    |--------------------------------------------------------------------------
    |
    | The documentation lives in resources/docs/{locale}/*.md and is served at
    | /docs. It ships with the code, so it never needs seeding and it is never
    | mixed with user-written Content.
    |
    | "public"  anyone can read it.
    | "invite"  only signed-in people and holders of an invitation link
    |           (php artisan docs:invite "Label") can read it.
    |
    */

    'access' => env('DOCS_ACCESS', 'public'),

    'path' => resource_path('docs'),

    /** Fields of work the collaboration form offers. The labels live in the docs language files under "fields". */
    'fields' => [
        'economics', 'finance', 'accounting', 'law', 'cooperatives', 'labor', 'construction', 'trade',
        'sociology', 'psychology', 'education', 'software', 'security', 'design', 'data', 'government', 'other',
    ],
];
