<?php

return [
    'enabled' => (bool) env('AI_ASSISTANCE_ENABLED', false),

    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_AUTHORING_MODEL', 'gpt-5.6-luna'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],
];
